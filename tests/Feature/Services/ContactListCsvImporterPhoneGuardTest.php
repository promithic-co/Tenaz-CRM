<?php

use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Models\User;
use App\Services\ContactListCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function importCsv(ContactList $list, string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($path, $contents);

    return app(ContactListCsvImporter::class)->import(
        $list,
        new UploadedFile($path, 'contatos.csv', 'text/csv', null, true),
    );
}

test('a cell too short to be a phone is skipped instead of imported', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);

    $result = importCsv($list, <<<'CSV'
    nome,telefone
    Manoel,5567998601348
    Lixo,12345
    Joana,5567996161342
    CSV);

    // The short row used to become a real recipient: it inflated the list count, took a
    // send slot, failed at Meta and then counted toward the auto-pause failure rate.
    expect($result['imported'])->toBe(2)
        ->and($result['skipped'])->toBe(1)
        ->and(ContactListEntry::where('contact_list_id', $list->id)->pluck('phone')->sort()->values()->all())
        ->toBe(['5567996161342', '5567998601348']);
});

test('a foreign number still imports', function () {
    $user = User::factory()->create();
    $list = ContactList::factory()->create(['tenant_id' => $user->tenantId]);

    // The permissive fallback exists for these; the guard must not take them out.
    $result = importCsv($list, <<<'CSV'
    nome,telefone
    Foreign,351912345678
    CSV);

    expect($result['imported'])->toBe(1)
        ->and(ContactListEntry::where('contact_list_id', $list->id)->first()->phone)->toBe('351912345678');
});
