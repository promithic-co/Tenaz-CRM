<?php

use App\Jobs\ProcessIncomingWhatsAppMessageJob;
use App\Jobs\ProcessWhatsappOutboxMessageJob;
use App\Models\Agent;
use App\Models\AgentInteractionEvent;
use App\Models\FollowupMessage;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappOutboxMessage;
use App\Services\LaboratoryMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** @return array{0: User, 1: Tenant, 2: Agent} */
function tenantScopedMetricsUser(): array
{
    $user = User::factory()->superAdmin()->create();
    $tenant = $user->tenants()->first();
    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);

    return [$user, $tenant, $agent];
}

it('excludes another tenants failed follow-up messages from failed_today', function () {
    [$userA, $tenantA, $agentA] = tenantScopedMetricsUser();
    $leadA = Lead::factory()->create([
        'agent_id' => $agentA->id,
        'tenant_id' => (string) $tenantA->id,
    ]);
    FollowupMessage::factory()->create([
        'lead_id' => $leadA->id,
        'tenant_id' => (string) $tenantA->id,
        'status' => 'failed',
        'sent_at' => now(),
    ]);

    [$userB, $tenantB, $agentB] = tenantScopedMetricsUser();
    $leadB = Lead::factory()->create([
        'agent_id' => $agentB->id,
        'tenant_id' => (string) $tenantB->id,
    ]);
    FollowupMessage::factory()->create([
        'lead_id' => $leadB->id,
        'tenant_id' => (string) $tenantB->id,
        'status' => 'failed',
        'sent_at' => now(),
    ]);

    $stats = app(LaboratoryMetricsService::class)->followupStats((string) $tenantA->id);

    expect($stats['failed_today'])->toBe(1);
});

it('does not count global failed_jobs rows toward the tenant-scoped metric', function () {
    [$userA, $tenantA, $agentA] = tenantScopedMetricsUser();
    $leadA = Lead::factory()->create([
        'agent_id' => $agentA->id,
        'tenant_id' => (string) $tenantA->id,
    ]);
    FollowupMessage::factory()->create([
        'lead_id' => $leadA->id,
        'tenant_id' => (string) $tenantA->id,
        'status' => 'failed',
        'sent_at' => now(),
    ]);

    // Regression guard: this row is what the OLD failed_today implementation read
    // (DB::table('failed_jobs')->where('payload', 'like', '%ProcessLeadFollowUpJob%')).
    // It carries no tenant_id at all — the metric must not be inflated by it.
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'followups',
        'payload' => 'unrelated serialized payload containing ProcessLeadFollowUpJob',
        'exception' => 'RuntimeException: boom',
        'failed_at' => now()->toDateTimeString(),
    ]);

    $stats = app(LaboratoryMetricsService::class)->followupStats((string) $tenantA->id);

    expect($stats['failed_today'])->toBe(1);
});

it('records tenant-attributable terminal evidence when the inbound job permanently fails', function () {
    [$user, $tenant, $agent] = tenantScopedMetricsUser();
    $phone = '5511999998888';

    $job = new ProcessIncomingWhatsAppMessageJob(
        phone: $phone,
        name: 'Fulano de Tal',
        tenantId: (string) $tenant->id,
        agentId: $agent->id,
        instanceName: 'instance-test',
        aggregatedMessage: 'Mensagem do cliente',
    );

    $job->failed(new RuntimeException('boom'));

    $event = AgentInteractionEvent::query()
        ->where('event_type', 'inbound_processing_permanently_failed')
        ->first();

    expect($event)->not->toBeNull()
        ->and((string) $event->tenant_id)->toBe((string) $tenant->id);

    expect(json_encode($event->payload_json))->not->toContain($phone);
});

it('records tenant-attributable terminal evidence when the outbox job permanently fails without changing status', function () {
    [$user, $tenant, $agent] = tenantScopedMetricsUser();
    $lead = Lead::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => (string) $tenant->id,
    ]);

    $outbox = WhatsappOutboxMessage::create([
        'tenant_id' => (string) $tenant->id,
        'lead_id' => $lead->id,
        'channel' => 'whatsapp',
        'provider' => 'meta_cloud',
        'payload_json' => ['type' => 'text', 'phone' => $lead->whatsapp, 'text' => 'Oi'],
        'status' => 'failed',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $job = new ProcessWhatsappOutboxMessageJob($outbox->id);
    $job->failed(new RuntimeException('boom'));

    $event = AgentInteractionEvent::query()
        ->where('event_type', 'outbound_permanently_failed')
        ->first();

    expect($event)->not->toBeNull()
        ->and((string) $event->tenant_id)->toBe((string) $tenant->id);

    expect($outbox->fresh()->status)->toBe('failed');
});
