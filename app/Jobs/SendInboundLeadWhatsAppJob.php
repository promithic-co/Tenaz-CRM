<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\VoiceInstance;
use App\Services\AgentInteractionEventService;
use App\Services\ContactSyncService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendInboundLeadWhatsAppJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        public readonly int $voiceInstanceId,
        public readonly string $phone,
        public readonly ?string $name = null,
    ) {
        $this->onQueue('messages');
    }

    public function handle(WhatsAppService $whatsapp): void
    {
        $interactionEvents = app(AgentInteractionEventService::class);
        $interactionId = $interactionEvents->newInteractionId();
        $voiceInstance = VoiceInstance::with(['whatsappInstance', 'postCallMetaTemplate'])->find($this->voiceInstanceId);
        $whatsappInstance = $voiceInstance?->whatsappInstance;

        if (! $whatsappInstance) {
            Log::warning('ura.no_whatsapp_instance', [
                'interaction_id' => $interactionId,
                'voice_instance_id' => $this->voiceInstanceId,
            ]);

            return;
        }

        $normalizedPhone = ltrim($this->phone, '+');

        $lead = Cache::lock("lead_create_{$voiceInstance->tenant_id}_{$normalizedPhone}", 8)
            ->block(5, function () use ($voiceInstance, $normalizedPhone, $whatsappInstance) {
                $attributes = [
                    'nome' => $this->name,
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

        $interactionEvents->recordForLead(
            interactionId: $interactionId,
            lead: $lead,
            eventType: 'ura_inbound_received',
            eventSource: 'send_inbound_lead_whatsapp_job',
            payload: [
                'voice_instance_id' => $this->voiceInstanceId,
                'whatsapp_instance_id' => $whatsappInstance->id,
                'phone' => $normalizedPhone,
            ],
        );

        $message = $voiceInstance->post_call_message
            ?? 'Olá! Você demonstrou interesse em nossa oferta. Posso te ajudar aqui pelo WhatsApp!';

        $template = $voiceInstance->postCallMetaTemplate;

        if (! $template || $template->status !== 'APPROVED') {
            Log::warning('ura.meta_template_unavailable', [
                'interaction_id' => $interactionId,
                'voice_instance_id' => $voiceInstance->id,
            ]);

            $interactionEvents->recordForLead(
                interactionId: $interactionId,
                lead: $lead,
                eventType: 'outbound_failed',
                eventSource: 'send_inbound_lead_whatsapp_job',
                payload: ['reason' => 'meta_template_unavailable', 'voice_instance_id' => $voiceInstance->id],
                severity: 'warning',
            );

            return;
        }

        $whatsapp->sendTemplateViaInstance(
            $whatsappInstance,
            $normalizedPhone,
            (string) ($template->meta_template_name ?? $template->name),
            (string) ($template->language ?? 'pt_BR'),
        );

        Log::info('ura.inbound_whatsapp_sent', [
            'interaction_id' => $interactionId,
            'phone' => $normalizedPhone,
            'lead_id' => $lead->id,
            'voice_instance_id' => $this->voiceInstanceId,
        ]);

        $interactionEvents->recordForLead(
            interactionId: $interactionId,
            lead: $lead,
            eventType: 'outbound_sent',
            eventSource: 'send_inbound_lead_whatsapp_job',
            payload: [
                'source' => 'ura',
                'voice_instance_id' => $this->voiceInstanceId,
                'whatsapp_instance_id' => $whatsappInstance->id,
                'provider' => $whatsappInstance->provider->value,
            ],
        );
    }
}
