<?php

use App\Ai\Agents\CredFlowFollowUpAgent;
use App\Jobs\ProcessIncomingWhatsAppMessageJob;
use App\Jobs\ProcessLeadFollowUpJob;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\FollowupMessage;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Services\AgentService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// CheckFollowUpsCommand tests
// ---------------------------------------------------------------------------

describe('CheckFollowUpsCommand', function () {
    test('excludes sandbox leads from dispatch', function () {
        Queue::fake();

        Lead::factory()->create([
            'is_sandbox' => true,
            'followup_status' => 'active',
            'followup_count' => 0,
            'last_interaction_at' => now()->subMinutes(30),
        ]);

        $this->artisan('credflow:check-followups');

        Queue::assertNotPushed(ProcessLeadFollowUpJob::class);
    });

    test('dispatches first follow-up after delay expires', function () {
        Queue::fake();

        // Freeze time within business hours (12:00 UTC = within 08-20 São Paulo window)
        $this->travelTo(\Carbon\Carbon::create(2026, 3, 20, 15, 0, 0, 'UTC'));

        Lead::factory()->create([
            'is_sandbox' => false,
            'followup_status' => 'active',
            'followup_count' => 0,
            'last_interaction_at' => now()->subMinutes(15),
            'last_inbound_at' => now()->subMinutes(15),
        ]);

        $this->artisan('credflow:check-followups');

        Queue::assertPushed(ProcessLeadFollowUpJob::class);
    });

    test('does not dispatch first follow-up before delay expires', function () {
        Queue::fake();

        Lead::factory()->create([
            'is_sandbox' => false,
            'followup_status' => 'active',
            'followup_count' => 0,
            'last_interaction_at' => now()->subMinutes(2),
            'last_inbound_at' => now()->subMinutes(2),
        ]);

        $this->artisan('credflow:check-followups');

        Queue::assertNotPushed(ProcessLeadFollowUpJob::class);
    });

    test('dispatches daily follow-up at configured time', function () {
        Queue::fake();

        // Freeze time at 10:01 São Paulo time (past the default daily time of 10:00)
        $frozenTime = Carbon::parse('2026-03-17 10:01', 'America/Sao_Paulo');
        Carbon::setTestNow($frozenTime);

        Lead::factory()->create([
            'is_sandbox' => false,
            'followup_status' => 'active',
            'followup_count' => 1,
            'last_interaction_at' => Carbon::parse('2026-03-16 10:00', 'America/Sao_Paulo'),
            'last_inbound_at' => $frozenTime->copy()->subHours(2),
        ]);

        $this->artisan('credflow:check-followups');

        Queue::assertPushed(ProcessLeadFollowUpJob::class);

        Carbon::setTestNow();
    });

    test('does not dispatch if already sent today', function () {
        Queue::fake();

        // Freeze time at 10:01 São Paulo time
        $frozenTime = Carbon::parse('2026-03-17 10:01', 'America/Sao_Paulo');
        Carbon::setTestNow($frozenTime);

        // last_interaction_at is today — already sent
        Lead::factory()->create([
            'is_sandbox' => false,
            'followup_status' => 'active',
            'followup_count' => 1,
            'last_interaction_at' => Carbon::parse('2026-03-17 09:30', 'America/Sao_Paulo'),
            'last_inbound_at' => $frozenTime->copy()->subHours(2),
        ]);

        $this->artisan('credflow:check-followups');

        Queue::assertNotPushed(ProcessLeadFollowUpJob::class);

        Carbon::setTestNow();
    });

    test('ignores inactive leads', function () {
        Queue::fake();

        Lead::factory()->create([
            'is_sandbox' => false,
            'followup_status' => 'inactive',
            'followup_count' => 2,
            'last_interaction_at' => now()->subDays(2),
        ]);

        $this->artisan('credflow:check-followups');

        Queue::assertNotPushed(ProcessLeadFollowUpJob::class);
    });
});

// ---------------------------------------------------------------------------
// ProcessLeadFollowUpJob tests
// ---------------------------------------------------------------------------

