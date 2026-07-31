<?php

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\AppSetting;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ─── User tenantId ───────────────────────────────────────────────────────────

test('user tenantId equals string of user id', function () {
    $user = User::factory()->create();

    expect($user->tenantId)->toBe((string) $user->id);
});

test('user tenantId resolves the first tenant with a single pivot query per instance', function () {
    $user = User::factory()->create();

    DB::enableQueryLog();
    DB::flushQueryLog();

    $resolved = [$user->tenantId, $user->tenantId, $user->tenantId];

    $pivotQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'tenant_user'));
    DB::disableQueryLog();

    expect($resolved)->each->toBe((string) $user->id)
        ->and($pivotQueries)->toHaveCount(1);
});

test('user tenantId uses an already eager-loaded tenants relation', function () {
    $user = User::factory()->create();
    $user->load('tenants');

    DB::enableQueryLog();
    DB::flushQueryLog();

    $tenantId = $user->tenantId;

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($tenantId)->toBe((string) $user->id)
        ->and($queries)->toBeEmpty();
});

test('user tenantId resolves without a request session (queue and console context)', function () {
    $user = User::factory()->create();

    // Queue workers and console commands run against a request that never had a
    // session store attached, so the accessor must go straight to the fallback.
    app()->instance('request', Request::create('/', 'GET'));

    expect(request()->hasSession())->toBeFalse()
        ->and($user->tenantId)->toBe((string) $user->id);
});

test('user tenantId follows the active tenant switched mid-request', function () {
    $user = User::factory()->create();
    $otherTenant = Tenant::create(['name' => 'Org B']);

    // Attach a session store to the request the way StartSession does in HTTP.
    request()->setLaravelSession(app('session.store'));

    // Resolve once so the fallback is memoized before the switch happens.
    expect($user->tenantId)->toBe((string) $user->id);

    request()->session()->put('active_tenant_id', (string) $otherTenant->id);

    expect($user->tenantId)->toBe((string) $otherTenant->id);

    request()->session()->forget('active_tenant_id');

    expect($user->tenantId)->toBe((string) $user->id);
});

// ─── User roleFor ────────────────────────────────────────────────────────────

test('the first tenant role is seeded by the tenant id lookup', function () {
    $user = User::factory()->create();

    DB::enableQueryLog();
    DB::flushQueryLog();

    $tenantId = $user->tenantId;
    $role = $user->currentRole();

    $pivotQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'tenant_user'));
    DB::disableQueryLog();

    // The tenant lookup already selects the pivot row, so the role must come
    // out of it rather than costing a second select.
    expect($tenantId)->toBe((string) $user->id)
        ->and($role)->toBe(TenantRole::Owner)
        ->and($pivotQueries)->toHaveCount(1);
});

test('roleFor resolves each tenant with a single query', function () {
    $user = User::factory()->create();
    $secondTenant = Tenant::create(['name' => 'Org B']);
    $user->tenants()->attach($secondTenant->id, ['role' => TenantRole::Administrator->value]);

    $firstTenantId = $user->tenantId;
    $user->forgetTenantMemo();

    DB::enableQueryLog();
    DB::flushQueryLog();

    $roles = [
        $user->roleFor($firstTenantId),
        $user->roleFor($firstTenantId),
        $user->roleFor((string) $secondTenant->id),
        $user->roleFor((string) $secondTenant->id),
    ];

    $pivotQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'tenant_user'));
    DB::disableQueryLog();

    expect($roles)->toBe([
        TenantRole::Owner,
        TenantRole::Owner,
        TenantRole::Administrator,
        TenantRole::Administrator,
    ])->and($pivotQueries)->toHaveCount(2);
});

test('roleFor caches a non-membership so repeated permission checks cost one query', function () {
    $user = User::factory()->create();
    $foreignTenant = Tenant::create(['name' => 'Org B']);

    DB::enableQueryLog();
    DB::flushQueryLog();

    // What a super-admin acting as a company they do not belong to hits on
    // every isOwnerOrAdmin()/isRestrictedUser() check across a request.
    $roles = [
        $user->roleFor((string) $foreignTenant->id),
        $user->roleFor((string) $foreignTenant->id),
        $user->roleFor((string) $foreignTenant->id),
    ];

    $pivotQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'tenant_user'));
    DB::disableQueryLog();

    expect($roles)->toBe([null, null, null])
        ->and($pivotQueries)->toHaveCount(1);
});

