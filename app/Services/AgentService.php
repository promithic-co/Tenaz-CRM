<?php

namespace App\Services;

use App\Ai\AgentFactory;
use App\Ai\Agents\BaseCustomerServiceAgent;
use App\Ai\DTOs\MediaContext;
use App\Ai\Exceptions\ToolCallCeilingExceededException;
use App\Contracts\AgentServiceInterface;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Sentry\State\Scope;
use Throwable;

// @pint-ignore: used via app() container

class AgentService implements AgentServiceInterface
{
    /** When the agent outputs this exact phrase, no message is sent to the user (same-turn no reply). */
    public const NO_REPLY_SENTINEL = '[CREDFLOW_NAO_RESPONDER]';

    /** Safe message sent to the customer whenever the fact-check guardrail forces a human handoff. */
    public const HUMAN_HANDOFF_MESSAGE = 'Houve uma inconsistência sistêmica na leitura detalhada do seu benefício e decidi por segurança passar seu atendimento para a nossa equipe humana. Em instantes um especialista da corretora vai confirmar seus valores e assumir o contato por aqui, ok?';

    private float $requestStartTime;

    public function __construct(
        private readonly MediaUnderstandingService $media,
        private readonly FactCheckService $factCheck,
        private readonly AgentFactory $agentFactory,
        private readonly AgentInteractionEventService $interactionEvents,
        private readonly AgentInteractionContext $interactionContext,
        private readonly ConversationContextSynchronizer $contextSync,
        private readonly AiRunRecorder $aiRuns,
    ) {}

