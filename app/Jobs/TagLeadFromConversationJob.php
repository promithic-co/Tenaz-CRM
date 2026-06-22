<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\LeadAutoTaggingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Async job that evaluates AI-assisted tag assignment for a single Lead.
 *
 * Runs on the dedicated `auto-tags` queue (low-priority, never blocks `messages`).
 * Overlap protection via WithoutOverlapping and a conservative rate limit.
 */
class TagLeadFromConversationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $maxExceptions = 2;

    public int $timeout = 60;

    public function __construct(
        public readonly int $leadId,
        public readonly string $trigger,
        public readonly ?int $requestedByUserId = null,
    ) {
        $this->onQueue('auto-tags');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("auto_tag_lead_{$this->leadId}"))
                ->releaseAfter(60)
                ->expireAfter(300),
            new RateLimited('auto-tags'),
        ];
    }

    /**
     * Exponential backoff between retries.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 60];
    }

    public function retryUntil(): ?\DateTimeInterface
    {
        $windowSeconds = (int) config('credflow.jobs.auto_tag_retry_window_seconds', 1800);

        return $windowSeconds > 0 ? now()->addSeconds($windowSeconds) : null;
    }

    public function handle(LeadAutoTaggingService $service): void
    {
        $lead = Lead::find($this->leadId);

        if (! $lead) {
            return;
        }

        $result = $service->evaluate($lead, $this->trigger, $this->requestedByUserId);

        Log::info('auto-tag job completed', [
            'lead_id' => $this->leadId,
            'trigger' => $this->trigger,
            'skipped' => $result['skipped'] ?? false,
            'applied_count' => count($result['applied'] ?? []),
        ]);
    }
}
