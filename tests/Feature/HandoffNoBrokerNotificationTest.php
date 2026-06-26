<?php

use App\Ai\Tools\EscalarParaHumanoTool;
use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

// ─── Helper (mirrors EscalarParaHumanoToolTest::toolLead) ────────────────────

function handoffLead(array $extra = []): Lead
{
    $tenant = Tenant::create(['name' => 'HandoffTest']);
    $user = User::factory()->create();
    $user->tenants()->detach();
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'is_default' => true,
    ]);

    return Lead::factory()->forAgent($agent)->create(array_merge([
        'tenant_id' => (string) $tenant->id,
        'operational_stage' => Lead::STAGE_AI_QUALIFYING,
        'followup_status' => 'active',
        'status' => 'qualificado',
    ], $extra));
}

// ─── SC3: handoff is in-system; no broker WhatsApp dispatched ─────────────────

test('qualification handoff routes to atendimento queue via status+tag with no broker WhatsApp', function () {
    $lead = handoffLead();

    $tool = new EscalarParaHumanoTool($lead);
    $result = (string) $tool->handle(new Request([
        'motivo' => 'proposta_aceita',
        'resumo' => 'Crédito aprovado, parcela R$ 230/mês',
    ]));

    // ServiceTicket created — this IS the atendimento-queue entry
    $ticket = ServiceTicket::query()->activeEscalation($lead->id)->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->type)->toBe(ServiceTicket::TYPE_ESCALATION);
    expect($ticket->status)->toBe(ServiceTicket::STATUS_OPEN);

    // Lead routed in-system
    $lead->refresh();
    expect($lead->operational_stage)->toBe(Lead::STAGE_HUMAN_PENDING);
    expect($lead->followup_status)->toBe('paused');

    // Tool result must not contain broker-number language
    expect($result)->not->toContain('número');
    expect($result)->not->toContain('celular');
    expect($result)->not->toContain('entrará em contato');
});

// ─── No dependency on escalation_whatsapp_number ─────────────────────────────

test('no agent_configs escalation field is read during handoff', function () {
    $lead = handoffLead();

    // Ensure the agent config has a null/absent escalation_whatsapp_number
    // (escalation not in $fillable; set nothing — column defaults to empty string from factory)
    // Override with null at DB level to be explicit
    if ($lead->agent && $lead->agent->config) {
        DB::table('agent_configs')
            ->where('agent_id', $lead->agent_id)
            ->update(['escalation_whatsapp_number' => null]);
    }

    $tool = new EscalarParaHumanoTool($lead);
    $result = (string) $tool->handle(new Request([
        'motivo' => 'solicitacao_cliente',
        'resumo' => 'Cliente pediu atendimento humano',
    ]));

    // Handoff must succeed even with null escalation field
    $ticket = ServiceTicket::query()->activeEscalation($lead->id)->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->status)->toBe(ServiceTicket::STATUS_OPEN);

    $lead->refresh();
    expect($lead->operational_stage)->toBe(Lead::STAGE_HUMAN_PENDING);
});
