<?php

use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('opting a lead out writes consent on the contact and the list entry', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);

    $contact = Contact::factory()->create([
        'tenant_id' => $user->tenantId,
        'phone' => '5511999990001',
        'opt_in_status' => Contact::OPT_PENDING,
    ]);
    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $list->id,
        'phone' => '5511999990001',
        'opt_in_status' => 'pending',
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990001',
        'status' => 'novo',
    ]);

    $lead->update(['status' => 'optou_sair']);

    expect($contact->fresh()->opt_in_status)->toBe(Contact::OPT_OUT)
        ->and($contact->fresh()->opt_out_at)->not->toBeNull()
        ->and($entry->fresh()->opt_in_status)->toBe('opted_out')
        ->and($entry->fresh()->opt_out_at)->not->toBeNull();
});

test('the send gate suppresses a recipient the lead opted out for', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);

    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $list->id,
        'phone' => '5511999990009',
        'opt_in_status' => 'opted_in',
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990009',
        'status' => 'novo',
    ]);

    $lead->update(['status' => 'optou_sair']);

    // The predicate SendCampaignMessageJob evaluates right before calling Meta.
    expect($entry->fresh()->opt_in_status)->toBe('opted_out');
});

test('consent propagates across the BR 9th digit mismatch', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);

    // Imported with the 9th digit; the inbound webhook created the lead without it.
    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $list->id,
        'phone' => '5567998601348',
        'opt_in_status' => 'pending',
    ]);
    $contact = Contact::factory()->create([
        'tenant_id' => $user->tenantId,
        'phone' => '5567998601348',
        'opt_in_status' => Contact::OPT_PENDING,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '556798601348',
        'status' => 'novo',
    ]);

    $lead->update(['status' => 'optou_sair']);

    expect($entry->fresh()->opt_in_status)->toBe('opted_out')
        ->and($contact->fresh()->opt_in_status)->toBe(Contact::OPT_OUT);
});

test('every duplicate contact for the same person is opted out', function () {
    $user = User::factory()->create();

    // The 9th-digit mismatch mints one contact per form; suppressing only one of them
    // leaves the other free to be sent to.
    $imported = Contact::factory()->create([
        'tenant_id' => $user->tenantId,
        'phone' => '5567996161342',
        'opt_in_status' => Contact::OPT_PENDING,
    ]);
    $fromInbound = Contact::factory()->create([
        'tenant_id' => $user->tenantId,
        'phone' => '556796161342',
        'opt_in_status' => Contact::OPT_PENDING,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '556796161342',
        'status' => 'novo',
    ]);

    $lead->update(['status' => 'optou_sair']);

    expect($imported->fresh()->opt_in_status)->toBe(Contact::OPT_OUT)
        ->and($fromInbound->fresh()->opt_in_status)->toBe(Contact::OPT_OUT);
});

test('another tenant with the same phone keeps its consent', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $theirs = Contact::factory()->create([
        'tenant_id' => $other->tenantId,
        'phone' => '5511999990002',
        'opt_in_status' => Contact::OPT_IN,
    ]);
    $otherList = ContactList::factory()->create(['tenant_id' => $other->tenantId]);
    $theirEntry = ContactListEntry::factory()->create([
        'contact_list_id' => $otherList->id,
        'phone' => '5511999990002',
        'opt_in_status' => 'opted_in',
    ]);

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990002',
        'status' => 'novo',
    ]);

    $lead->update(['status' => 'optou_sair']);

    expect($theirs->fresh()->opt_in_status)->toBe(Contact::OPT_IN)
        ->and($theirEntry->fresh()->opt_in_status)->toBe('opted_in');
});

test('moving the lead back out of optou_sair does not restore consent', function () {
    $user = User::factory()->create();

    $contact = Contact::factory()->create([
        'tenant_id' => $user->tenantId,
        'phone' => '5511999990003',
        'opt_in_status' => Contact::OPT_PENDING,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990003',
        'status' => 'novo',
    ]);

    $lead->update(['status' => 'optou_sair']);
    $lead->update(['status' => 'novo']);

    // Revocation is the person's decision; only an explicit opt-in may undo it.
    expect($contact->fresh()->opt_in_status)->toBe(Contact::OPT_OUT);
});

test('the backfill command repairs a lead that opted out before propagation existed', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '556798601348',
        'status' => 'novo',
    ]);

    // Rows created after the status change, reproducing the historical state: the lead is
    // already optou_sair but no consent was ever written for it.
    $lead->update(['status' => 'optou_sair']);

    $contact = Contact::factory()->create([
        'tenant_id' => $user->tenantId,
        'phone' => '5567998601348',
        'opt_in_status' => Contact::OPT_PENDING,
    ]);
    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $list->id,
        'phone' => '5567998601348',
        'opt_in_status' => 'pending',
    ]);

    $this->artisan('consent:backfill-opt-outs')->assertExitCode(0);

    expect($contact->fresh()->opt_in_status)->toBe(Contact::OPT_OUT)
        ->and($entry->fresh()->opt_in_status)->toBe('opted_out');
});

test('the backfill command leaves consent alone on a dry run', function () {
    $user = User::factory()->create();

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990005',
        'status' => 'novo',
    ]);
    $lead->update(['status' => 'optou_sair']);

    $contact = Contact::factory()->create([
        'tenant_id' => $user->tenantId,
        'phone' => '5511999990005',
        'opt_in_status' => Contact::OPT_PENDING,
    ]);

    $this->artisan('consent:backfill-opt-outs', ['--dry-run' => true])->assertExitCode(0);

    expect($contact->fresh()->opt_in_status)->toBe(Contact::OPT_PENDING);
});

test('a non-terminal status change leaves consent untouched', function () {
    $user = User::factory()->create();

    $contact = Contact::factory()->create([
        'tenant_id' => $user->tenantId,
        'phone' => '5511999990004',
        'opt_in_status' => Contact::OPT_IN,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511999990004',
        'status' => 'novo',
    ]);

    $lead->update(['status' => 'qualificado']);

    expect($contact->fresh()->opt_in_status)->toBe(Contact::OPT_IN)
        ->and($contact->fresh()->opt_out_at)->toBeNull();
});
