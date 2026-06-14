<?php

use App\Models\Contact;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('backfill links production leads missing a contact and skips sandbox leads', function () {
    $lead = Lead::factory()->create([
        'tenant_id' => 'tenant-bf',
        'whatsapp' => '5511933332222',
        'nome' => 'Fernanda',
        'contact_id' => null,
    ]);

    $sandbox = Lead::factory()->sandbox()->create([
        'tenant_id' => 'tenant-bf',
        'contact_id' => null,
    ]);

    $this->artisan('contacts:backfill')->assertSuccessful();

    $lead->refresh();
    expect($lead->contact_id)->not->toBeNull();

    $contact = Contact::withoutGlobalScopes()->find($lead->contact_id);
    expect($contact->phone)->toBe('5511933332222')
        ->and($contact->name)->toBe('Fernanda');

    $sandbox->refresh();
    expect($sandbox->contact_id)->toBeNull();
});

test('backfill is idempotent and re-running links nothing new', function () {
    Lead::factory()->create([
        'tenant_id' => 'tenant-bf',
        'whatsapp' => '5511922221111',
        'contact_id' => null,
    ]);

    $this->artisan('contacts:backfill')->assertSuccessful();
    $firstCount = Contact::withoutGlobalScopes()->count();

    $this->artisan('contacts:backfill')->assertSuccessful();

    expect(Contact::withoutGlobalScopes()->count())->toBe($firstCount);
});

test('dry run reports without writing', function () {
    Lead::factory()->create([
        'tenant_id' => 'tenant-bf',
        'whatsapp' => '5511911110000',
        'contact_id' => null,
    ]);

    $this->artisan('contacts:backfill --dry-run')->assertSuccessful();

    expect(Contact::withoutGlobalScopes()->count())->toBe(0);
});