describe('ProcessLeadFollowUpJob', function () {
    test('skips execution when lead is no longer active', function () {
        CredFlowFollowUpAgent::fake();

        $lead = Lead::factory()->create([
            'followup_status' => 'inactive',
            'followup_count' => 0,
        ]);

        $job = new ProcessLeadFollowUpJob($lead);
        $job->handle(
            app(\App\Services\WhatsappOutboxService::class),
            app(\App\Services\FollowUpSettingsResolver::class),
            app(\App\Services\FollowUpWindowService::class),
            app(\App\Services\PauseService::class),
        );

        CredFlowFollowUpAgent::assertNeverPrompted();
    });

    test('skips execution when inbound message was very recent', function () {
        CredFlowFollowUpAgent::fake(['unused']);

        $lead = Lead::factory()->create([
            'followup_status' => 'active',
            'followup_count' => 0,
            'last_interaction_at' => now()->subMinute(),
            'last_inbound_at' => now()->subMinutes(2),
        ]);

        $job = new ProcessLeadFollowUpJob($lead);
        $job->handle(
            app(\App\Services\WhatsappOutboxService::class),
            app(\App\Services\FollowUpSettingsResolver::class),
            app(\App\Services\FollowUpWindowService::class),
            app(\App\Services\PauseService::class),
        );

        CredFlowFollowUpAgent::assertNeverPrompted();
        expect($lead->fresh()->followup_count)->toBe(0);
        expect(FollowupMessage::where('lead_id', $lead->id)->count())->toBe(0);
    });

    test('skips execution when lead is in manual human mode', function () {
        $this->travelTo(Carbon::create(2026, 3, 20, 15, 0, 0, 'UTC'));
        CredFlowFollowUpAgent::fake(['unused']);

        $lead = Lead::factory()->create([
            'ai_mode' => Lead::AI_MODE_MANUAL,
            'operational_stage' => Lead::STAGE_HUMAN_ACTIVE,
            'followup_status' => 'active',
            'followup_count' => 0,
            'last_interaction_at' => now()->subHours(2),
            'last_inbound_at' => now()->subHours(2),
        ]);

        $job = new ProcessLeadFollowUpJob($lead);
        $job->handle(
            app(\App\Services\WhatsappOutboxService::class),
            app(\App\Services\FollowUpSettingsResolver::class),
            app(\App\Services\FollowUpWindowService::class),
            app(\App\Services\PauseService::class),
        );

        CredFlowFollowUpAgent::assertNeverPrompted();
        expect($lead->fresh()->followup_status)->toBe('paused')
            ->and($lead->fresh()->followup_count)->toBe(0);
    });

    test('does not skip when only last_interaction_at is fresh from scheduler pre-stamp', function () {
        $this->travelTo(Carbon::create(2026, 3, 20, 15, 0, 0, 'UTC'));
        CredFlowFollowUpAgent::fake(['Mensagem de follow-up de teste']);
        $instance = WhatsappInstance::factory()->create([
            'tenant_id' => 'default',
            'name' => 'test-instance',
        ]);

        $lead = Lead::factory()->create([
            'followup_status' => 'active',
            'followup_count' => 0,
            'last_interaction_at' => now()->subMinute(),
            'last_inbound_at' => now()->subHours(2),
            'whatsapp_instance_id' => $instance->id,
        ]);

        $job = new ProcessLeadFollowUpJob($lead);
        $job->handle(
            app(\App\Services\WhatsappOutboxService::class),
            app(\App\Services\FollowUpSettingsResolver::class),
            app(\App\Services\FollowUpWindowService::class),
            app(\App\Services\PauseService::class),
        );

        expect($lead->fresh()->followup_count)->toBe(1);
        expect($lead->fresh()->last_interaction_at->greaterThan(now()->subMinute()))->toBeTrue();
        expect(FollowupMessage::where('lead_id', $lead->id)->count())->toBe(1);
    });

    test('deactivates lead when max follow-up count is reached', function () {
        $this->travelTo(Carbon::create(2026, 3, 20, 15, 0, 0, 'UTC'));
        // Fake the AI agent so no real API call is made
        CredFlowFollowUpAgent::fake(['Olá, ainda posso ajudar?']);

        $instance = WhatsappInstance::factory()->create([
            'tenant_id' => 'default',
            'name' => 'test-instance',
        ]);

        // Default follow-up limit is 2 within the 24h customer service window.
        $lead = Lead::factory()->create([
            'followup_status' => 'active',
            'followup_count' => 1,
            'last_interaction_at' => now()->subHours(2),
            'last_inbound_at' => now()->subHours(2),
            'whatsapp_instance_id' => $instance->id,
        ]);

        $job = new ProcessLeadFollowUpJob($lead);
        $job->handle(
            app(\App\Services\WhatsappOutboxService::class),
            app(\App\Services\FollowUpSettingsResolver::class),
            app(\App\Services\FollowUpWindowService::class),
            app(\App\Services\PauseService::class),
        );

        $lead->refresh();

        expect($lead->followup_status)->toBe('inactive')
            ->and($lead->followup_count)->toBe(2)
            ->and($lead->last_interaction_at->greaterThan(now()->subMinute()))->toBeTrue();
    });

    test('has correct retry configuration', function () {
        $lead = Lead::factory()->create();
        $job = new ProcessLeadFollowUpJob($lead);

        expect($job->tries)->toBe(3)
            ->and($job->backoff)->toBe([60, 300])
            ->and($job->timeout)->toBe(120);
    });
});

