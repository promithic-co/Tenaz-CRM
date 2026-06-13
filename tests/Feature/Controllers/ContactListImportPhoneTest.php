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
 * Locks the CURRENT import/skip behavior of ContactListController::importCsv so the
 * C.1 reconciliation (controller normalizePhone -> ContactSyncService) cannot silently
 * change which rows are imported or what phone value they dedup on.
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

it('skips too-short numbers that fall below 12 digits after country code', function () {
    $user = User::factory()->create();

    // 999990001 -> 55 + 9 digits = 11 digits = below the 12 minimum -> skipped
    $phones = importPhonesFor($user, "telefone,nome\n999990001,Curto\n");

    expect($phones)->toBe([]);
});

it('treats foreign-looking 11-digit numbers as BR by forcing country code', function () {
    $user = User::factory()->create();

    // 12025550123 (11 digits) -> <=11 -> 55 + 11 = 13 digits -> accepted as-is
    $phones = importPhonesFor($user, "telefone,nome\n12025550123,Foreign\n");

    expect($phones)->toBe(['5512025550123']);
});

it('skips garbage non-digit input', function () {
    $user = User::factory()->create();

    $phones = importPhonesFor($user, "telefone,nome\nabc-def,Lixo\n");

    expect($phones)->toBe([]);
});

it('skips numbers longer than 13 digits', function () {
    $user = User::factory()->create();

    // 14 digits already > 11 so no CC added, then >13 -> rejected
    $phones = importPhonesFor($user, "telefone,nome\n55119999900011,Longo\n");

    expect($phones)->toBe([]);
});

it('imports a representative mixed CSV with the same import/skip outcome', function () {
    $user = User::factory()->create();

    $csv = "telefone,nome\n"
        ."5511999990001,BR com CC\n"
        ."11988887777,BR sem CC\n"
        ."1133334444,BR fixo\n"
        ."999990001,Curto\n"
        ."abc,Lixo\n";

    $phones = importPhonesFor($user, $csv);

    expect($phones)->toBe([
        '551133334444',
        '5511988887777',
        '5511999990001',
    ]);
});