    /**
     * Processa uma mensagem com o agente ARIA.
     * Retorna a resposta em texto ou null (lead opt-out).
     */
    public function process(Lead $lead, string $message, ?MediaContext $mediaContext = null, ?string $interactionId = null): ?string
    {
        $interactionId ??= $this->interactionEvents->newInteractionId();

        if ($lead->status === 'optou_sair') {
            $this->interactionEvents->bufferForLead(
                interactionId: $interactionId,
                lead: $lead,
                eventType: 'agent_skipped',
                eventSource: 'agent_service',
                payload: ['reason' => 'lead_opt_out'],
            );
            $this->interactionEvents->flush();

            return null;
        }

        $this->requestStartTime = microtime(true);

        $now = now();
        $lead->updateQuietly([
            'last_interaction_at' => $now,
            'last_inbound_at' => $now,
        ]);

        $enrichedMessage = $this->buildMessage($message, $mediaContext);

        Log::info('aria.request', [
            'interaction_id' => $interactionId,
            'lead_id' => $lead->id,
            'whatsapp' => $lead->whatsapp,
            'tenant_id' => $lead->tenant_id,
            'modo' => $lead->modo,
            'msg_len' => strlen($enrichedMessage),
            'has_media' => $mediaContext !== null,
            'media_type' => $mediaContext?->type->value,
        ]);

        $this->interactionEvents->bufferForLead(
            interactionId: $interactionId,
            lead: $lead,
            eventType: 'agent_started',
            eventSource: 'agent_service',
            payload: [
                'message_length' => strlen($enrichedMessage),
                'has_media' => $mediaContext !== null,
                'media_type' => $mediaContext?->type->value,
            ],
        );

        $this->interactionContext->set([
            'interaction_id' => $interactionId,
            'tenant_id' => (string) $lead->tenant_id,
            'lead_id' => $lead->id,
            'agent_id' => $lead->agent_id,
            'source' => 'agent_service',
        ]);

        if (app()->bound('sentry')) {
            \Sentry\withScope(function (Scope $scope) use ($lead, $interactionId): void {
                $scope->setUser(['id' => (string) $lead->id]);
                $scope->setTag('lead_tenant', (string) $lead->tenant_id);
                $scope->setTag('interaction_id', $interactionId);
            });
        }

        try {
            $agent = $this->agentFactory->make($lead);
            $this->aiRuns->start(
                runId: $interactionId,
                lead: $lead,
                agentName: class_basename($agent),
                architectureVersion: $this->architectureVersion(),
            );

            if ($lead->conversation_id) {
                // Mirror any timeline rows the agent hasn't seen yet (operator turns,
                // inbound received while AI was paused) into agent_conversation_messages
                // BEFORE continuing the conversation. Source of truth = timeline.
                $synced = $this->contextSync->syncPending($lead);
                if ($synced > 0) {
                    $this->interactionEvents->bufferForLead(
                        interactionId: $interactionId,
                        lead: $lead,
                        eventType: 'context_synced',
                        eventSource: 'agent_service',
                        payload: ['rows_synced' => $synced],
                    );
                }

                try {
                    $response = $agent
                        ->continue($lead->conversation_id, as: $lead)
                        ->prompt($enrichedMessage);
                } catch (Throwable $e) {
                    Log::warning('aria.conversation_recovery', [
                        'interaction_id' => $interactionId,
                        'lead_id' => $lead->id,
                        'old_conversation_id' => $lead->conversation_id,
                        'error' => $e->getMessage(),
                    ]);
                    $lead->update(['conversation_id' => null]);
                    $response = $agent->forUser($lead)->prompt($enrichedMessage);
                    $lead->update(['conversation_id' => $response->conversationId]);
                }
            } else {
                $response = $agent->forUser($lead)->prompt($enrichedMessage);
                $lead->update(['conversation_id' => $response->conversationId]);
            }

            if ($mediaContext && $lead->conversation_id) {
                $this->persistMediaMeta($lead->conversation_id, $enrichedMessage, $mediaContext);
            }

            // The inbound timeline row for this turn is now mirrored inside laravel/ai
            // (prompt() wrote its own user row), so mark it synced to prevent the next
            // syncPending() call from duplicating it.
            $this->markInboundSynced($lead, $interactionId);

            $text = (string) $response;
            $text = $this->applyFactCheckGuardrail($agent, $lead, $text, $interactionId);

            if ($this->shouldNotSendReply($text)) {
                Log::info('aria.no_reply', [
                    'interaction_id' => $interactionId,
                    'lead_id' => $lead->id,
                    'reason' => 'sentinel_or_empty',
                ]);

                $this->interactionEvents->bufferForLead(
                    interactionId: $interactionId,
                    lead: $lead,
                    eventType: 'agent_no_reply',
                    eventSource: 'agent_service',
                    payload: ['reason' => 'sentinel_or_empty'],
                );
                $this->aiRuns->finish($interactionId, 'success', 'no_response');

                return null;
            }

            Log::info('aria.response', [
                'interaction_id' => $interactionId,
                'lead_id' => $lead->id,
                'response_len' => strlen($text),
            ]);

            $this->interactionEvents->bufferForLead(
                interactionId: $interactionId,
                lead: $lead,
                eventType: 'agent_response_ready',
                eventSource: 'agent_service',
                payload: ['response_length' => strlen($text)],
            );
            $this->aiRuns->finish(
                runId: $interactionId,
                status: $this->finalStatus($lead),
                outcome: $this->finalOutcome($lead, $text),
            );

            return $text;

        } catch (\PDOException $e) {
            // DB is down — rethrow so the job retries
            $this->aiRuns->finish($interactionId, 'error', null, class_basename($e));
            throw $e;
        } catch (\InvalidArgumentException $e) {
            // Configuration/programming error — fail fast
            $this->aiRuns->finish($interactionId, 'error', null, class_basename($e));
            throw $e;
        } catch (ToolCallCeilingExceededException $e) {
            // Tool call loop detected — return safe escalation
            Log::warning('aria.tool_ceiling_hit', [
                'interaction_id' => $interactionId,
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);

            $this->interactionEvents->bufferForLead(
                interactionId: $interactionId,
                lead: $lead,
                eventType: 'tool_loop_blocked',
                eventSource: 'agent_service',
                payload: ['error' => $e->getMessage()],
                severity: 'warning',
            );
            $this->aiRuns->finish($interactionId, 'fallback', 'transferred', 'tool_loop');

            return 'Estou enfrentando uma dificuldade técnica neste momento. Vou passar seu atendimento para nossa equipe humana que poderá ajudá-lo diretamente.';
        } catch (Throwable $e) {
            // RuntimeException from config validation — rethrow
            if ($e instanceof \RuntimeException && ! $this->isTransientError($e)) {
                $this->aiRuns->finish($interactionId, 'error', null, class_basename($e));
                throw $e;
            }

            // AI provider / transient errors — graceful fallback
            Log::error('aria.agent_error', [
                'interaction_id' => $interactionId,
                'lead_id' => $lead->id,
                'whatsapp' => $lead->whatsapp,
                'error' => $e->getMessage(),
            ]);

            $this->interactionEvents->bufferForLead(
                interactionId: $interactionId,
                lead: $lead,
                eventType: 'agent_failed',
                eventSource: 'agent_service',
                payload: [
                    'error_tag' => $this->classifyError($e),
                    'error_source' => $this->detectSource($e),
                    'error' => $e->getMessage(),
                ],
                severity: 'error',
            );

            if ($lead->agent) {
                app(InteractionRecoveryService::class)->recordFailure(
                    lead: $lead,
                    agent: $lead->agent,
                    errorTag: $this->classifyError($e),
                    errorSource: $this->detectSource($e),
                    errorMessage: $e->getMessage(),
                    context: ['original_message' => $message],
                );
            }
            $this->aiRuns->finish($interactionId, 'fallback', 'no_response', $this->classifyError($e));

            return 'Vou verificar isso e já retorno em breve. Aguarde um momento.';
        } finally {
            // SCALE-7: single bulk insert of every event buffered this turn. Runs on all exit
            // paths (success, no-reply, handled exceptions, rethrows) so observability rows are
            // durable before the worker moves on; fail-open so a flush error can't mask the turn.
            $this->interactionEvents->flush();
            $this->aiRuns->finish($interactionId, 'fallback', 'no_response');
            $this->interactionContext->clear();
        }
    }

    private function shouldNotSendReply(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return true;
        }

        return str_contains($trimmed, self::NO_REPLY_SENTINEL);
    }

