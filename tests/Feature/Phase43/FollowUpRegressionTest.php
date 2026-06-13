<?php

use App\Jobs\ProcessLeadFollowUpJob;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;

it('test_follow_up_via_whatsapp_instance', function () {
    Bus::fake([ProcessLeadFollowUpJob::class]);

    // Fix time to 10:00 São Paulo so the business-hours check passes
    Carbon::setTestNow(Carbon::create(2026, 4, 24, 13, 0, 0, 'UTC')); // 10:00 BRT

    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->for($user)->create(['name' => 'evo-followup']);
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
        'followup_status' => 'active',
        'followup_count' => 0,
        'whatsapp_instance_id' => $instance->id,
        'last_interaction_at' => now()->subMinutes(20),
        'last_inbound_at' => now()->subMinutes(20),
        'is_sandbox' => false,
        'campaign_id' => null,
    ]);

    $this->artisan('credflow:check-followups')->assertSuccessful();

    Bus::assertDispatched(ProcessLeadFollowUpJob::class);
})->afterEach(fn () => Carbon::setTestNow());

it('test_follow_up_via_meta_cloud_instance', function () {
    Bus::fake([ProcessLeadFollowUpJob::class]);

    Carbon::setTestNow(Carbon::create(2026, 4, 24, 13, 0, 0, 'UTC')); // 10:00 BRT

    $user = User::factory()->create();
    $metaInstance = WhatsappInstance::factory()->metaCloud()->for($user)->create(['name' => 'meta-followup']);
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'agent_id' => $agent->id,
        'followup_status' => 'active',
        'followup_count' => 0,
        'whatsapp_instance_id' => $metaInstance->id,
        'last_interaction_at' => now()->subMinutes(20),
        'last_inbound_at' => now()->subMinutes(20),
        'is_sandbox' => false,
        'campaign_id' => null,
    ]);

    $this->artisan('credflow:check-followups')->assertSuccessful();

    Bus::assertDispatched(ProcessLeadFollowUpJob::class);
})->afterEach(fn () => Carbon::setTestNow());