test('a role change on the pivot invalidates the memo on the same instance', function () {
    $user = User::factory()->create();
    $tenantId = $user->tenantId;

    expect($user->isOwnerOrAdmin())->toBeTrue();

    // Note the argument is the value that just warmed the memo — the pattern that
    // makes caller-side invalidation unworkable. A stale role here would let a
    // demoted user keep passing owner/admin permission checks.
    $user->tenants()->updateExistingPivot($tenantId, ['role' => TenantRole::User->value]);

    expect($user->currentRole())->toBe(TenantRole::User)
        ->and($user->isOwnerOrAdmin())->toBeFalse()
        ->and($user->isRestrictedUser())->toBeTrue();
});

test('detaching and re-attaching on the same instance resolves the new tenant', function () {
    $user = User::factory()->create();
    $originalTenantId = $user->tenantId;

    expect($user->currentRole())->toBe(TenantRole::Owner);

    $newTenant = Tenant::create(['name' => 'Org B']);
    $user->tenants()->detach($originalTenantId);
    $user->tenants()->attach($newTenant->id, ['role' => TenantRole::Administrator->value]);

    // A stale tenant id would scope every query to the tenant the user just left.
    expect($user->tenantId)->toBe((string) $newTenant->id)
        ->and($user->currentRole())->toBe(TenantRole::Administrator);
});

// ─── Lead scoping ────────────────────────────────────────────────────────────

test('user A cannot see leads from user B on dashboard', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Lead::factory()->forTenant($userA->tenantId)->create(['nome' => 'Lead de A']);
    Lead::factory()->forTenant($userB->tenantId)->create(['nome' => 'Lead de B']);

    $this->actingAs($userA)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.total', 1)
        );
});

test('user A cannot see leads from user B in conversas', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $agentA = Agent::factory()->create(['user_id' => $userA->id, 'is_default' => true]);
    $agentB = Agent::factory()->create(['user_id' => $userB->id, 'is_default' => true]);

    Lead::factory()->forAgent($agentA)->create();
    Lead::factory()->forAgent($agentB)->create();

    $response = $this->actingAs($userA)->get(route('conversas.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('conversas/Index')
            ->where('leads.total', 1)
        );
});

test('user B cannot access lead belonging to user A', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $agentA = Agent::factory()->create(['user_id' => $userA->id, 'is_default' => true]);

    $lead = Lead::factory()->forAgent($agentA)->create();

    $this->actingAs($userB)
        ->get(route('conversas.show', $lead))
        ->assertNotFound();
});

test('user A can access their own lead', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();

    $this->actingAs($user)
        ->get(route('conversas.show', $lead))
        ->assertOk();
});

// ─── ServiceTicket scoping ────────────────────────────────────────────────────

test('service tickets are scoped by tenant', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $leadA = Lead::factory()->forTenant($userA->tenantId)->create();
    $leadB = Lead::factory()->forTenant($userB->tenantId)->create();

    ServiceTicket::create([
        'tenant_id' => $userA->tenantId,
        'lead_id' => $leadA->id,
        'type' => 'escalation',
    ]);

    // Same ticket type as tenant A's so only the tenant scope can exclude it.
    ServiceTicket::create([
        'tenant_id' => $userB->tenantId,
        'lead_id' => $leadB->id,
        'type' => 'escalation',
    ]);

    // Both tickets default to status "open" and are unassigned, so they land in
    // the "waiting" bucket. userA must see only their tenant's ticket.
    $this->actingAs($userA)
        ->get(route('atendimentos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('atendimentos/Index')
            ->where('counters.waiting', 1)
        );
});

