<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MetaQualityRedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $campaignId,
        public readonly string $campaignName,
        public readonly int $whatsappInstanceId,
        public readonly string $whatsappInstanceName,
        public readonly string $qualityRating = 'RED',
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'meta_quality_red',
            'severity' => 'critical',
            'title' => 'Risco de restricao/banimento',
            'body' => "A qualidade Meta da instancia {$this->whatsappInstanceName} ficou RED. A campanha {$this->campaignName} foi pausada para evitar restricao ou banimento.",
            'campaign_id' => $this->campaignId,
            'campaign_name' => $this->campaignName,
            'whatsapp_instance_id' => $this->whatsappInstanceId,
            'whatsapp_instance_name' => $this->whatsappInstanceName,
            'quality_rating' => $this->qualityRating,
            'action_url' => route('campanhas.show', $this->campaignId),
        ];
    }
}
