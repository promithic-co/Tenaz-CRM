<?php

namespace App\Jobs;

use App\Enums\WhatsAppProvider;
use App\Models\Campaign;
use App\Models\WhatsappInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchMetaQualityAutoPauseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $whatsappInstanceId) {}

    public function handle(): void
    {
        $instance = WhatsappInstance::withoutGlobalScope('tenant')->find($this->whatsappInstanceId);
        if (! $instance || $instance->provider !== WhatsAppProvider::MetaCloud) {
            return;
        }

        $pausedCount = Campaign::withoutGlobalScope('tenant')
            ->where('tenant_id', $instance->tenant_id)
            ->where('whatsapp_instance_id', $instance->id)
            ->whereIn('status', ['draft', 'sending', 'scheduled'])
            ->update([
                'status' => 'paused',
                'paused_at' => now(),
                'failure_reason' => 'meta_quality_red_auto_pause',
            ]);

        Log::warning('meta.quality.auto_paused', [
            'whatsapp_instance_id' => $instance->id,
            'tenant_id' => $instance->tenant_id,
            'paused_count' => $pausedCount,
        ]);
    }
}