test('service ticket auto-inherits tenant_id from lead on create', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->forTenant($user->tenantId)->create();

    $ticket = ServiceTicket::create([
        'lead_id' => $lead->id,
        'type' => 'escalation',
    ]);

    expect($ticket->tenant_id)->toBe($user->tenantId);
});

// ─── AppSetting scoping ──────────────────────────────────────────────────────

test('app settings are isolated by user', function () {
    AppSetting::flushCache();

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    AppSetting::set('agent_name', 'ARIA-A', $userA->id);
    AppSetting::set('agent_name', 'ARIA-B', $userB->id);

    expect(AppSetting::get('agent_name', null, $userA->id))->toBe('ARIA-A');
    expect(AppSetting::get('agent_name', null, $userB->id))->toBe('ARIA-B');
});

test('global settings serve as fallback when user has no custom setting', function () {
    AppSetting::flushCache();

    $user = User::factory()->create();

    AppSetting::set('agent_name', 'GLOBAL', null);

    expect(AppSetting::get('agent_name', 'DEFAULT', $user->id))->toBe('GLOBAL');
});

test('user specific setting overrides global fallback', function () {
    AppSetting::flushCache();

    $user = User::factory()->create();

    AppSetting::set('agent_name', 'GLOBAL', null);
    AppSetting::set('agent_name', 'CUSTOM', $user->id);

    expect(AppSetting::get('agent_name', 'DEFAULT', $user->id))->toBe('CUSTOM');
});

// ─── Playground scoping ──────────────────────────────────────────────────────

test('playground sessions are scoped by tenant', function () {
    // The playground is behind the `super_admin` gate; tenant scoping still
    // applies on top of it, which is what these two tests pin down.
    $userA = User::factory()->superAdmin()->create();
    $userB = User::factory()->superAdmin()->create();

    Lead::factory()->forTenant($userA->tenantId)->sandbox()->create(['sandbox_label' => 'Sessão A']);
    Lead::factory()->forTenant($userB->tenantId)->sandbox()->create(['sandbox_label' => 'Sessão B']);

    $this->actingAs($userA)
        ->get(route('backoffice.playground.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('playground/Index')
            ->where('sessions', fn ($sessions) => count($sessions) === 1)
        );
});

test('user B cannot access sandbox lead belonging to user A', function () {
    $userA = User::factory()->superAdmin()->create();
    $userB = User::factory()->superAdmin()->create();

    $lead = Lead::factory()->forTenant($userA->tenantId)->sandbox()->create();

    // Selecting B's company is what scopes the super-admin; with nothing
    // selected the cross-tenant view is intentional.
    $this->actingAs($userB)
        ->withSession(['active_tenant_id' => (string) $userB->tenantId])
        ->postJson(route('backoffice.playground.chat', $lead), ['message' => 'oi'])
        ->assertNotFound();
});

// ─── Clear lead history ──────────────────────────────────────────────────────

test('user can clear own lead conversation history and memory', function () {
    $user = User::factory()->create();
    $conversationId = Str::uuid()->toString();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'title' => 'Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('agent_conversation_messages')->insert([
        'id' => Str::uuid()->toString(),
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
        'agent' => 'aria',
        'role' => 'user',
        'content' => 'Hello',
        'attachments' => '',
        'tool_calls' => '',
        'tool_results' => '',
        'usage' => '',
        'meta' => '',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $lead = Lead::factory()->forTenant($user->tenantId)->create([
        'conversation_id' => $conversationId,
    ]);

    $this->actingAs($user)
        ->post(route('conversas.clearHistory', $lead))
        ->assertRedirect();

    $lead->refresh();
    expect($lead->conversation_id)->toBeNull();
    expect(DB::table('agent_conversation_messages')->where('conversation_id', $conversationId)->count())->toBe(0);
    expect(DB::table('agent_conversations')->where('id', $conversationId)->count())->toBe(0);
});

test('user cannot clear history of lead from another tenant', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $lead = Lead::factory()->forTenant($userB->tenantId)->create();

    $this->actingAs($userA)
        ->post(route('conversas.clearHistory', $lead))
        ->assertNotFound();
});
