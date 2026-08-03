<?php

namespace App\Jobs;

use App\Enums\WhatsAppProvider;
use App\Events\InstanceHealthChanged;
use App\Models\WhatsappInstance;
use App\Services\WhatsApp\MetaHealthService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Refreshes one instance's Meta messaging health snapshot from the Graph API.
 *
 * Runs off the scheduler every 15 minutes and on demand from the panel's refresh
 * button, so the WhatsApp page never has to make a blocking Graph call while
 * rendering.
 */
class SyncMetaHealthJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(public readonly int $instanceId)
    {
        $this->onQueue('default');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("meta_health_sync_instance:{$this->instanceId}"))
                ->expireAfter(120)
                ->releaseAfter(60),
        ];
    }

    public function handle(MetaHealthService $health): void
    {
        $instance = WhatsappInstance::withoutGlobalScope('tenant')->find($this->instanceId);

        if (! $instance || $instance->provider !== WhatsAppProvider::MetaCloud) {
            return;
        }

        if ($instance->hasExpiredMetaToken()) {
            Log::warning('SyncMetaHealthJob: Meta token expired', ['instance_id' => $instance->id]);

            return;
        }

        $previous = $instance->healthStatus();

        $current = $health->refresh($instance);

        // Only broadcast on an actual transition. The scheduler fires this every
        // 15 minutes per instance; pushing an unchanged status each tick would be
        // pure noise on the private channel.
        if ($current !== $previous) {
            InstanceHealthChanged::dispatch(
                $instance->id,
                $current->value,
                $instance->fresh()?->healthReasons() ?? [],
            );
        }
    }
}
