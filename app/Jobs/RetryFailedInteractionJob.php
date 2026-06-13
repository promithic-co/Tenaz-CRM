<?php

namespace App\Jobs;

use App\Models\FailedInteraction;
use App\Models\ServiceTicket;
use App\Services\AgentInteractionEventService;
use App\Services\AgentService;
use App\Services\AlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetryFailedInteractionJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 90;

    public int $tries = 2;

    public function __construct(
        public FailedInteraction $failedInteraction,
    ) {}

    public function handle(AgentService $agentService, AlertService $alertService): void
    {
        $interactionEvents = app(AgentInteractionEventService::class);
        $interactionId = $interactionEvents->newInteractionId();
        $failure = $this->failedInteraction;

        if ($failure->status !== 'pending') {
            return;
        }

        $failure->markRetrying();

        try {
            $lead = $failure->lead;
            $context = $failure->context;

            $interactionEvents->recordForLead(
                interactionId: $interactionId,
                lead: $lead,
                eventType: 'laboratory_reprocess_started',
                eventSource: 'retry_failed_interaction_job',
                payload: [
                    'failure_id' => $failure->id,
                    'retry_count' => $failure->retry_count,
                    'error_tag' => $failure->error_tag,
                    'error_source' => $failure->error_source,
                ],
            );

            $response = $agentService->process($lead, $context['original_message'] ?? '', interactionId: $interactionId);

            if ($response) {
                $failure->markResolved();

                Log::info('laboratory.retry_success', [
                    'interaction_id' => $interactionId,
                    'failure_id' => $failure->id,
                    'lead_id' => $lead->id,
                    'attempt' => $failure->retry_count,
                ]);

                $interactionEvents->recordForLead(
                    interactionId: $interactionId,
                    lead: $lead,
                    eventType: 'laboratory_reprocess_succeeded',
                    eventSource: 'retry_failed_interaction_job',
                    payload: ['failure_id' => $failure->id, 'retry_count' => $failure->retry_count],
                );
            }
        } catch (Throwable $e) {
            $maxAttempts = config('laboratory.retry.max_attempts', 3);

            if ($failure->retry_count >= $maxAttempts) {
                $failure->markEscalated();

                ServiceTicket::create([
                    'lead_id' => $failure->lead_id,
                    'type' => 'escalation',
                    'status' => ServiceTicket::STATUS_OPEN,
                    'reason' => 'problema_tecnico',
                    'summary' => "Retry automático falhou {$maxAttempts}x — tag: {$failure->error_tag}, fonte: {$failure->error_source}. Interação original não entregue.",
                ]);

                $failure->lead->update([
                    'status' => 'escalado',
                    'followup_status' => 'inactive',
                ]);

                $alertService->sendAlert(
                    'retry_exhausted',
                    "Lead {$failure->lead->nome} (#{$failure->lead_id}) — {$failure->error_tag} falhou {$maxAttempts}x. Escalado para humano via ServiceTicket.",
                    ['failure_id' => $failure->id, 'lead_id' => $failure->lead_id],
                );

                Log::error('laboratory.retry_exhausted', [
                    'interaction_id' => $interactionId,
                    'failure_id' => $failure->id,
                    'lead_id' => $failure->lead_id,
                    'error_tag' => $failure->error_tag,
                    'attempts' => $failure->retry_count,
                ]);

                $interactionEvents->recordForLead(
                    interactionId: $interactionId,
                    lead: $failure->lead,
                    eventType: 'laboratory_reprocess_failed',
                    eventSource: 'retry_failed_interaction_job',
                    payload: [
                        'failure_id' => $failure->id,
                        'retry_count' => $failure->retry_count,
                        'error' => $e->getMessage(),
                        'exhausted' => true,
                    ],
                    severity: 'error',
                );
            } else {
                $failure->scheduleNextRetry();

                Log::warning('laboratory.retry_failed', [
                    'interaction_id' => $interactionId,
                    'failure_id' => $failure->id,
                    'attempt' => $failure->retry_count,
                    'next_retry_at' => $failure->next_retry_at,
                    'error' => $e->getMessage(),
                ]);

                $interactionEvents->recordForLead(
                    interactionId: $interactionId,
                    lead: $failure->lead,
                    eventType: 'laboratory_reprocess_failed',
                    eventSource: 'retry_failed_interaction_job',
                    payload: [
                        'failure_id' => $failure->id,
                        'retry_count' => $failure->retry_count,
                        'next_retry_at' => $failure->next_retry_at?->toIso8601String(),
                        'error' => $e->getMessage(),
                        'exhausted' => false,
                    ],
                    severity: 'warning',
                );
            }
        }
    }
}
