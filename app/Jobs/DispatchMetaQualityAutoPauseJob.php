<?php

namespace App\Jobs;

use App\Enums\WhatsAppProvider;
use App\Models\WhatsappInstance;
use App\Services\MetaQualityRiskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchMetaQualityAutoPauseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $whatsappInstanceId) {}

    public function handle(MetaQualityRiskService $riskService): void
    {
        $instance = WhatsappInstance::withoutGlobalScope('tenant')->find($this->whatsappInstanceId);
        if (! $instance || $instance->provider !== WhatsAppProvider::MetaCloud) {
            return;
        }

        $riskService->pauseInstanceCampaignsForRed($instance);
    }
}
