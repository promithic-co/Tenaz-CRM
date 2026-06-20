<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\VoiceCampaignCall;
use App\Services\ContactSyncService;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPostCallWhatsAppJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        public readonly int $voiceCampaignCallId,
    ) {
        $this->onQueue('messages');
    }

    public function handle(WhatsAppService $whatsapp): void
    {
        $call = VoiceCampaignCall::with('voiceCampaign.voiceInstance.whatsappInstance', 'voiceCampaign.voiceInstance.postCallMetaTemplate')->find($this->voiceCampaignCallId);
        $voiceInstance = $call?->voiceCampaign?->voiceInstance;
        $whatsappInstance = $voiceInstance?->whatsappInstance;

        if (! $whatsappInstance) {
            Log::warning('ivr.no_whatsapp_instance', ['call_id' => $this->voiceCampaignCallId]);

            return;
        }

        $normalizedPhone = ltrim($call->phone, '+');

        $lead = Cache::lock("lead_create_{$voiceInstance->tenant_id}_{$normalizedPhone}", 8)
            ->block(5, function () use ($voiceInstance, $normalizedPhone, $whatsappInstance) {
                $attributes = [
                    'agent_id' => $whatsappInstance->agent_id,
                    'modo' => 'receptivo',
                    'evolution_instance' => $whatsappInstance->name,
                ];

                return Lead::firstOrCreate(
                    ['tenant_id' => $voiceInstance->tenant_id, 'whatsapp' => $normalizedPhone],
                    $attributes
                );
            });

        app(ContactSyncService::class)->syncFromLead($lead, Contact::SOURCE_URA);
        $lead->refresh();

        $message = $call->voiceCampaign->post_call_message
            ?? $voiceInstance->post_call_message
            ?? 'Olá! Você demonstrou interesse em nossa oferta. Posso te ajudar aqui pelo WhatsApp!';

        $template = $voiceInstance->postCallMetaTemplate;

        if (! $template || $template->status !== 'APPROVED') {
            Log::warning('ivr.meta_template_unavailable', ['voice_instance_id' => $voiceInstance->id, 'call_id' => $call->id]);

            return;
        }

        // Per-send idempotency claim (mirrors ProcessLeadFollowUpJob). tries=3 + a lost Meta
        // response would otherwise re-POST the template on retry, double-messaging the lead.
        $sendClaimKey = "postcall_send:{$this->voiceCampaignCallId}";
        if (! Cache::add($sendClaimKey, 1, now()->addMinutes(10))) {
            Log::info('ivr.whatsapp_send_already_claimed', [
                'call_id' => $this->voiceCampaignCallId,
                'phone' => $normalizedPhone,
            ]);

            return;
        }

        $whatsapp->sendTemplateViaInstance(
            $whatsappInstance,
            $normalizedPhone,
            (string) ($template->meta_template_name ?? $template->name),
            (string) ($template->language ?? 'pt_BR'),
        );

        Log::info('ivr.whatsapp_sent', [
            'phone' => $normalizedPhone,
            'call_id' => $call->id,
            'lead_id' => $lead->id,
        ]);

        app(DashboardMetricsService::class)->dispatchUpdate((string) $lead->tenant_id);
    }

    public function failed(Throwable $e): void
    {
        Log::error('ivr.whatsapp_failed', [
            'call_id' => $this->voiceCampaignCallId,
            'error' => $e->getMessage(),
        ]);
    }
}
