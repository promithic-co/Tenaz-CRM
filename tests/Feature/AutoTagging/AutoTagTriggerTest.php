<?php

use App\Events\LeadStatusChanged;
use App\Jobs\TagLeadFromConversationJob;
use App\Models\AppSetting;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('AutoTag Trigger', function () {
    test('disabled tenant setting means no dispatch', function () {
        Queue::fake();

        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;
        // auto_tagging_enabled NOT set (defaults false)
        $lead = Lead::factory()->forTenant($tenantId)->create();

        Tag::factory()->forTenant($tenantId)->create([
            'ai_detectable' => true,
            'ai_description' => 'Test',
        ]);

        LeadStatusChanged::dispatch($lead->id, $tenantId, 'novo', 'qualificado');

        Queue::assertNotPushed(TagLeadFromConversationJob::class);
    });

    test('no detectable tags means no dispatch even when enabled', function () {
        Queue::fake();

        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;
        AppSetting::setForTenant($tenantId, 'auto_tagging_enabled', true);
        $lead = Lead::factory()->forTenant($tenantId)->create();

        // No ai_detectable tags
        Tag::factory()->forTenant($tenantId)->create(['ai_detectable' => false]);

        LeadStatusChanged::dispatch($lead->id, $tenantId, 'novo', 'qualificado');

        Queue::assertNotPushed(TagLeadFromConversationJob::class);
    });

    test('relevant status transition dispatches one job on auto-tags', function () {
        Queue::fake();

        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;
        AppSetting::setForTenant($tenantId, 'auto_tagging_enabled', true);
        $lead = Lead::factory()->forTenant($tenantId)->create();

        Tag::factory()->forTenant($tenantId)->create([
            'ai_detectable' => true,
            'ai_description' => 'Test',
        ]);

        LeadStatusChanged::dispatch($lead->id, $tenantId, 'novo', 'qualificado');

        Queue::assertPushedOn('auto-tags', TagLeadFromConversationJob::class);
        Queue::assertPushed(TagLeadFromConversationJob::class, 1);
    });

    test('non-whitelisted status transition means no dispatch', function () {
        Queue::fake();

        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;
        AppSetting::setForTenant($tenantId, 'auto_tagging_enabled', true);
        $lead = Lead::factory()->forTenant($tenantId)->create();

        Tag::factory()->forTenant($tenantId)->create([
            'ai_detectable' => true,
            'ai_description' => 'Test',
        ]);

        // 'novo' -> 'aberto' is not in the whitelist
        LeadStatusChanged::dispatch($lead->id, $tenantId, 'novo', 'aberto');

        Queue::assertNotPushed(TagLeadFromConversationJob::class);
    });

    test('manual POST dispatches job when enabled and detectable tags exist', function () {
        Queue::fake();

        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;
        AppSetting::setForTenant($tenantId, 'auto_tagging_enabled', true);
        $lead = Lead::factory()->forTenant($tenantId)->create();

        Tag::factory()->forTenant($tenantId)->create([
            'ai_detectable' => true,
            'ai_description' => 'Test',
        ]);

        $this->actingAs($user)
            ->post(route('leads.auto-tag.store', $lead))
            ->assertRedirect();

        Queue::assertPushedOn('auto-tags', TagLeadFromConversationJob::class);
    });
});
