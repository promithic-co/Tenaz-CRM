<?php

namespace App\Actions;

use App\Models\ContactList;
use App\Services\ContactListCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;

class ImportContactListCsvAction
{
    public function __construct(
        private readonly ContactListCsvImporter $importer,
    ) {}

    /**
     * Import a CSV file into the list and build the redirect response.
     *
     * On failure, returns back() with a `file` validation error. On success,
     * redirects to the list show page with the import summary flash.
     */
    public function execute(ContactList $list, UploadedFile $file): RedirectResponse
    {
        $result = $this->importer->import($list, $file);

        if (isset($result['error'])) {
            return back()->withErrors(['file' => $result['error']]);
        }

        $imported = $result['imported'];
        $skipped = $result['skipped'];

        return redirect()->route('listas-contato.show', $list)
            ->with('success', "Importação concluída: {$imported} importados, {$skipped} ignorados.");
    }
}
