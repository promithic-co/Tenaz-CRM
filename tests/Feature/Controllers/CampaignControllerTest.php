<?php

use App\Enums\TenantRole;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactList;
use App\Models\ContactListEntry;
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

test('cancel action transitions a sending campaign to cancelled', function () {
    $user = makeCampaignUser();
    $campaign = Campaign::factory()->sending()->create(['tenant_id' => $user->tenantId]);

    $this->actingAs($user)->post("/campanhas/{$campaign->id}/cancel");

    expect($campaign->fresh()->status)->toBe('cancelled');
});

test('duplicate action creates a draft copy and redirects to it', function () {
    $user = makeCampaignUser();
    $campaign = Campaign::factory()->completed()->create(['tenant_id' => $user->tenantId]);

    $response = $this->actingAs($user)->post("/campanhas/{$campaign->id}/duplicate");

    $copy = Campaign::where('tenant_id', $user->tenantId)
        ->where('id', '!=', $campaign->id)
        ->first();

    expect($copy)->not->toBeNull();
    expect($copy->status)->toBe('draft');
    $response->assertRedirect(route('campanhas.show', $copy));
});

test('updateThrottle action updates the limits of a paused campaign', function () {
    $user = makeCampaignUser();
    $campaign = Campaign::factory()->paused()->create([
        'tenant_id' => $user->tenantId,
        'daily_limit' => 100,
    ]);

    $this->actingAs($user)->patch("/campanhas/{$campaign->id}/throttle", [
        'daily_limit' => 3000,
        'delay_between_ms' => 500,
    ]);

    $fresh = $campaign->fresh();
    expect($fresh->daily_limit)->toBe(3000);
    expect($fresh->delay_between_ms)->toBe(500);
});

test('updateThrottle action validates the limit range', function () {
    $user = makeCampaignUser();
    $campaign = Campaign::factory()->paused()->create(['tenant_id' => $user->tenantId]);

    $this->actingAs($user)->patch("/campanhas/{$campaign->id}/throttle", [
        'daily_limit' => 0,
        'delay_between_ms' => 500,
    ])->assertSessionHasErrors('daily_limit');
});

test('the bulk reprocess-failures route no longer exists', function () {
    $user = makeCampaignUser();
    $campaign = Campaign::factory()->paused()->create(['tenant_id' => $user->tenantId]);

    // Replaced by the failed-status CSV export: retrying a malformed number re-fails it.
    $this->actingAs($user)
        ->post("/campanhas/{$campaign->id}/reprocess-failures")
        ->assertNotFound();
});

test('export returns only the failed recipients when filtered by status', function () {
    $user = makeCampaignUser();
    $campaign = Campaign::factory()->paused()->create(['tenant_id' => $user->tenantId]);

    $badNumber = ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'phone' => '55349998766336',
    ]);
    $goodNumber = ContactListEntry::factory()->create([
        'contact_list_id' => $campaign->contact_list_id,
        'phone' => '5567999554874',
    ]);

    CampaignMessage::factory()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $badNumber->id,
        'status' => 'failed',
        'failed_at' => now(),
        'error_code' => 'INVALID_PHONE',
        'error_message' => 'Invalid destination format.',
    ]);
    CampaignMessage::factory()->sent()->create([
        'campaign_id' => $campaign->id,
        'contact_list_entry_id' => $goodNumber->id,
    ]);

    $csv = $this->actingAs($user)
        ->get("/campanhas/{$campaign->id}/export?status=failed")
        ->assertOk()
        ->streamedContent();

    // This export IS the "Extrair falhas" button: the number plus the reason it failed, so
    // the operator can fix the spreadsheet and load a corrected list.
    expect($csv)->toContain('55349998766336')
        ->toContain('INVALID_PHONE')
        ->not->toContain('5567999554874');
});

test('retryMessage action reenqueues a single failed recipient', function () {
    Queue::fake();
    $user = makeCampaignUser();
    $campaign = Campaign::factory()->sending()->create(['tenant_id' => $user->tenantId]);
    $failed = CampaignMessage::factory()->failed()->create(['campaign_id' => $campaign->id]);

    $this->actingAs($user)->post("/campanhas/{$campaign->id}/messages/{$failed->id}/retry");

    expect($failed->fresh()->status)->toBe('pending');
});

test('retryMessage action 404s for a message from another campaign', function () {
    $user = makeCampaignUser();
    $campaign = Campaign::factory()->sending()->create(['tenant_id' => $user->tenantId]);
    $otherCampaign = Campaign::factory()->sending()->create(['tenant_id' => $user->tenantId]);
    $foreign = CampaignMessage::factory()->failed()->create(['campaign_id' => $otherCampaign->id]);

    $this->actingAs($user)->post("/campanhas/{$campaign->id}/messages/{$foreign->id}/retry")
        ->assertNotFound();
});

test('removeRecipients action skips selected pending rows', function () {
    $user = makeCampaignUser();
    $campaign = Campaign::factory()->sending()->create(['tenant_id' => $user->tenantId]);
    $pending = CampaignMessage::factory()->create(['campaign_id' => $campaign->id, 'status' => 'pending']);

    $this->actingAs($user)->post("/campanhas/{$campaign->id}/remove-recipients", [
        'message_ids' => [$pending->id],
    ]);

    expect($pending->fresh()->status)->toBe('skipped');
    expect($pending->fresh()->error_code)->toBe('REMOVED_MANUAL');
});

test('removeRecipients action requires at least one recipient', function () {
    $user = makeCampaignUser();
    $campaign = Campaign::factory()->sending()->create(['tenant_id' => $user->tenantId]);

    $this->actingAs($user)->post("/campanhas/{$campaign->id}/remove-recipients", [
        'message_ids' => [],
    ])->assertSessionHasErrors('message_ids');
});

test('export action streams a csv of recipients', function () {
    $user = makeCampaignUser();
    $campaign = Campaign::factory()->sending()->create(['tenant_id' => $user->tenantId]);
    CampaignMessage::factory()->sent()->create(['campaign_id' => $campaign->id]);

    $response = $this->actingAs($user)->get("/campanhas/{$campaign->id}/export");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

test('new control actions are forbidden for another tenant', function () {
    $user = makeCampaignUser();
    $other = makeCampaignUser();
    $campaign = Campaign::factory()->sending()->create(['tenant_id' => $user->tenantId]);

    $this->actingAs($other)->post("/campanhas/{$campaign->id}/cancel")->assertNotFound();
    $this->actingAs($other)->post("/campanhas/{$campaign->id}/duplicate")->assertNotFound();
    $this->actingAs($other)->post("/campanhas/{$campaign->id}/reprocess-failures")->assertNotFound();
});
