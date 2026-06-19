<?php

namespace App\Services;

use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Notifications\MetaQualityRedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MetaQualityRiskService
{
    public const PAUSE_REASON_CODE = 'meta_quality_red_auto_pause';

    public const FAILURE_REASON = 'Qualidade Meta RED: campanha pausada por risco de restricao ou banimento.';

    public function pauseInstanceCampaignsForRed(WhatsappInstance $instance): int
    {
        $campaigns = Campaign::withoutGlobalScope('tenant')
            ->where('tenant_id', $instance->tenant_id)
            ->where('whatsapp_instance_id', $instance->id)
            ->whereIn('status', ['draft', 'scheduled', 'sending'])
            ->get();

        foreach ($campaigns as $campaign) {
            $campaign->update([
                'status' => 'paused',
                'paused_at' => now(),
                'failure_reason' => self::FAILURE_REASON,
                'pause_reason_code' => self::PAUSE_REASON_CODE,
                'paused_from_status' => $campaign->status,
                'risk_acknowledged_at' => null,
                'risk_acknowledged_by' => null,
            ]);

            $this->notifyTenantUsers($campaign->fresh(), $instance);
        }

        Log::warning('meta.quality.red_campaigns_paused', [
            'whatsapp_instance_id' => $instance->id,
            'tenant_id' => $instance->tenant_id,
            'paused_count' => $campaigns->count(),
        ]);

        return $campaigns->count();
    }

    public function acknowledgePaused(Campaign $campaign, User $user): void
    {
        $this->ensureMetaQualityRiskCampaign($campaign);

        $campaign->update([
            'risk_acknowledged_at' => now(),
            'risk_acknowledged_by' => $user->id,
        ]);

        $this->markCampaignNotificationsRead($campaign);
    }

    public function continueWithRisk(Campaign $campaign, User $user): void
    {
        $this->ensureMetaQualityRiskCampaign($campaign);

        $previousStatus = $campaign->paused_from_status;
        if (! in_array($previousStatus, ['draft', 'scheduled', 'sending'], true)) {
            $previousStatus = 'sending';
        }

        $campaign->update([
            'status' => $previousStatus,
            'paused_at' => null,
            'risk_acknowledged_at' => now(),
            'risk_acknowledged_by' => $user->id,
        ]);

        $this->markCampaignNotificationsRead($campaign);

        if ($previousStatus === 'sending') {
            DispatchCampaignJob::dispatch($campaign->fresh());
        }
    }

    private function ensureMetaQualityRiskCampaign(Campaign $campaign): void
    {
        if ($campaign->pause_reason_code !== self::PAUSE_REASON_CODE) {
            throw new \RuntimeException('Esta campanha nao possui alerta ativo de qualidade Meta RED.');
        }
    }

    private function notifyTenantUsers(Campaign $campaign, WhatsappInstance $instance): void
    {
        $this->tenantUsers((string) $campaign->tenant_id)
            ->each(function (User $user) use ($campaign, $instance): void {
                if ($this->hasUnreadNotificationForCampaign($user, $campaign->id)) {
                    return;
                }

                $user->notify(new MetaQualityRedNotification(
                    campaignId: $campaign->id,
                    campaignName: $campaign->name,
                    whatsappInstanceId: $instance->id,
                    whatsappInstanceName: $instance->display_name ?: $instance->name,
                ));
            });
    }

    /** @return Collection<int, User> */
    private function tenantUsers(string $tenantId): Collection
    {
        return User::query()
            ->whereHas('tenants', fn ($query) => $query->where('tenants.id', $tenantId))
            ->get();
    }

    private function hasUnreadNotificationForCampaign(User $user, int $campaignId): bool
    {
        return $user->unreadNotifications()
            ->where('type', MetaQualityRedNotification::class)
            ->get()
            ->contains(fn ($notification): bool => (int) ($notification->data['campaign_id'] ?? 0) === $campaignId);
    }

    private function markCampaignNotificationsRead(Campaign $campaign): void
    {
        $this->tenantUsers((string) $campaign->tenant_id)
            ->each(function (User $user) use ($campaign): void {
                $user->unreadNotifications()
                    ->where('type', MetaQualityRedNotification::class)
                    ->get()
                    ->filter(fn ($notification): bool => (int) ($notification->data['campaign_id'] ?? 0) === $campaign->id)
                    ->each->markAsRead();
            });
    }
}
