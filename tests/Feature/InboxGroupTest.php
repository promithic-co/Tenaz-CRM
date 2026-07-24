<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The inbox triage tabs (fila / minhas / ia / todas) and the counters behind
 * them. The tabs must stay mutually exclusive, must never leak a lead the actor
 * cannot open, and must not cost one query per row.
 *
 * @return array{Tenant, User, User}
 */
function groupTenant(): array
{
    $tenant = Tenant::create(['name' => 'GroupTenant']);

    $owner = User::factory()->create();
    $owner->tenants()->detach();
    $owner->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

    $seller = User::factory()->create();
    $seller->tenants()->detach();
    $seller->tenants()->attach($tenant->id, ['role' => TenantRole::User->value]);

    return [$tenant, $owner, $seller];
}

function escalationTicket(Lead $lead, string $status, ?int $assignedUserId = null): ServiceTicket
{
    return ServiceTicket::create([
        'tenant_id' => (string) $lead->tenant_id,
        'lead_id' => $lead->id,
        'assigned_user_id' => $assignedUserId,
        'type' => ServiceTicket::TYPE_ESCALATION,
        'status' => $status,
        'priority' => ServiceTicket::PRIORITY_NORMAL,
        'sla_due_at' => now()->addHour(),
    ]);
}

/**
 * @return list<string>
 */
function inboxNames(User $actor, array $query = []): array
{
    $response = test()->actingAs($actor)->get(route('conversas.index', $query))->assertOk();

    return array_column($response->viewData('page')['props']['leads']['data'], 'nome');
}

test('each tab shows only the leads it owns and the tabs never overlap', function () {
    [$tenant, $owner] = groupTenant();
    $tenantId = (string) $tenant->id;

    $queued = Lead::factory()->create([
        'tenant_id' => $tenantId,
        'nome' => 'Na Fila',
        'assigned_user_id' => null,
    ]);
    escalationTicket($queued, ServiceTicket::STATUS_OPEN);

    Lead::factory()->create([
        'tenant_id' => $tenantId,
        'nome' => 'Minha',
        'assigned_user_id' => $owner->id,
    ]);

    Lead::factory()->create([
        'tenant_id' => $tenantId,
        'nome' => 'Com IA',
        'assigned_user_id' => null,
    ]);

    expect(inboxNames($owner, ['group' => 'fila']))->toBe(['Na Fila'])
        ->and(inboxNames($owner, ['group' => 'minhas']))->toBe(['Minha'])
        ->and(inboxNames($owner, ['group' => 'ia']))->toBe(['Com IA'])
        ->and(inboxNames($owner))->toHaveCount(3);
});

test('a claimed escalation leaves the queue without falling into the ia tab', function () {
    [$tenant, $owner] = groupTenant();

    $lead = Lead::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'nome' => 'Em Atendimento',
        'assigned_user_id' => null,
    ]);
    escalationTicket($lead, ServiceTicket::STATUS_ASSIGNED, $owner->id);

    expect(inboxNames($owner, ['group' => 'fila']))->toBe([])
        ->and(inboxNames($owner, ['group' => 'ia']))->toBe([])
        ->and(inboxNames($owner))->toBe(['Em Atendimento']);
});

test('a resolved escalation returns the lead to the ia tab', function () {
    [$tenant, $owner] = groupTenant();

    $lead = Lead::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'nome' => 'Resolvido',
        'assigned_user_id' => null,
    ]);
    escalationTicket($lead, ServiceTicket::STATUS_RESOLVED);

    expect(inboxNames($owner, ['group' => 'ia']))->toBe(['Resolvido']);
});

