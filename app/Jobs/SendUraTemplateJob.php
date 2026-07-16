<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\UraApiKey;
use App\Services\AgentInteractionEventService;
use App\Services\ContactSyncService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendUraTemplateJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * @param  list<string>  $variables  Positional template variables: ["João", "INSS"] → {{1}}, {{2}}
     */
    public function __construct(
        public readonly int $uraApiKeyId,
        public readonly string $phone,
        public readonly ?string $name = null,
        public readonly array $variables = [],
    ) {
        $this->onQueue('messages');
    }

    public function handle(WhatsAppService $whatsapp): void
    {
        $interactionEvents = app(AgentInteractionEventService::class);
        $interactionId = $interactionEvents->newInteractionId();
        $apiKey = UraApiKey::with(['agent.whatsappInstance', 'whatsappTemplate'])->find($this->uraApiKeyId);

        if (! $apiKey) {
            Log::warning('ura.trigger.api_key_not_found', [
                'interaction_id' => $interactionId,
                'ura_api_key_id' => $this->uraApiKeyId,
            ]);

            return;
        }

        $agent = $apiKey->agent;
        $template = $apiKey->whatsappTemplate;
        $whatsappInstance = $agent?->whatsappInstance;
        $tenantId = (string) $apiKey->tenant_id;

        if (! $agent || (string) $agent->tenant_id !== $tenantId) {
            Log::warning('ura.trigger.agent_tenant_mismatch', [
                'interaction_id' => $interactionId,
                'ura_api_key_id' => $this->uraApiKeyId,
            ]);

            return;
        }

        if ($template && (string) $template->tenant_id !== $tenantId) {
            Log::warning('ura.trigger.template_tenant_mismatch', [
                'interaction_id' => $interactionId,
                'ura_api_key_id' => $this->uraApiKeyId,
                'template_id' => $template->id,
            ]);

            return;
        }

        if ($whatsappInstance && (string) $whatsappInstance->tenant_id !== $tenantId) {
            Log::warning('ura.trigger.whatsapp_instance_tenant_mismatch', [
                'interaction_id' => $interactionId,
                'ura_api_key_id' => $this->uraApiKeyId,
                'whatsapp_instance_id' => $whatsappInstance->id,
            ]);

            return;
        }

        if (! $whatsappInstance) {
            Log::warning('ura.trigger.no_whatsapp_instance', [
                'interaction_id' => $interactionId,
                'ura_api_key_id' => $this->uraApiKeyId,
            ]);

            return;
        }

        $normalizedPhone = ltrim($this->phone, '+');

        $lead = Cache::lock("lead_create_{$apiKey->tenant_id}_{$normalizedPhone}", 8)
            ->block(5, function () use ($apiKey, $normalizedPhone, $whatsappInstance) {
                $attributes = [
                    'nome' => $this->name,
                    'agent_id' => $apiKey->agent_id,
                    'modo' => 'receptivo',
                    'evolution_instance' => $whatsappInstance->name,
                ];

                return Lead::firstOrCreate(
                    ['tenant_id' => $apiKey->tenant_id, 'whatsapp' => $normalizedPhone],
                    $attributes
                );
            });

        app(ContactSyncService::class)->syncFromLead($lead, Contact::SOURCE_URA);
        $lead->refresh();

        $interactionEvents->recordForLead(
            interactionId: $interactionId,
            lead: $lead,
            eventType: 'ura_inbound_received',
            eventSource: 'send_ura_template_job',
            payload: [
                'ura_api_key_id' => $this->uraApiKeyId,
                'whatsapp_instance_id' => $whatsappInstance->id,
                'template_id' => $apiKey->whatsappTemplate?->id,
                'phone' => $normalizedPhone,
            ],
        );

        if (! $template || ! $template->isApproved()) {
            Log::warning('ura.trigger.meta_template_unavailable', [
                'interaction_id' => $interactionId,
                'ura_api_key_id' => $apiKey->id,
            ]);

            $interactionEvents->recordForLead(
                interactionId: $interactionId,
                lead: $lead,
                eventType: 'outbound_failed',
                eventSource: 'send_ura_template_job',
                payload: ['reason' => 'meta_template_unavailable', 'ura_api_key_id' => $apiKey->id],
                severity: 'warning',
            );

            return;
        }

        $components = $this->buildMetaComponents($template->variables_count);

        // Per-send idempotency claim (mirrors ProcessLeadFollowUpJob). tries=3 + a lost Meta
        // response would otherwise re-POST the template on retry, double-messaging the lead.
        $sendClaimKey = "ura_template_send:{$this->uraApiKeyId}:{$normalizedPhone}:".md5(serialize($this->variables));
        if (! Cache::add($sendClaimKey, 1, now()->addMinutes(10))) {
            Log::info('ura.trigger.send_already_claimed', [
                'interaction_id' => $interactionId,
                'ura_api_key_id' => $this->uraApiKeyId,
                'phone' => $normalizedPhone,
            ]);

            return;
        }

        $whatsapp->sendTemplateViaInstance(
            $whatsappInstance,
            $normalizedPhone,
            (string) ($template->meta_template_name ?? $template->name),
            (string) ($template->language ?? 'pt_BR'),
            $components,
        );

        Log::info('ura.trigger.sent', [
            'interaction_id' => $interactionId,
            'phone' => $normalizedPhone,
            'lead_id' => $lead->id,
            'ura_api_key_id' => $this->uraApiKeyId,
            'template_id' => $template?->id,
        ]);

        $interactionEvents->recordForLead(
            interactionId: $interactionId,
            lead: $lead,
            eventType: 'outbound_sent',
            eventSource: 'send_ura_template_job',
            payload: [
                'source' => 'ura',
                'ura_api_key_id' => $this->uraApiKeyId,
                'whatsapp_instance_id' => $whatsappInstance->id,
                'template_id' => $template?->id,
                'provider' => $whatsappInstance->provider->value,
            ],
        );
    }

    /**
     * Build Meta components array for body variable substitution.
     * Meta spec: components[{type:"body", parameters:[{type:"text", text:"..."}]}]
     *
     * @return list<array<string, mixed>>
     */
    private function buildMetaComponents(int $variablesCount): array
    {
        if ($variablesCount <= 0 || empty($this->variables)) {
            return [];
        }

        $parameters = [];
        for ($i = 0; $i < $variablesCount; $i++) {
            $parameters[] = [
                'type' => 'text',
                'text' => (string) ($this->variables[$i] ?? ''),
            ];
        }

        return [['type' => 'body', 'parameters' => $parameters]];
    }

    private function interpolateVariables(string $body): string
    {
        foreach ($this->variables as $index => $value) {
            $placeholder = '{{'.($index + 1).'}}';
            $body = str_replace($placeholder, (string) $value, $body);
        }

        return $body;
    }

    public function failed(Throwable $e): void
    {
        Log::error('ura.trigger.failed', [
            'ura_api_key_id' => $this->uraApiKeyId,
            'phone' => $this->phone,
            'error' => $e->getMessage(),
        ]);
    }
}
