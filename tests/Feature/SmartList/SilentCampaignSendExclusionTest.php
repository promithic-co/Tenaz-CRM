<?php

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use App\Services\LaboratoryMetricsService;
use App\Services\SmartList\SmartListResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A campaign creates a Lead per recipient so the send is visible in /conversas. Those rows
 * carry the default status and a fresh created_at, so anything counting or resolving leads
 * has to say whether it means people or rows.
 */
function silentSend(User $user, Campaign $campaign, string $phone): Lead
{
    return Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => $phone,
        'campaign_id' => $campaign->id,
        'last_inbound_at' => null,
        'status' => 'novo',
    ]);
}

test('a smart list does not resolve to people who never answered a blast', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->completed()->create(['tenant_id' => $user->tenantId]);

    silentSend($user, $campaign, '5511999990001');
    silentSend($user, $campaign, '5511999990002');

    $organic = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990003',
        'campaign_id' => null,
        'status' => 'novo',
    ]);

    $filters = ['version' => 1, 'match' => 'all', 'rules' => [['field' => 'status', 'op' => 'in', 'value' => ['novo']]]];
    $resolver = app(SmartListResolverService::class);

    // Left in, the list a campaign is built from grows by its own previous fan-out.
    expect($resolver->count($user->tenantId, $filters))->toBe(1)
        ->and($resolver->buildQuery($user->tenantId, $filters)->first()->id)->toBe($organic->id);
});

test('a recipient who replied is back in the smart list', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->completed()->create(['tenant_id' => $user->tenantId]);

    $replied = silentSend($user, $campaign, '5511999990004');
    $replied->update(['last_inbound_at' => now()]);

    $filters = ['version' => 1, 'match' => 'all', 'rules' => [['field' => 'status', 'op' => 'in', 'value' => ['novo']]]];

    expect(app(SmartListResolverService::class)->count($user->tenantId, $filters))->toBe(1);
});

test('the laboratory counts campaign replies rather than campaign sends', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->completed()->create(['tenant_id' => $user->tenantId]);

    silentSend($user, $campaign, '5511999990005');
    silentSend($user, $campaign, '5511999990006');

    $replied = silentSend($user, $campaign, '5511999990007');
    $replied->update(['last_inbound_at' => now()]);

    // The old predicate was modo = 'bulk', which nothing in the application writes, so this
    // reported 0 however many people answered.
    $metrics = app(LaboratoryMetricsService::class)->bulkMetrics($user->tenantId);

    expect($metrics['replies_from_campaigns_today'])->toBe(1);
});

test('a reply from an earlier day is not counted as today', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->completed()->create(['tenant_id' => $user->tenantId]);

    $yesterday = silentSend($user, $campaign, '5511999990008');
    $yesterday->update(['last_inbound_at' => now()->subDays(2)]);

    expect(app(LaboratoryMetricsService::class)->bulkMetrics($user->tenantId)['replies_from_campaigns_today'])->toBe(0);
});
