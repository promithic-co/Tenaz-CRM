<?php

use App\Ai\Tools\EscalarParaHumanoTool;
use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Ai\Tools\Request;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function toolLead(array $extra = []): Lead
{
    $tenant = Tenant::create(['name' => 'ToolTest']);
    $user = User::factory()->create();
    $user->tenants()->detach();
    $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $agent = Agent::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_default' => true]);

    return Lead::factory()->forAgent($agent)->create(array_merge([
        'tenant_id' => (string) $tenant->id,
        'operational_stage' => Lead::STAGE_AI_QUALIFYING,
        'followup_status' => 'active',
        'status' => 'qualificado',
    ], $extra));
}

function invokeTool(Lead $lead, array $input = []): string
{
    $tool = new EscalarParaHumanoTool($lead);
    $request = new Request(array_merge([
        'motivo' => 'proposta_aceita',
        'resumo' => 'Produto Crédito Novo aprovado, parcela R$ 230/mês',
    ], $input));

    return (string) $tool->handle($request);
}

test('tool description mentions fila de atendimento not external number', function () {
    $lead = toolLead();
    $tool = new EscalarParaHumanoTool($lead);
    $description = (string) $tool->description();

    expect($description)->toContain('fila');
    expect($description)->not->toContain('número');
    expect($description)->not->toContain('celular');
    expect($description)->not->toContain('whatsapp_number');
    expect($description)->not->toContain('especialista entrará em contato');
});

test('tool creates active escalation ticket', function () {
    $lead = toolLead();

    invokeTool($lead);

    $ticket = ServiceTicket::query()->activeEscalation($lead->id)->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->type)->toBe(ServiceTicket::TYPE_ESCALATION);
    expect($ticket->status)->toBe(ServiceTicket::STATUS_OPEN);
});

test('tool saves reason and summary from input', function () {
    $lead = toolLead();

    invokeTool($lead, [
        'motivo' => 'proposta_aceita',
        'resumo' => 'Crédito Novo INSS, R$ 12500, parcela R$ 230',
        'produto_escolhido' => 'Crédito Novo',
        'valor_total' => '12500.00',
    ]);

    $ticket = ServiceTicket::query()->activeEscalation($lead->id)->first();
    expect($ticket->reason)->toBe('proposta_aceita');
    expect($ticket->chosen_product)->toBe('Crédito Novo');
    expect((string) $ticket->total_value)->toBe('12500');
});

test('tool sets lead to human_pending with AI paused', function () {
    $lead = toolLead();

    invokeTool($lead);

    $lead->refresh();
    expect($lead->operational_stage)->toBe(Lead::STAGE_HUMAN_PENDING);
    expect($lead->ai_paused_until)->not->toBeNull();
    expect($lead->ai_paused_until->isFuture())->toBeTrue();
    expect($lead->ai_paused_reason)->toBe('handoff_requested_by_ai');
    expect($lead->followup_status)->toBe('paused');
});

test('tool is idempotent: second call returns already_done when handoff active', function () {
    $lead = toolLead();

    invokeTool($lead);
    $result = invokeTool($lead);

    $decoded = json_decode($result, true);
    expect($decoded['status'])->toBe('already_done');
    expect(ServiceTicket::query()->activeEscalation($lead->id)->count())->toBe(1);
});

test('tool is idempotent even when Lead.status is escalado', function () {
    $lead = toolLead(['status' => 'escalado']);

    // Create an active ticket to simulate already-transferred state.
    ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    $result = invokeTool($lead);

    $decoded = json_decode($result, true);
    expect($decoded['status'])->toBe('already_done');
    expect(ServiceTicket::query()->activeEscalation($lead->id)->count())->toBe(1);
});

test('tool can transfer even when Lead.status is escalado but no active ticket exists', function () {
    $lead = toolLead(['status' => 'escalado']);

    $result = invokeTool($lead);

    $decoded = json_decode($result, true);
    expect($decoded['status'])->toBe('success');
    expect(ServiceTicket::query()->activeEscalation($lead->id)->count())->toBe(1);
});

test('tool does not affect no_credit tickets', function () {
    $lead = toolLead();

    ServiceTicket::create([
        'tenant_id' => $lead->tenant_id,
        'lead_id' => $lead->id,
        'type' => ServiceTicket::TYPE_NO_CREDIT,
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    invokeTool($lead);

    expect(ServiceTicket::where('lead_id', $lead->id)->where('type', ServiceTicket::TYPE_NO_CREDIT)->count())->toBe(1);
    expect(ServiceTicket::query()->activeEscalation($lead->id)->count())->toBe(1);
});

test('tool result mentions fila interna not external number', function () {
    $lead = toolLead();

    $result = invokeTool($lead);

    expect($result)->not->toContain('número');
    expect($result)->not->toContain('celular');
    expect($result)->not->toContain('entrará em contato');
});

test('tool normalizes motivo with extra text into summary', function () {
    $lead = toolLead();

    invokeTool($lead, [
        'motivo' => 'proposta_aceita - cliente confirmou o Crédito Novo',
        'resumo' => 'Documentação completa',
    ]);

    $ticket = ServiceTicket::query()->activeEscalation($lead->id)->first();
    expect($ticket->reason)->toBe('proposta_aceita');
    expect($ticket->summary)->toContain('cliente confirmou');
});
