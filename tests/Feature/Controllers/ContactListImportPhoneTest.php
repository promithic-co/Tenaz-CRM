<?php

use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/**
 * Characterization test for the CSV import phone-normalization contract.
 *
 * Locks the import/skip behavior of ContactListController::importCsv after the C.1
 * reconciliation routed normalization through the canonical
 * ContactSyncService::normalizePhone. That normalizer is permissive by design: it
 * tries the E.164 validator first, then falls back to digits-only so foreign and
 * imperfect numbers still resolve to a stable contact key. Malformed/out-of-range
 * numbers are NOT dropped here — they are filtered later at send time by
 * PhoneNumberValidator. These tests lock that permissive contract.
 *
 * @return list<string> sorted, distinct phone values persisted for the list
 */
function importPhonesFor(User $user, string $csvBody): array
{
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);

    $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvBody);

    test()->actingAs($user)->post("/listas-contato/{$list->id}/import-csv", [
        'file' => $file,
    ]);

    return ContactListEntry::where('contact_list_id', $list->id)
        ->orderBy('phone')
        ->pluck('phone')
        ->all();
}

it('normalizes BR mobile with country code unchanged', function () {
    $user = User::factory()->create();

    $phones = importPhonesFor($user, "telefone,nome\n5511999990001,Com CC\n");

    expect($phones)->toBe(['5511999990001']);
});

it('adds country code to BR mobile without it', function () {
    $user = User::factory()->create();

    $phones = importPhonesFor($user, "telefone,nome\n11999990001,Sem CC\n");

    expect($phones)->toBe(['5511999990001']);
});

it('adds country code to BR landline (10 local digits)', function () {
    $user = User::factory()->create();

    $phones = importPhonesFor($user, "telefone,nome\n1133334444,Fixo\n");

    expect($phones)->toBe(['551133334444']);
});

it('keeps too-short numbers as digits-only (filtered later at send time)', function () {
    $user = User::factory()->create();

    // 999990001 -> not valid E.164 -> digits-only fallback -> kept verbatim
    $phones = importPhonesFor($user, "telefone,nome\n999990001,Curto\n");

    expect($phones)->toBe(['999990001']);
});

it('keeps foreign-looking 11-digit numbers as digits-only when not valid BR', function () {
    $user = User::factory()->create();

    // 12025550123 -> not a valid BR number -> digits-only fallback -> kept verbatim (no CC forced)
    $phones = importPhonesFor($user, "telefone,nome\n12025550123,Foreign\n");

    expect($phones)->toBe(['12025550123']);
});

it('skips garbage non-digit input', function () {
    $user = User::factory()->create();

    $phones = importPhonesFor($user, "telefone,nome\nabc-def,Lixo\n");

    expect($phones)->toBe([]);
});

it('keeps numbers longer than 13 digits as digits-only', function () {
    $user = User::factory()->create();

    // 14 digits -> not valid E.164 -> digits-only fallback -> kept; filtered at send time
    $phones = importPhonesFor($user, "telefone,nome\n55119999900011,Longo\n");

    expect($phones)->toBe(['55119999900011']);
});

it('imports a representative mixed CSV under the permissive contract', function () {
    $user = User::factory()->create();

    $csv = "telefone,nome\n"
        ."5511999990001,BR com CC\n"
        ."11988887777,BR sem CC\n"
        ."1133334444,BR fixo\n"
        ."999990001,Curto\n"
        ."abc,Lixo\n";

    $phones = importPhonesFor($user, $csv);

    // Valid BR numbers normalize to E.164; the short number falls back to digits-only
    // and is still imported. Only the non-digit "abc" row is skipped. Sorted by phone.
    expect($phones)->toBe([
        '551133334444',
        '5511988887777',
        '5511999990001',
        '999990001',
    ]);
});