// ---------------------------------------------------------------------------
// Phase 10: Smart Scheduler tests
// ---------------------------------------------------------------------------

describe('Phase 10: Smart Scheduler', function () {
    test('job not dispatched when time is before business hours window', function () {
        Queue::fake();

        $frozenTime = Carbon::parse('2026-03-16 09:30:00', 'America/Sao_Paulo');
        $this->travelTo($frozenTime);

        $agent = Agent::factory()->create();
        $lead = Lead::factory()->create([
            'agent_id' => $agent->id,
            'tenant_id' => $agent->tenant_id,
            'is_sandbox' => false,
            'followup_status' => 'active',
            'followup_count' => 0,
            'last_interaction_at' => $frozenTime->copy()->subMinutes(15),
            'last_inbound_at' => $frozenTime->copy()->subMinutes(15),
        ]);
        AgentConfig::factory()->create([
            'agent_id' => $agent->id,
            'followup_window_start' => '10:00',
            'followup_window_end' => '20:00',
            'followup_interval_days' => 1,
        ]);

        $this->artisan('credflow:check-followups');

        Queue::assertNotPushed(ProcessLeadFollowUpJob::class);

        $this->travelBack();
    });

    test('job dispatched when time is within business hours window', function () {
        Queue::fake();

        $frozenTime = Carbon::parse('2026-03-16 10:01:00', 'America/Sao_Paulo');
        $this->travelTo($frozenTime);

        $agent = Agent::factory()->create();
        $lead = Lead::factory()->create([
            'agent_id' => $agent->id,
            'tenant_id' => $agent->tenant_id,
            'is_sandbox' => false,
            'followup_status' => 'active',
            'followup_count' => 0,
            'last_interaction_at' => $frozenTime->copy()->subMinutes(15),
            'last_inbound_at' => $frozenTime->copy()->subMinutes(15),
        ]);
        AgentConfig::factory()->create([
            'agent_id' => $agent->id,
            'followup_window_start' => '10:00',
            'followup_window_end' => '20:00',
            'followup_interval_days' => 1,
        ]);

        $this->artisan('credflow:check-followups');

        Queue::assertPushed(ProcessLeadFollowUpJob::class);

        $this->travelBack();
    });

    test('job not dispatched when time is after business hours window', function () {
        Queue::fake();

        $frozenTime = Carbon::parse('2026-03-16 20:30:00', 'America/Sao_Paulo');
        $this->travelTo($frozenTime);

        $agent = Agent::factory()->create();
        $lead = Lead::factory()->create([
            'agent_id' => $agent->id,
            'tenant_id' => $agent->tenant_id,
            'is_sandbox' => false,
            'followup_status' => 'active',
            'followup_count' => 0,
            'last_interaction_at' => $frozenTime->copy()->subMinutes(15),
            'last_inbound_at' => $frozenTime->copy()->subMinutes(15),
        ]);
        AgentConfig::factory()->create([
            'agent_id' => $agent->id,
            'followup_window_start' => '10:00',
            'followup_window_end' => '20:00',
            'followup_interval_days' => 1,
        ]);

        $this->artisan('credflow:check-followups');

        Queue::assertNotPushed(ProcessLeadFollowUpJob::class);

        $this->travelBack();
    });

    test('job not dispatched when minimum interval has not elapsed since last follow-up', function () {
        Queue::fake();

        $frozenTime = Carbon::parse('2026-03-16 10:01:00', 'America/Sao_Paulo');
        $this->travelTo($frozenTime);

        $agent = Agent::factory()->create();
        $lead = Lead::factory()->create([
            'agent_id' => $agent->id,
            'tenant_id' => $agent->tenant_id,
            'is_sandbox' => false,
            'followup_status' => 'active',
            'followup_count' => 1,
            'last_interaction_at' => $frozenTime->copy()->subMinutes(30),
            'last_inbound_at' => $frozenTime->copy()->subHours(2),
        ]);
        AgentConfig::factory()->create([
            'agent_id' => $agent->id,
            'followup_interval_days' => 2,
            'followup_daily_time' => '10:00',
            'followup_window_start' => '08:00',
            'followup_window_end' => '20:00',
        ]);

        $this->artisan('credflow:check-followups');

        Queue::assertNotPushed(ProcessLeadFollowUpJob::class);

        $this->travelBack();
    });

    test('job dispatched when minimum interval has elapsed since last follow-up', function () {
        Queue::fake();

        $frozenTime = Carbon::parse('2026-03-16 10:01:00', 'America/Sao_Paulo');
        $this->travelTo($frozenTime);

        $agent = Agent::factory()->create();
        $lead = Lead::factory()->create([
            'agent_id' => $agent->id,
            'tenant_id' => $agent->tenant_id,
            'is_sandbox' => false,
            'followup_status' => 'active',
            'followup_count' => 1,
            'last_interaction_at' => $frozenTime->copy()->subMinutes(90),
            'last_inbound_at' => $frozenTime->copy()->subHours(2),
        ]);
        AgentConfig::factory()->create([
            'agent_id' => $agent->id,
            'followup_interval_days' => 2,
            'followup_daily_time' => '10:00',
            'followup_window_start' => '08:00',
            'followup_window_end' => '20:00',
        ]);

        $this->artisan('credflow:check-followups');

        Queue::assertPushed(ProcessLeadFollowUpJob::class);

        $this->travelBack();
    });

    test('lead active over 14 days with no interaction is deactivated by safety cutoff', function () {
        Queue::fake();

        $lead = Lead::factory()->create([
            'is_sandbox' => false,
            'followup_status' => 'active',
            'followup_count' => 1,
            'last_interaction_at' => now()->subDays(15),
        ]);

        $this->artisan('credflow:check-followups');

        expect($lead->fresh()->followup_status)->toBe('inactive');
        Queue::assertNotPushed(ProcessLeadFollowUpJob::class);
    });

    test('lead active under 14 days is not deactivated by safety cutoff', function () {
        Queue::fake();

        $lead = Lead::factory()->create([
            'is_sandbox' => false,
            'followup_status' => 'active',
            'followup_count' => 1,
            'last_interaction_at' => now()->subDays(10),
            'last_inbound_at' => now()->subMinutes(5),
        ]);

        $this->artisan('credflow:check-followups');

        expect($lead->fresh()->followup_status)->toBe('active');
    });
});

