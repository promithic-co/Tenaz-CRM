<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\FailedInteraction;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;

class InteractionRecoveryService
{
    /**
     * Record a failed interaction and schedule retry.
     * Called from catch blocks in AgentService, WhatsAppService, etc.
     */
    public function recordFailure(
        Lead $lead,
        Agent $agent,
        string $errorTag,
        string $errorSource,
        string $errorMessage,
        ?array $context = null,
    ): FailedInteraction {
        $failure = FailedInteraction::create([
            'lead_id' => $lead->id,
            'agent_id' => $agent->id,
            'error_tag' => $errorTag,
            'error_source' => $errorSource,
            'error_message' => $errorMessage,
            'context' => $context,
            'status' => 'pending',
            'next_retry_at' => $this->calculateFirstRetry(),
        ]);

        Log::warning('laboratory.failure_recorded', [
            'failure_id' => $failure->id,
            'lead_id' => $lead->id,
            'error_tag' => $errorTag,
            'error_source' => $errorSource,
            'next_retry_at' => $failure->next_retry_at,
        ]);

        return $failure;
    }

    /**
     * Calculate first retry time — within business hours.
     */
    private function calculateFirstRetry(): \Carbon\Carbon
    {
        $start = config('laboratory.retry.business_hours_start', '08:00');
        $end = config('laboratory.retry.business_hours_end', '18:00');
        $delay = config('laboratory.retry.backoff_minutes', [15])[0];

        $retryAt = \Carbon\Carbon::now()->addMinutes($delay);

        if ($retryAt->format('H:i') > $end) {
            $retryAt = \Carbon\Carbon::instance($retryAt->addDay())->setTimeFromTimeString($start);
        } elseif ($retryAt->format('H:i') < $start) {
            $retryAt = \Carbon\Carbon::instance($retryAt)->setTimeFromTimeString($start);
        }

        while ($retryAt->isWeekend()) {
            $retryAt = \Carbon\Carbon::instance($retryAt->addDay())->setTimeFromTimeString($start);
        }

        return $retryAt;
    }
}
