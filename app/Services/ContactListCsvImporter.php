<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Support\CsvDelimiterDetector;
use Illuminate\Http\UploadedFile;

class ContactListCsvImporter
{
    public function __construct(
        private readonly ContactSyncService $contactSync,
    ) {}

    /**
     * Parse an uploaded CSV and import contacts into the list.
     *
     * Auto-detects the delimiter, normalizes headers (UTF-8, BOM strip, lowercase,
     * trim), reads phone/phone2/name columns, builds extra_data from the remaining
     * columns, and dedups phones against existing entries AND earlier rows in the
     * same file. Phones are normalized with the strict Brazilian import rule.
     *
     * Returns an array shaped as:
     *   ['error' => string]                          on failure (file/format/column)
     *   ['imported' => int, 'skipped' => int]        on success
     *
     * @return array{error: string}|array{imported: int, skipped: int}
     */
    public function import(ContactList $list, UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return ['error' => 'Não foi possível abrir o arquivo.'];
        }

        try {
            $delimiter = CsvDelimiterDetector::detect($handle);

            $rawHeaders = fgetcsv($handle, 0, $delimiter);

            if (! $rawHeaders) {
                return ['error' => 'Arquivo CSV vazio ou inválido.'];
            }

            $headers = array_map(function (string $h): string {
                $h = $this->toUtf8($h);
                $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);

                return strtolower(trim($h));
            }, $rawHeaders);

            $phoneCol = array_search('telefone', $headers) !== false ? array_search('telefone', $headers) : array_search('phone', $headers);
            $phone2Col = array_search('telefone2', $headers) !== false ? array_search('telefone2', $headers) : array_search('phone2', $headers);
            $nameCol = array_search('nome', $headers) !== false ? array_search('nome', $headers) : array_search('name', $headers);

            if ($phoneCol === false) {
                return ['error' => 'Coluna "TELEFONE" não encontrada. Verifique o padrão da planilha.'];
            }

            // N+1 fix: preload existing phones once, dedup in-memory. The map also
            // accumulates phones imported earlier in this file to guard intra-file dups.
            $seenPhones = ContactListEntry::where('contact_list_id', $list->id)
                ->pluck('phone')
                ->flip();

            $imported = 0;
            $skipped = 0;

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $row = array_map(fn ($v) => $v !== null ? $this->toUtf8($v) : null, $row);

                $name = ($nameCol !== false && isset($row[$nameCol])) ? (string) $row[$nameCol] : null;

                $extraData = [];

                foreach ($headers as $i => $header) {
                    if ($i === $phoneCol || $i === $phone2Col || $i === $nameCol) {
                        continue;
                    }

                    if (isset($row[$i]) && $row[$i] !== '') {
                        $extraData[$header] = $row[$i];
                    }
                }

                $rawPhones = [isset($row[$phoneCol]) ? $row[$phoneCol] : null];

                if ($phone2Col !== false) {
                    $rawPhones[] = isset($row[$phone2Col]) ? $row[$phone2Col] : null;
                }

                $phonesToImport = [];

                foreach ($rawPhones as $raw) {
                    $phone = $this->contactSync->normalizeBrazilianPhone($raw);

                    if ($phone !== null) {
                        $phonesToImport[] = $phone;
                    }
                }

                if (empty($phonesToImport)) {
                    $skipped++;

                    continue;
                }

                foreach ($phonesToImport as $phone) {
                    if ($seenPhones->has($phone)) {
                        $skipped++;

                        continue;
                    }

                    $entry = ContactListEntry::create([
                        'contact_list_id' => $list->id,
                        'phone' => $phone,
                        'name' => $name,
                        'opt_in_status' => 'pending',
                        'extra_data' => ! empty($extraData) ? $extraData : null,
                    ]);

                    $this->contactSync->syncFromEntry($entry, Contact::SOURCE_CSV_IMPORT);

                    $seenPhones->put($phone, true);
                    $imported++;
                }
            }
        } finally {
            fclose($handle);
        }

        $list->refreshEntriesCount();

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Ensure a string is valid UTF-8.
     * If not, assume Windows-1252 (common for Brazilian Excel exports) and convert.
     */
    private function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
