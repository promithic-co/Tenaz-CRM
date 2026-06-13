<?php

namespace App\Actions;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Services\AgentInteractionEventService;
use App\Services\ContactSyncService;
use Illuminate\Validation\ValidationException;

/**
 * Create or restore a lead for a tenant + WhatsApp pair.
 *
 * Returns a discriminated result so the caller can keep its three distinct
 * redirects intact:
 *   - `existed` true  → an active lead already exists (no write performed)
 *   - `existed` false → a fresh row was created OR a soft-deleted row restored;
 *                       `restored` distinguishes the two for event payloads
 */
class CreateOrRestoreLeadAction
{
    public function __construct(
        private readonly AgentInteractionEventService $events,
        private readonly ContactSyncService $contactSync,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated store payload (nome, whatsapp, cpf?, evolution_instance)
     * @return array{lead: Lead, existed: bool, restored: bool}
     *
     * @throws ValidationException when the instance name is not a tenant-owned instance
     */
    public function execute(string $tenantId, int $userId, array $data): array
    {
        $instance = WhatsappInstance::query()
            ->where('tenant_id', $tenantId)
            ->where('name', $data['evolution_instance'])
            ->first();

        if (! $instance) {
            throw ValidationException::withMessages([
                'evolution_instance' => 'Instância de WhatsApp inválida.',
            ]);
        }

        // Active lead check uses tenant + WhatsApp as the practical duplicate key,
        // ignoring the DB-level per-agent unique index — operators expect "one phone =
        // one conversation" inside a tenant.
        $existing = Lead::query()
            ->withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('whatsapp', $data['whatsapp'])
            ->orderByRaw('deleted_at IS NULL DESC')
            ->orderByDesc('id')
            ->first();

        if ($existing && $existing->deleted_at === null) {
            return ['lead' => $existing, 'existed' => true, 'restored' => false];
        }

        $payload = [
            'tenant_id' => $tenantId,
            'agent_id' => $instance->agent_id,
            'evolution_instance' => $instance->name,
            'whatsapp_instance_id' => $instance->id,
            'nome' => $data['nome'],
            'whatsapp' => $data['whatsapp'],
            'cpf' => $data['cpf'] ?? null,
            'status' => 'novo',
            'followup_status' => 'inactive',
            'followup_count' => 0,
        ];

        if ($existing) {
            $existing->restore();
            $existing->update($payload);
            $lead = $existing->fresh();
        } else {
            $lead = Lead::create($payload);
        }

        $this->contactSync->syncFromLead($lead, Contact::SOURCE_MANUAL);
        $lead->refresh();

        $this->events->recordForLead(
            interactionId: $this->events->newInteractionId(),
            lead: $lead,
            eventType: 'lead_created_manual',
            eventSource: 'lead_management_controller',
            payload: [
                'user_id' => $userId,
                'restored' => (bool) $existing,
            ],
        );

        return ['lead' => $lead, 'existed' => false, 'restored' => (bool) $existing];
    }
}
