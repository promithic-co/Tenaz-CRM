<?php

use App\Models\Agent;
use App\Models\AgentFollowUpSetting;
use App\Models\FollowUpSetting;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Services\FollowUpSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('FollowUpSettingsResolver', function () {
    it('prefers agent_followup_settings row when present', function () {
        $agent = Agent::factory()->create();
        AgentFollowUpSetting::factory()->create([
            'agent_id' => $agent->id,
            'tenant_id' => $agent->tenant_id,
            'first_delay_minutes' => 22,
            'max_attempts_within_window' => 4,
            'tone' => 'direto',
        ]);

        $lead = Lead::factory()->create([
            'agent_id' => $agent->id,
            'tenant_id' => $agent->tenant_id,
        ]);

        $settings = app(FollowUpSettingsResolver::class)->forLead($lead);

        expect($settings['first_delay_minutes'])->toBe(22)
            ->and($settings['max_attempts_within_window'])->toBe(4)
            ->and($settings['tone'])->toBe('direto');
    });

    it('falls back to tenant followup_settings row when agent row missing', function () {
        $agent = Agent::factory()->create();
        FollowUpSetting::create([
            'tenant_id' => (string) $agent->tenant_id,
            'enabled' => true,
            'first_delay_minutes' => 33,
            'min_interval_minutes' => 90,
            'max_attempts_within_window' => 3,
            'business_window_start' => '08:00',
            'business_window_end' => '20:00',
            'timezone' => 'America/Sao_Paulo',
            'message_type' => 'contextual',
            'tone' => 'acolhedor',
            'persuasion_intensity' => 3,
            'custom_instructions' => '',
        ]);

        $lead = Lead::factory()->create([
            'agent_id' => $agent->id,
            'tenant_id' => $agent->tenant_id,
        ]);

        $settings = app(FollowUpSettingsResolver::class)->forLead($lead);

        expect($settings['first_delay_minutes'])->toBe(33)
            ->and($settings['tone'])->toBe('acolhedor');
    });

    it('falls back to hardcoded defaults when no rows exist', function () {
        $lead = Lead::factory()->create();

        $settings = app(FollowUpSettingsResolver::class)->forLead($lead);

        expect($settings['first_delay_minutes'])->toBe(10)
            ->and($settings['max_attempts_within_window'])->toBe(2)
            ->and($settings['tone'])->toBe('consultivo');
    });

    it('forgetAgent clears the per-agent cache', function () {
        $agent = Agent::factory()->create();
        AgentFollowUpSetting::factory()->create([
            'agent_id' => $agent->id,
            'tenant_id' => $agent->tenant_id,
            'first_delay_minutes' => 15,
        ]);

        $resolver = app(FollowUpSettingsResolver::class);
        $first = $resolver->forAgent($agent->id, (string) $agent->tenant_id);

        AgentFollowUpSetting::withoutGlobalScope('tenant')
            ->where('agent_id', $agent->id)
            ->update(['first_delay_minutes' => 45]);

        // Without forget, cache returns stale value.
        $stale = $resolver->forAgent($agent->id, (string) $agent->tenant_id);
        expect($stale['first_delay_minutes'])->toBe(15);

        $resolver->forgetAgent($agent->id);
        $fresh = $resolver->forAgent($agent->id, (string) $agent->tenant_id);
        expect($fresh['first_delay_minutes'])->toBe(45);
    });
});

describe('WABA-agent uniqueness', function () {
    it('rejects assigning the same meta_waba_id to a different agent', function () {
        $agentA = Agent::factory()->create();
        $agentB = Agent::factory()->create(['tenant_id' => $agentA->tenant_id]);

        WhatsappInstance::factory()->create([
            'tenant_id' => $agentA->tenant_id,
            'agent_id' => $agentA->id,
            'meta_waba_id' => 'WABA-123',
        ]);

        expect(fn () => WhatsappInstance::factory()->create([
            'tenant_id' => $agentB->tenant_id,
            'agent_id' => $agentB->id,
            'meta_waba_id' => 'WABA-123',
        ]))->toThrow(\DomainException::class);
    });

    it('allows multiple phone numbers under the same WABA to share an agent', function () {
        $agent = Agent::factory()->create();

        WhatsappInstance::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'agent_id' => $agent->id,
            'meta_waba_id' => 'WABA-456',
        ]);

        $second = WhatsappInstance::factory()->create([
            'tenant_id' => $agent->tenant_id,
            'agent_id' => $agent->id,
            'meta_waba_id' => 'WABA-456',
        ]);

        expect($second->exists)->toBeTrue();
    });

    it('does not enforce when meta_waba_id or agent_id is null', function () {
        $instance = WhatsappInstance::factory()->create([
            'meta_waba_id' => null,
            'agent_id' => null,
        ]);

        expect($instance->exists)->toBeTrue();
    });
});