test('the counters match the rows each tab renders', function () {
    [$tenant, $owner] = groupTenant();
    $tenantId = (string) $tenant->id;

    $queued = Lead::factory()->create(['tenant_id' => $tenantId, 'assigned_user_id' => null]);
    escalationTicket($queued, ServiceTicket::STATUS_OPEN);

    Lead::factory()->count(2)->create(['tenant_id' => $tenantId, 'assigned_user_id' => $owner->id]);
    Lead::factory()->count(3)->create(['tenant_id' => $tenantId, 'assigned_user_id' => null]);

    test()->actingAs($owner)
        ->get(route('conversas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('group_counts.fila', 1)
            ->where('group_counts.minhas', 2)
            ->where('group_counts.ia', 3)
        );
});

test('the counters honour the active filters so a badge never promises rows the tab lacks', function () {
    [$tenant, $owner] = groupTenant();
    $tenantId = (string) $tenant->id;

    Lead::factory()->create([
        'tenant_id' => $tenantId,
        'assigned_user_id' => $owner->id,
        'nome' => 'Ana Qualificada',
        'status' => 'qualificado',
    ]);
    Lead::factory()->create([
        'tenant_id' => $tenantId,
        'assigned_user_id' => $owner->id,
        'nome' => 'Bruno Novo',
        'status' => 'novo',
    ]);

    test()->actingAs($owner)
        ->get(route('conversas.index', ['group' => 'minhas', 'status' => 'qualificado']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('group_counts.minhas', 1)
            ->where('leads.total', 1)
        );
});

test('the counters never count a lead the seller cannot open', function () {
    [$tenant, $owner, $seller] = groupTenant();
    $tenantId = (string) $tenant->id;

    $ownerAgent = Agent::factory()->create(['user_id' => $owner->id, 'tenant_id' => $tenantId]);

    $hidden = Lead::factory()->forAgent($ownerAgent)->create(['assigned_user_id' => null]);
    escalationTicket($hidden, ServiceTicket::STATUS_OPEN);

    $shared = Lead::factory()->create([
        'tenant_id' => $tenantId,
        'agent_id' => null,
        'assigned_user_id' => null,
    ]);
    escalationTicket($shared, ServiceTicket::STATUS_OPEN);

    test()->actingAs($owner)
        ->get(route('conversas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('group_counts.fila', 2));

    test()->actingAs($seller)
        ->get(route('conversas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('group_counts.fila', 1));
});

test('the queue is ordered oldest first so the longest wait is picked up next', function () {
    [$tenant, $owner] = groupTenant();
    $tenantId = (string) $tenant->id;

    foreach (['Esperou Mais' => 5, 'Esperou Menos' => 1] as $name => $hoursAgo) {
        $lead = Lead::factory()->create([
            'tenant_id' => $tenantId,
            'nome' => $name,
            'assigned_user_id' => null,
            'last_interaction_at' => now()->subHours($hoursAgo),
        ]);
        escalationTicket($lead, ServiceTicket::STATUS_OPEN);
    }

    expect(inboxNames($owner, ['group' => 'fila']))->toBe(['Esperou Mais', 'Esperou Menos']);
});

test('the row carries the last timeline message and flags the ones waiting on us', function () {
    [$tenant, $owner] = groupTenant();

    $lead = Lead::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'assigned_user_id' => $owner->id,
    ]);

    timelineMessage($lead, 'outbound', 'Bom dia, tudo bem?', now()->subMinutes(10));
    timelineMessage($lead, 'inbound', 'Quero simular o emprestimo', now()->subMinutes(2));

    test()->actingAs($owner)
        ->get(route('conversas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leads.data.0.last_message_body', 'Quero simular o emprestimo')
            ->where('leads.data.0.last_message_direction', 'inbound')
            ->where('leads.data.0.awaiting_reply', true)
        );
});

test('the preview follows created_at, not the insertion order the backfill produces', function () {
    [$tenant, $owner] = groupTenant();

    $lead = Lead::factory()->create([
        'tenant_id' => (string) $tenant->id,
        'assigned_user_id' => $owner->id,
    ]);

    timelineMessage($lead, 'inbound', 'Mensagem de hoje', now()->subMinute());
    // The campaign backfill inserts historic rows with fresh ids.
    timelineMessage($lead, 'outbound', 'Template de tres meses atras', now()->subMonths(3));

    test()->actingAs($owner)
        ->get(route('conversas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leads.data.0.last_message_body', 'Mensagem de hoje')
        );
});

test('listing the inbox does not cost a query per lead', function () {
    [$tenant, $owner] = groupTenant();
    $tenantId = (string) $tenant->id;

    $measure = function (int $leadCount) use ($tenantId, $owner): int {
        Lead::query()->where('tenant_id', $tenantId)->forceDelete();

        for ($i = 0; $i < $leadCount; $i++) {
            $lead = Lead::factory()->create([
                'tenant_id' => $tenantId,
                'assigned_user_id' => $owner->id,
            ]);
            timelineMessage($lead, 'inbound', "Mensagem {$i}", now()->subMinutes($i));
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->actingAs($owner)->get(route('conversas.index'))->assertOk();
        $count = count(DB::getRawQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    expect($measure(10))->toBe($measure(2));
});

function timelineMessage(Lead $lead, string $direction, string $body, $createdAt): ConversationTimelineMessage
{
    $message = ConversationTimelineMessage::create([
        'tenant_id' => (string) $lead->tenant_id,
        'lead_id' => $lead->id,
        'direction' => $direction,
        'sender_type' => $direction === 'inbound' ? 'customer' : 'operator',
        'channel' => 'whatsapp',
        'body' => $body,
        'status' => 'sent',
        'source' => 'test',
    ]);

    // Timestamps are guarded, so the backdating has to happen after the insert.
    $message->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

    return $message;
}
