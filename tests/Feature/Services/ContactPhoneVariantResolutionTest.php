<?php

use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Models\User;
use App\Services\ContactSyncService;
use App\Services\WhatsApp\PhoneNumberValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('canonical folds both BR mobile spellings onto the 13-digit form', function () {
    expect(PhoneNumberValidator::canonical('556798601348'))->toBe('5567998601348')
        ->and(PhoneNumberValidator::canonical('5567998601348'))->toBe('5567998601348');
});

test('canonical leaves a landline and a malformed number alone', function () {
    // The landline has no 9th-digit sibling; folding it would merge two different people.
    expect(PhoneNumberValidator::canonical('551133334444'))->toBe('551133334444')
        // Garbage keeps its digits: the contacts domain is permissive by design and the
        // send-time validator is what rejects it.
        ->and(PhoneNumberValidator::canonical('12345'))->toBe('12345')
        ->and(PhoneNumberValidator::canonical(''))->toBeNull();
});

test('the same subscriber resolves to one contact across both spellings', function () {
    $user = User::factory()->create();
    $service = app(ContactSyncService::class);

    $imported = $service->resolveContact($user->tenantId, '5567998601348', ['name' => 'Manoel']);
    $fromWebhook = $service->resolveContact($user->tenantId, '556798601348', ['name' => 'Manoel']);

    expect($fromWebhook->id)->toBe($imported->id)
        ->and(Contact::withoutGlobalScopes()->where('tenant_id', $user->tenantId)->count())->toBe(1);
});

test('a contact created from the 9-less webhook form is stored canonically', function () {
    $user = User::factory()->create();

    $contact = app(ContactSyncService::class)->resolveContact($user->tenantId, '556798601348');

    // Storing the arriving form is what let the next CSV import mint a second row.
    expect($contact->phone)->toBe('5567998601348');
});

test('a landline does not resolve onto the mobile sharing its trailing digits', function () {
    $user = User::factory()->create();
    $service = app(ContactSyncService::class);

    $mobile = $service->resolveContact($user->tenantId, '5511933334444');
    $landline = $service->resolveContact($user->tenantId, '551133334444');

    expect($landline->id)->not->toBe($mobile->id);
});

test('another tenant with the same subscriber keeps its own contact', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $service = app(ContactSyncService::class);

    $ours = $service->resolveContact($user->tenantId, '5567998601348');
    $theirs = $service->resolveContact($other->tenantId, '556798601348');

    expect($theirs->id)->not->toBe($ours->id);
});

test('a lead and a list entry for the same person share one contact', function () {
    $user = User::factory()->create();
    $service = app(ContactSyncService::class);

    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);
    $entry = ContactListEntry::factory()->create([
        'contact_list_id' => $list->id,
        'phone' => '5567996161342',
        'contact_id' => null,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '556796161342',
        'contact_id' => null,
    ]);

    $fromEntry = $service->syncFromEntry($entry);
    $fromLead = $service->syncFromLead($lead);

    expect($fromLead->id)->toBe($fromEntry->id);
});

test('the dedupe command merges an existing duplicate pair onto one contact', function () {
    $user = User::factory()->create();

    $imported = Contact::factory()->create([
        'tenant_id' => $user->tenantId,
        'phone' => '5567998601348',
        'name' => 'MANOEL',
        'email' => null,
        'opt_in_status' => Contact::OPT_PENDING,
    ]);
    $fromWebhook = Contact::factory()->create([
        'tenant_id' => $user->tenantId,
        'phone' => '556798601348',
        'name' => 'Manoel',
        'email' => 'manoel@example.com',
        'opt_in_status' => Contact::OPT_OUT,
        'opt_out_at' => now(),
    ]);

    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '556798601348',
        'contact_id' => $fromWebhook->id,
    ]);

    $this->artisan('contacts:dedupe-phone-variants')->assertExitCode(0);

    expect($imported->fresh()->deleted_at)->toBeNull()
        ->and($fromWebhook->fresh()->deleted_at)->not->toBeNull()
        ->and($lead->fresh()->contact_id)->toBe($imported->id)
        // The loser's opt-out binds the survivor; anything else re-opens a blocked person.
        ->and($imported->fresh()->opt_in_status)->toBe(Contact::OPT_OUT)
        // A field the survivor lacked is carried over rather than dropped.
        ->and($imported->fresh()->email)->toBe('manoel@example.com');
});

test('the dedupe command writes nothing on a dry run', function () {
    $user = User::factory()->create();

    Contact::factory()->create(['tenant_id' => $user->tenantId, 'phone' => '5567998601348']);
    $loser = Contact::factory()->create(['tenant_id' => $user->tenantId, 'phone' => '556798601348']);

    $this->artisan('contacts:dedupe-phone-variants', ['--dry-run' => true])->assertExitCode(0);

    expect($loser->fresh()->deleted_at)->toBeNull()
        ->and(Contact::withoutGlobalScopes()->where('tenant_id', $user->tenantId)->count())->toBe(2);
});

test('the dedupe command leaves distinct subscribers untouched', function () {
    $user = User::factory()->create();

    $mobile = Contact::factory()->create(['tenant_id' => $user->tenantId, 'phone' => '5511933334444']);
    $landline = Contact::factory()->create(['tenant_id' => $user->tenantId, 'phone' => '551133334444']);

    $this->artisan('contacts:dedupe-phone-variants')->assertExitCode(0);

    expect($mobile->fresh()->deleted_at)->toBeNull()
        ->and($landline->fresh()->deleted_at)->toBeNull();
});

test('the dedupe command is idempotent', function () {
    $user = User::factory()->create();

    Contact::factory()->create(['tenant_id' => $user->tenantId, 'phone' => '5567998601348']);
    Contact::factory()->create(['tenant_id' => $user->tenantId, 'phone' => '556798601348']);

    $this->artisan('contacts:dedupe-phone-variants')->assertExitCode(0);
    $this->artisan('contacts:dedupe-phone-variants')->assertExitCode(0)->expectsOutputToContain('No duplicated contacts found.');
});
