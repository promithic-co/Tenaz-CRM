<?php

namespace App\Jobs;

use App\Enums\WhatsAppProvider;
use App\Models\WhatsappInstance;
use App\Services\AlertService;
use App\Services\MetaQualityRiskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchMetaQualityAutoPauseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Safety control (REL-7): auto-pause for RED quality must not silently exhaust — a
    // degraded instance that keeps sending burns Meta reputation. Explicit retries + a
    // loud failed() handler so exhaustion is alerted, not dropped.
    public int $tries = 3;

    public int $timeout = 60;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public readonly int $whatsappInstanceId) {}

    public function handle(MetaQualityRiskService $riskService): void
    {
        $instance = WhatsappInstance::withoutGlobalScope('tenant')->find($this->whatsappInstanceId);
        if (! $instance || $instance->provider !== WhatsAppProvider::MetaCloud) {
            return;
        }

        $riskService->pauseInstanceCampaignsForRed($instance);
    }

    public function failed(Throwable $e): void
    {
        Log::critical('meta.quality_autopause_failed', [
            'whatsapp_instance_id' => $this->whatsappInstanceId,
            'error' => $e->getMessage(),
        ]);

        app(AlertService::class)->sendAlert(
            'meta_quality_autopause_failed',
            "Auto-pause for RED-quality WhatsApp instance #{$this->whatsappInstanceId} failed after retries: {$e->getMessage()}. The instance may keep sending while degraded.",
            ['whatsapp_instance_id' => $this->whatsappInstanceId],
        );
    }
}
