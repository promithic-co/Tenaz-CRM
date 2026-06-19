<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Deprecated no-op shim.
 *
 * The legacy split-message send path was replaced by the WhatsappOutboxMessage
 * pattern (persistence + idempotency_key dedupe + in_doubt handling). No live
 * code dispatches this job anymore. The class is retained for one deploy cycle
 * so any payloads still sitting in the Redis "messages" queue deserialize and
 * drain harmlessly instead of crashing the worker. Remove after the queue is
 * confirmed drained.
 */
class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public readonly string $instance,
        public readonly string $number,
        public readonly string $text,
        public readonly ?int $instanceId = null,
    ) {
        $this->onQueue('messages');
    }

    public function handle(): void
    {
        Log::warning('whatsapp.send_job_deprecated_noop', [
            'instance' => $this->instance,
            'number' => $this->number,
            'instance_id' => $this->instanceId,
            'reason' => 'legacy_send_path_removed_use_outbox',
        ]);
    }
}