    private function architectureVersion(): string
    {
        $version = (string) config('credflow.agent.architecture_version', 'legacy_prompt');

        return in_array($version, ['legacy_prompt', 'folder_skills', 'hybrid'], true)
            ? $version
            : 'legacy_prompt';
    }

    private function finalStatus(Lead $lead): string
    {
        return $lead->status === 'escalado' ? 'human_handoff' : 'success';
    }

    private function finalOutcome(Lead $lead, string $text): string
    {
        return match ($lead->status) {
            'qualificado' => 'qualified',
            'convertido' => 'scheduled',
            'escalado' => 'transferred',
            default => str_ends_with(trim($text), '?') ? 'asked_next_question' : 'replied',
        };
    }

    /**
     * Fact-check guardrail with timeout safety and reduced retry budget.
     */
    private function applyFactCheckGuardrail(BaseCustomerServiceAgent $agent, Lead $lead, string $text, string $interactionId): string
    {
        $error = $this->factCheck->validateAgentResponse($lead, $text);
        if (! $error) {
            $this->interactionEvents->bufferForLead(
                interactionId: $interactionId,
                lead: $lead,
                eventType: 'fact_check_passed',
                eventSource: 'agent_service',
            );

            return $text;
        }

        if ($this->hasExceededTimeout()) {
            Log::critical('aria.fact_check_timeout_escalation', [
                'interaction_id' => $interactionId,
                'lead_id' => $lead->id,
            ]);

            $this->interactionEvents->bufferForLead(
                interactionId: $interactionId,
                lead: $lead,
                eventType: 'fact_check_failed',
                eventSource: 'agent_service',
                payload: ['reason' => 'timeout', 'error' => $error, 'action' => 'human_escalation'],
                severity: 'critical',
            );
            $lead->update(['status' => 'escalado']);

            return self::HUMAN_HANDOFF_MESSAGE;
        }

        Log::warning('aria.fact_check_retry', [
            'interaction_id' => $interactionId,
            'lead_id' => $lead->id,
            'error' => $error,
        ]);

        $this->interactionEvents->bufferForLead(
            interactionId: $interactionId,
            lead: $lead,
            eventType: 'fact_check_failed',
            eventSource: 'agent_service',
            payload: ['error' => $error, 'action' => 'retry'],
            severity: 'warning',
        );

        $fallbackResponse = $agent->continue($lead->conversation_id, as: $lead)->prompt($error);
        $text = (string) $fallbackResponse;

        $secondError = $this->factCheck->validateAgentResponse($lead, $text);
        if ($secondError) {
            Log::critical('aria.fact_check_failed_twice', [
                'interaction_id' => $interactionId,
                'lead_id' => $lead->id,
            ]);

            $this->interactionEvents->bufferForLead(
                interactionId: $interactionId,
                lead: $lead,
                eventType: 'fact_check_failed',
                eventSource: 'agent_service',
                payload: ['error' => $secondError, 'action' => 'human_escalation'],
                severity: 'critical',
            );
            $lead->update(['status' => 'escalado']);

            return self::HUMAN_HANDOFF_MESSAGE;
        }

        $this->interactionEvents->bufferForLead(
            interactionId: $interactionId,
            lead: $lead,
            eventType: 'fact_check_passed',
            eventSource: 'agent_service',
            payload: ['after_retry' => true],
        );

        return $text;
    }

