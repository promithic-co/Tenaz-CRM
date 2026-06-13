<?php

use App\Models\Agent;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\FailedInteraction;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{0: User, 1: Tenant, 2: Agent} */
function labUserWithTenant(): array
{
    $user = User::factory()->create();
    $tenant = $user->tenants()->first();
    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);

    return [$user, $tenant, $agent];
}

it('displays laboratory dashboard for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('laboratory'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('laboratory/Index'));
});

it('redirects guests to login', function () {
    $this->get(route('laboratory'))
        ->assertRedirect(route('login'));
});

it('shows correct failure statistics for the current tenant only', function () {
    [$user, $tenant, $agent] = labUserWithTenant();
    $lead = Lead::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => $user->tenantId,
    ]);

    FailedInteraction::create([
        'lead_id' => $lead->id, 'agent_id' => $agent->id,
        'error_tag' => 'timeout', 'error_source' => 'openai',
        'error_message' => 'Test', 'status' => 'pending',
        'next_retry_at' => now()->addMinutes(15),
    ]);

    FailedInteraction::create([
        'lead_id' => $lead->id, 'agent_id' => $agent->id,
        'error_tag' => 'timeout', 'error_source' => 'openai',
        'error_message' => 'Test', 'status' => 'escalated',
        'next_retry_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('laboratory'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('laboratory/Index')
            ->has('stats')
            ->where('stats.pending_retries', 1)
            ->where('stats.escalated_open', 1)
        );
});

it('does not include other tenants failed interactions in stats', function () {
    [$userA, $tenantA, $agentA] = labUserWithTenant();
    $leadA = Lead::factory()->create([
        'agent_id' => $agentA->id,
        'tenant_id' => (string) $tenantA->id,
    ]);
    FailedInteraction::create([
        'lead_id' => $leadA->id, 'agent_id' => $agentA->id,
        'error_tag' => 'timeout', 'error_source' => 'openai',
        'error_message' => 'A', 'status' => 'pending',
        'next_retry_at' => now()->addMinutes(15),
    ]);

    [$userB, $tenantB, $agentB] = labUserWithTenant();
    $leadB = Lead::factory()->create([
        'agent_id' => $agentB->id,
        'tenant_id' => (string) $tenantB->id,
    ]);
    FailedInteraction::create([
        'lead_id' => $leadB->id, 'agent_id' => $agentB->id,
        'error_tag' => 'server_error', 'error_source' => 'openai',
        'error_message' => 'B', 'status' => 'pending',
        'next_retry_at' => now()->addMinutes(15),
    ]);

    $this->actingAs($userA)
        ->get(route('laboratory'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('stats.pending_retries', 1)
        );
});

it('shows error pattern distribution for the current tenant', function () {
    [$user, $tenant, $agent] = labUserWithTenant();
    $lead = Lead::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => $user->tenantId,
    ]);

    FailedInteraction::create([
        'lead_id' => $lead->id, 'agent_id' => $agent->id,
        'error_tag' => 'rate_limit', 'error_source' => 'openai',
        'error_message' => 'Test', 'status' => 'pending',
        'next_retry_at' => now()->addMinutes(15),
    ]);

    $this->actingAs($user)
        ->get(route('laboratory'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('laboratory/Index')
            ->has('errorPatterns', 1)
            ->where('errorPatterns.0.error_tag', 'rate_limit')
        );
});

it('calculates recovery rate correctly for the current tenant', function () {
    [$user, $tenant, $agent] = labUserWithTenant();
    $lead = Lead::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => $user->tenantId,
    ]);

    FailedInteraction::create([
        'lead_id' => $lead->id, 'agent_id' => $agent->id,
        'error_tag' => 'timeout', 'error_source' => 'openai',
        'error_message' => 'Test', 'status' => 'resolved',
        'next_retry_at' => null, 'resolved_at' => now(),
    ]);
    FailedInteraction::create([
        'lead_id' => $lead->id, 'agent_id' => $agent->id,
        'error_tag' => 'timeout', 'error_source' => 'openai',
        'error_message' => 'Test', 'status' => 'resolved',
        'next_retry_at' => null, 'resolved_at' => now(),
    ]);
    FailedInteraction::create([
        'lead_id' => $lead->id, 'agent_id' => $agent->id,
        'error_tag' => 'timeout', 'error_source' => 'openai',
        'error_message' => 'Test', 'status' => 'pending',
        'next_retry_at' => now()->addMinutes(15),
    ]);

    $this->actingAs($user)
        ->get(route('laboratory'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('laboratory/Index')
            ->where('recoveryRate', 66.7)
        );
});

it('laboratory follow-up metrics show for current tenant', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
    ]);
    Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
        'is_sandbox' => false,
        'followup_status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('laboratory'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('followupStats.active_count', 1)
            ->where('followupStats.paused_count', 0)
        );
});

it('buckets hourly failures by created hour using the sqlite strftime branch', function () {
    expect(config('database.default'))->toBe('sqlite');

    [$user, $tenant, $agent] = labUserWithTenant();
    $lead = Lead::factory()->create([
        'agent_id' => $agent->id,
        'tenant_id' => $user->tenantId,
    ]);

    foreach ([['09:15:00', 2], ['14:30:00', 1]] as [$time, $count]) {
        for ($i = 0; $i < $count; $i++) {
            $failure = FailedInteraction::create([
                'lead_id' => $lead->id, 'agent_id' => $agent->id,
                'error_tag' => 'timeout', 'error_source' => 'openai',
                'error_message' => 'Test', 'status' => 'pending',
                'next_retry_at' => now()->addMinutes(15),
            ]);
            $failure->forceFill(['created_at' => today()->setTimeFromTimeString($time)])->saveQuietly();
        }
    }

    $this->actingAs($user)
        ->get(route('laboratory'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('hourlyFailures.9', 2)
            ->where('hourlyFailures.14', 1)
        );
});

it('shows bulk campaign metrics for the current tenant', function () {
    [$user, $tenant, $agent] = labUserWithTenant();

    Campaign::factory()->sending()->create(['tenant_id' => $user->tenantId]);
    $completed = Campaign::factory()->completed()->create(['tenant_id' => $user->tenantId]);
    expect($completed->completed_at->isToday())->toBeTrue();

    $deliveryCampaign = Campaign::factory()->create(['tenant_id' => $user->tenantId]);
    CampaignMessage::factory()->sent()->count(4)->create(['campaign_id' => $deliveryCampaign->id]);
    CampaignMessage::factory()->delivered()->count(2)->create(['campaign_id' => $deliveryCampaign->id]);

    $this->actingAs($user)
        ->get(route('laboratory'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('bulkMetrics.campaigns_active', 1)
            ->where('bulkMetrics.campaigns_completed_today', 1)
            ->where('bulkMetrics.messages_sent_today', 6)
            ->where('bulkMetrics.messages_delivered_today', 2)
            ->where('bulkMetrics.delivery_rate_today', 33.3)
            ->where('bulkMetrics.estimated_cost_today', 0.3)
        );
});
