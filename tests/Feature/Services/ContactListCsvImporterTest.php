<?php

use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\User;
use App\Services\ContactListCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function makeImportList(): ContactList
{
    $user = User::factory()->create();

    return ContactList::factory()->create(['tenant_id' => $user->tenantId]);
}

function importContent(ContactList $list, string $content): array
{
    $file = UploadedFile::fake()->createWithContent('contacts.csv', $content);

    return app(ContactListCsvImporter::class)->import($list, $file);
}

it('imports rows with comma delimiter', function () {
    $list = makeImportList();

    $result = importContent($list, "telefone,nome\n5511999990001,João\n5511988887777,Maria\n");

    expect($result)->toBe(['imported' => 2, 'skipped' => 0]);
    expect(ContactListEntry::where('contact_list_id', $list->id)->count())->toBe(2);
});

it('imports rows with semicolon delimiter', function () {
    $list = makeImportList();

    $result = importContent($list, "telefone;nome\n5511999990001;João\n5511988887777;Maria\n");

    expect($result)->toBe(['imported' => 2, 'skipped' => 0]);
});

it('strips UTF-8 BOM from the header', function () {
    $list = makeImportList();

    $result = importContent($list, "\xEF\xBB\xBFtelefone,nome\n5511999990001,João\n");

    expect($result)->toBe(['imported' => 1, 'skipped' => 0]);
    expect(ContactListEntry::where('contact_list_id', $list->id)->first()->phone)->toBe('5511999990001');
});

it('converts Windows-1252 names to UTF-8', function () {
    $list = makeImportList();

    // "João" with the latin-1 byte 0xE3 for "ã"
    $content = "telefone,nome\n5511999990001,Jo\xE3o\n";

    importContent($list, $content);

    $entry = ContactListEntry::where('contact_list_id', $list->id)->first();
    expect($entry->name)->toBe('João');
    expect(mb_check_encoding($entry->name, 'UTF-8'))->toBeTrue();
});

it('imports both phone and phone2 columns', function () {
    $list = makeImportList();

    $result = importContent($list, "telefone,telefone2,nome\n5511999990001,5511988887777,João\n");

    expect($result)->toBe(['imported' => 2, 'skipped' => 0]);
    $phones = ContactListEntry::where('contact_list_id', $list->id)->orderBy('phone')->pluck('phone')->all();
    expect($phones)->toBe(['5511988887777', '5511999990001']);
});

it('dedups against existing entries', function () {
    $list = makeImportList();

    ContactListEntry::factory()->create([
        'contact_list_id' => $list->id,
        'phone' => '5511999990001',
    ]);

    $result = importContent($list, "telefone,nome\n5511999990001,Dup\n5511988887777,New\n");

    expect($result)->toBe(['imported' => 1, 'skipped' => 1]);
    expect(ContactListEntry::where('contact_list_id', $list->id)->count())->toBe(2);
});

it('dedups intra-file duplicate phones', function () {
    $list = makeImportList();

    $result = importContent($list, "telefone,nome\n5511999990001,First\n5511999990001,Second\n");

    expect($result)->toBe(['imported' => 1, 'skipped' => 1]);
    expect(ContactListEntry::where('contact_list_id', $list->id)->count())->toBe(1);
});

it('normalizes a local-format phone consistently between entry and synced contact', function () {
    $list = makeImportList();

    // 11-digit local number (no country code) — normalizePhone prepends 55.
    importContent($list, "telefone,nome\n11999990001,João\n");

    $entry = ContactListEntry::where('contact_list_id', $list->id)->first();

    expect($entry->phone)->toBe('5511999990001')
        ->and($entry->contact_id)->not->toBeNull();

    $contact = Contact::withoutGlobalScopes()->find($entry->contact_id);
    expect($contact->phone)->toBe($entry->phone);
});

it('builds extra_data from non-phone non-name columns', function () {
    $list = makeImportList();

    importContent($list, "telefone,nome,cidade\n5511999990001,João,Curitiba\n");

    $entry = ContactListEntry::where('contact_list_id', $list->id)->first();
    expect($entry->extra_data)->toBe(['cidade' => 'Curitiba']);
});

it('returns an error when the phone column is missing', function () {
    $list = makeImportList();

    $result = importContent($list, "nome,cidade\nJoão,Curitiba\n");

    expect($result)->toHaveKey('error');
    expect($result['error'])->toContain('TELEFONE');
});

it('issues a constant number of dedup queries regardless of row count (no per-row exists)', function () {
    $list = makeImportList();

    $rows = "telefone,nome\n";
    for ($i = 0; $i < 25; $i++) {
        $phone = '5511'.str_pad((string) (90000000 + $i), 9, '0', STR_PAD_LEFT);
        $rows .= "{$phone},Lead {$i}\n";
    }

    DB::enableQueryLog();
    importContent($list, $rows);
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    $existsQueries = array_filter($log, function ($q) {
        return str_contains($q['query'], 'exists')
            || (str_contains($q['query'], 'select') && str_contains($q['query'], 'contact_list_entries') && str_contains($q['query'], '"phone" ='));
    });

    expect($existsQueries)->toBeEmpty();
});