    /** Check if the request has exceeded the safety timeout. */
    private function hasExceededTimeout(): bool
    {
        $maxSeconds = config('credflow.agent.timeout_seconds', 45);

        return (microtime(true) - $this->requestStartTime) > $maxSeconds;
    }

    private function buildMessage(string $message, ?MediaContext $mediaContext): string
    {
        if (! $mediaContext) {
            return $message;
        }

        $mediaText = $this->media->process($mediaContext);

        if (! $mediaText) {
            $fallback = $this->media->fallbackMessage($mediaContext);

            return $message ? "{$fallback}\n\n{$message}" : $fallback;
        }

        return $message ? "{$mediaText}\n\n{$message}" : $mediaText;
    }

    /**
     * Classify a provider error by exception type and HTTP status code first, falling back
     * to message-substring matching only for wrapped/opaque errors that expose no other signal.
     */
    private function classifyError(Throwable $e): string
    {
        if ($e instanceof RateLimitedException) {
            return 'rate_limit';
        }

        if ($e instanceof ProviderOverloadedException) {
            return 'server_error';
        }

        $statusTag = match ((int) $e->getCode()) {
            429 => 'rate_limit',
            500, 502, 503, 504 => 'server_error',
            default => null,
        };

        if ($statusTag !== null) {
            return $statusTag;
        }

        $message = strtolower($e->getMessage());

        return match (true) {
            str_contains($message, 'timeout') => 'timeout',
            str_contains($message, 'rate limit') || str_contains($message, '429') => 'rate_limit',
            str_contains($message, 'context') || str_contains($message, 'token') => 'context_overflow',
            str_contains($message, 'connection') => 'connection_error',
            str_contains($message, '500') || str_contains($message, '502') || str_contains($message, '503') => 'server_error',
            default => 'unknown',
        };
    }

    private function detectSource(Throwable $e): string
    {
        $message = strtolower($e->getMessage());
        $class = get_class($e);

        return match (true) {
            str_contains($class, 'OpenAI') || str_contains($message, 'openai') => 'openai',
            str_contains($class, 'Anthropic') || str_contains($message, 'anthropic') => 'anthropic',
            str_contains($message, 'promosys') => 'promosys',
            str_contains($message, 'inss') => 'inss',
            str_contains($message, 'whatsapp') => 'whatsapp',
            default => 'unknown',
        };
    }

    /** Determine if an error is likely transient (network/AI) vs a config/programming error. */
    private function isTransientError(Throwable $e): bool
    {
        return in_array(
            $this->classifyError($e),
            ['timeout', 'rate_limit', 'server_error', 'connection_error'],
            true,
        );
    }

    private function persistMediaMeta(string $conversationId, string $content, MediaContext $media): void
    {
        try {
            DB::table('agent_conversation_messages')
                ->where('conversation_id', $conversationId)
                ->where('role', 'user')
                ->where('content', $content)
                ->orderByDesc('created_at')
                ->limit(1)
                ->update([
                    'attachments' => json_encode(['_aria_media' => $media->toArray()]),
                ]);
        } catch (Throwable $e) {
            Log::warning('aria.media_persist_error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Mark the inbound timeline row for the current interaction as synced. Called after
     * a successful $agent->prompt() so the synchronizer won't re-mirror this user turn.
     */
    private function markInboundSynced(Lead $lead, string $interactionId): void
    {
        try {
            DB::table('conversation_timeline_messages')
                ->where('lead_id', $lead->id)
                ->where('interaction_id', $interactionId)
                ->where('sender_type', 'lead')
                ->whereNull('synced_to_agent_at')
                ->update(['synced_to_agent_at' => now()]);
        } catch (Throwable $e) {
            Log::warning('aria.mark_inbound_synced_error', [
                'lead_id' => $lead->id,
                'interaction_id' => $interactionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
