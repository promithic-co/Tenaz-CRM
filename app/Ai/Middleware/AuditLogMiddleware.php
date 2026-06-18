<?php

namespace App\Ai\Middleware;

use App\Ai\Agents\BaseCustomerServiceAgent;
use App\Jobs\LogAiUsageJob;
use App\Services\AgentInteractionContext;
use App\Services\AgentInteractionEventService;
use App\Services\AiRunRecorder;
use App\Services\LangfuseService;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;

class AuditLogMiddleware
{
    public function __construct(
        private readonly AgentInteractionContext $interactionContext,
        private readonly AgentInteractionEventService $interactionEvents,
        private readonly AiRunRecorder $aiRuns,
    ) {}

    public function handle(AgentPrompt $prompt, Closure $next)
    {
        $start = microtime(true);

        return $next($prompt)->then(function ($response) use ($prompt, $start) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $interactionId = $this->interactionContext->interactionId();

            Log::info('aria.audit', [
                'interaction_id' => $interactionId,
                'agent' => class_basename($prompt->agent),
                'model' => $prompt->model,
                'prompt_len' => strlen($prompt->prompt),
                'reply_len' => strlen($response->text),
                'tokens_in' => $response->usage?->promptTokens ?? 0,
                'tokens_out' => $response->usage?->completionTokens ?? 0,
                'duration' => $durationMs,
            ]);

            if ($prompt->agent instanceof BaseCustomerServiceAgent) {
                $lead = $prompt->agent->lead;
                $promptTokens = (int) ($response->usage?->promptTokens ?? 0);
                $completionTokens = (int) ($response->usage?->completionTokens ?? 0);

                if ($interactionId) {
                    $this->aiRuns->recordModelCall(
                        runId: $interactionId,
                        model: $prompt->model ?? 'unknown',
                        inputTokens: $promptTokens,
                        outputTokens: $completionTokens,
                        promptHash: hash('sha256', $prompt->prompt),
                    );

                    $this->interactionEvents->recordForLead(
                        interactionId: $interactionId,
                        lead: $lead,
                        eventType: 'model_called',
                        eventSource: 'audit_log_middleware',
                        payload: [
                            'model' => $prompt->model ?? 'unknown',
                            'prompt_length' => strlen($prompt->prompt),
                            'reply_length' => strlen($response->text),
                            'tokens_in' => $promptTokens,
                            'tokens_out' => $completionTokens,
                            'duration_ms' => $durationMs,
                        ],
                    );
                }

                LogAiUsageJob::dispatch(
                    $promptTokens,
                    $completionTokens,
                    $prompt->model ?? 'unknown',
                    $lead->agent_id,
                    $lead->tenant_id
                );

                $sessionId = $lead->conversation_id
                    ? (string) $lead->conversation_id
                    : 'lead-'.$lead->id;

                app(LangfuseService::class)->logAgentLlmTurn(
                    traceId: $interactionId ?? (string) Str::uuid(),
                    tenantId: (string) $lead->tenant_id,
                    sessionId: $sessionId,
                    leadId: $lead->id,
                    agentId: $lead->agent_id,
                    model: $prompt->model ?? 'unknown',
                    input: $prompt->prompt,
                    output: $response->text,
                    promptTokens: $promptTokens,
                    completionTokens: $completionTokens,
                    durationMs: (float) $durationMs,
                    metadata: [
                        'interaction_id' => $interactionId,
                        'agent_class' => class_basename($prompt->agent),
                        'is_sandbox' => $lead->is_sandbox,
                    ],
                );
            }
        });
    }
}
