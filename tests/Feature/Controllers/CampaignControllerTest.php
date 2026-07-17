<?php

use App\Enums\TenantRole;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function makeCampaignUser(): User
{
    return User::factory()->create();
}

function makeCampaignForUser(User $user): Campaign
{
    return Campaign::factory()->create(['tenant_id' => $user->tenantId]);
}

function makeRestrictedCampaignUser(): User
{
    $user = User::factory()->create();
    $user->tenants()->updateExistingPivot($user->tenantId, ['role' => TenantRole::User->value]);

    return $user->fresh();
}

test('store is forbidden for a restricted (non-owner/admin) user', function () {
    $user = makeRestrictedCampaignUser();
    $instance = WhatsappInstance::factory()->create(['tenant_id' => $user->tenantId, 'user_id' => $user->id]);
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
        'meta_template_name' => 'restricted_template',
        'meta_waba_id' => $instance->meta_waba_id,
    ]);

    $this->actingAs($user)->post('/campanhas', [
        'name' => 'Bloqueada',
        'whatsapp_instance_id' => $instance->id,
        'contact_list_id' => $list->id,
        'whatsapp_template_id' => $template->id,
    ])->assertForbidden();

    expect(Campaign::withoutGlobalScope('tenant')->where('name', 'Bloqueada')->exists())->toBeFalse();
});

test('create and index pages are forbidden for a restricted user', function () {
    $user = makeRestrictedCampaignUser();

    $this->actingAs($user)->get('/campanhas/create')->assertForbidden();
    $this->actingAs($user)->get('/campanhas')->assertForbidden();
});

test('store creates draft campaign and redirects to show', function () {
    $user = makeCampaignUser();
    $instance = WhatsappInstance::factory()->create(['tenant_id' => $user->tenantId, 'user_id' => $user->id]);
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
        'meta_template_name' => 'draft_template',
        'meta_waba_id' => $instance->meta_waba_id,
    ]);

    $response = $this->actingAs($user)->post('/campanhas', [
        'name' => 'Campanha Teste',
        'whatsapp_instance_id' => $instance->id,
        'contact_list_id' => $list->id,
        'whatsapp_template_id' => $template->id,
    ]);

    $campaign = Campaign::where('name', 'Campanha Teste')->first();
    expect($campaign)->not->toBeNull();
    expect($campaign->status)->toBe('draft');
    expect((string) $campaign->tenant_id)->toBe($user->tenantId);

    $response->assertRedirect(route('campanhas.show', $campaign));
});

test('store creates scheduled campaign when scheduled_at provided', function () {
    $user = makeCampaignUser();
    $instance = WhatsappInstance::factory()->create(['tenant_id' => $user->tenantId, 'user_id' => $user->id]);
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
        'meta_template_name' => 'scheduled_template',
        'meta_waba_id' => $instance->meta_waba_id,
    ]);

    $this->actingAs($user)->post('/campanhas', [
        'name' => 'Campanha Agendada',
        'whatsapp_instance_id' => $instance->id,
        'contact_list_id' => $list->id,
        'whatsapp_template_id' => $template->id,
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ]);

    $campaign = Campaign::where('name', 'Campanha Agendada')->first();
    expect($campaign->status)->toBe('scheduled');
});

test('store rejects instance from another tenant', function () {
    $user = makeCampaignUser();
    $other = makeCampaignUser();
    $instance = WhatsappInstance::factory()->create(['tenant_id' => $other->tenantId, 'user_id' => $other->id]);
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    $template = WhatsappTemplate::factory()->create(['tenant_id' => $user->tenantId]);

    $response = $this->actingAs($user)->post('/campanhas', [
        'name' => 'Hacked',
        'whatsapp_instance_id' => $instance->id,
        'contact_list_id' => $list->id,
        'whatsapp_template_id' => $template->id,
    ]);

    $response->assertSessionHasErrors('whatsapp_instance_id');
});

test('destroy removes draft campaign and redirects to index', function () {
    $user = makeCampaignUser();
    $campaign = makeCampaignForUser($user);

    $response = $this->actingAs($user)->delete("/campanhas/{$campaign->id}");

    $response->assertRedirect(route('campanhas.index'));
    expect(Campaign::find($campaign->id))->toBeNull();
});

test('destroy is forbidden for another tenant', function () {
    $user = makeCampaignUser();
    $other = makeCampaignUser();
    $campaign = makeCampaignForUser($user);

    $this->actingAs($other)->delete("/campanhas/{$campaign->id}")
        ->assertNotFound();

    expect(Campaign::withoutGlobalScope('tenant')->find($campaign->id))->not->toBeNull();
});

test('destroy cannot delete sending campaign', function () {
    $user = makeCampaignUser();
    $campaign = Campaign::factory()->sending()->create(['tenant_id' => $user->tenantId]);

    $response = $this->actingAs($user)->delete("/campanhas/{$campaign->id}");

    $response->assertSessionHasErrors('campaign');
    expect(Campaign::find($campaign->id))->not->toBeNull();
});

test('start action transitions draft campaign to sending', function () {
    Queue::fake();
    $user = makeCampaignUser();
    $instance = WhatsappInstance::factory()->create(['tenant_id' => $user->tenantId, 'user_id' => $user->id]);
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'status' => 'APPROVED',
        'meta_template_name' => 'controller_start_template',
        'meta_waba_id' => $instance->meta_waba_id,
    ]);

    $campaign = Campaign::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'contact_list_id' => $list->id,
        'whatsapp_template_id' => $template->id,
        'status' => 'draft',
        'error_threshold_percent' => 10,
    ]);

    $this->actingAs($user)->post("/campanhas/{$campaign->id}/start");

    expect($campaign->fresh()->status)->toBe('sending');
});

test('start action returns error if template not approved', function () {
    $user = makeCampaignUser();
    $campaign = Campaign::factory()->create([
        'tenant_id' => $user->tenantId,
        'status' => 'draft',
    ]);
    $campaign->whatsappTemplate()->associate(
        WhatsappTemplate::factory()->pending()->create(['tenant_id' => $user->tenantId])
    );
    $campaign->save();

    $response = $this->actingAs($user)->post("/campanhas/{$campaign->id}/start");

    $response->assertSessionHasErrors('campaign');
});

test('pause action transitions sending campaign to paused', function () {
    $user = makeCampaignUser();
    $campaign = Campaign::factory()->sending()->create(['tenant_id' => $user->tenantId]);

    $this->actingAs($user)->post("/campanhas/{$campaign->id}/pause");

    expect($campaign->fresh()->status)->toBe('paused');
});

test('resume action transitions paused campaign to sending', function () {
    Queue::fake();
    $user = makeCampaignUser();
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => $user->tenantId,
        'user_id' => $user->id,
    ]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'meta_template_name' => 'controller_resume_template',
        'meta_waba_id' => $instance->meta_waba_id,
        'status' => 'APPROVED',
    ]);
    $campaign = Campaign::factory()->paused()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
        'whatsapp_template_id' => $template->id,
    ]);

    $this->actingAs($user)->post("/campanhas/{$campaign->id}/resume");

    expect($campaign->fresh()->status)->toBe('sending');
});

test('start pause and resume are forbidden for another tenant', function () {
    $user = makeCampaignUser();
    $other = makeCampaignUser();
    $campaign = Campaign::factory()->sending()->create(['tenant_id' => $user->tenantId]);

    $this->actingAs($other)->post("/campanhas/{$campaign->id}/pause")->assertNotFound();
});