// ---------------------------------------------------------------------------
// Customer reply cancels follow-up
// ---------------------------------------------------------------------------

describe('Customer reply cancels follow-up', function () {
    test('follow-up status becomes inactive when customer replies during active sequence', function () {
        $agent = Agent::factory()->create(['is_active' => true]);
        WhatsappInstance::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'name' => 'test-instance',
        ]);

        $lead = Lead::factory()->create([
            'agent_id' => $agent->id,
            'tenant_id' => $agent->tenant_id,
            'followup_status' => 'active',
            'followup_count' => 1,
        ]);

        $this->mock(AgentService::class, function ($mock) {
            $mock->shouldReceive('process')->once()->andReturn(null);
        });

        $this->mock(WhatsAppService::class, function ($mock) {
            $mock->shouldNotReceive('sendSplitMessages');
        });

        $job = new ProcessIncomingWhatsAppMessageJob(
            phone: $lead->whatsapp,
            name: $lead->nome ?? 'Test',
            tenantId: (string) $lead->tenant_id,
            agentId: $agent->id,
            instanceName: 'test-instance',
            aggregatedMessage: 'Olá, tenho interesse sim!',
        );

        app()->call([$job, 'handle']);

        expect($lead->fresh()->followup_status)->toBe('inactive');
    });
});
