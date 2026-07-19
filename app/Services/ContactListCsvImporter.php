<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactListEntry;
use App\Support\CsvDelimiterDetector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ContactListCsvImporter
{
    /**
     * Header aliases for the primary phone column, in descending priority.
     * `contato` is deliberately last: it is ambiguous (may hold a name) and is
     * only elected when no other phone alias is present.
     *
     * @var list<string>
     */
    private const PHONE_HEADER_ALIASES = [
        'telefone', 'telefone1', 'telefoneprincipal', 'tel', 'tel1',
        'celular', 'celular1', 'cel', 'whatsapp', 'wpp', 'zap',
        'fone', 'numero', 'numerowhatsapp', 'numerotelefone', 'numerocelular',
        'phone', 'phonenumber', 'mobile', 'contato',
    ];

    /**
     * Header aliases for the secondary phone column, in descending priority.
     *
     * @var list<string>
     */
    private const PHONE2_HEADER_ALIASES = [
        'telefone2', 'tel2', 'celular2', 'whatsapp2', 'numero2', 'phone2', 'telefonesecundario',
    ];

    /**
     * Header aliases for the name column, in descending priority.
     *
     * @var list<string>
     */
    private const NAME_HEADER_ALIASES = [
        'nome', 'nomecompleto', 'name', 'fullname', 'cliente', 'nomecliente', 'razaosocial',
    ];

    public function __construct(
        private readonly ContactSyncService $contactSync,
    ) {}

    /**
     * Parse an uploaded CSV and import contacts into the list.
     *
     * Auto-detects the delimiter, normalizes headers (UTF-8, BOM strip, lowercase,
     * trim), reads phone/phone2/name columns, builds extra_data from the remaining
     * columns, and dedups phones against existing entries AND earlier rows in the
     * same file. Phones are normalized through ContactSyncService::normalizePhone —
     * the single canonical normalizer shared with lead and contact syncing — so an
     * imported entry.phone always matches the contact it links to.
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

            // Aggressively normalized headers (accents stripped, separators collapsed)
            // are used ONLY for alias matching. extra_data keeps the original $headers
            // keys, which the campaign param mapping references.
            $matchHeaders = array_map(fn (string $h): string => $this->normalizeHeaderForMatch($h), $headers);

            $phoneCol = $this->resolveColumn($matchHeaders, self::PHONE_HEADER_ALIASES);
            $phone2Col = $this->resolveColumn($matchHeaders, self::PHONE2_HEADER_ALIASES, [$phoneCol]);
            $nameCol = $this->resolveColumn($matchHeaders, self::NAME_HEADER_ALIASES, [$phoneCol, $phone2Col]);

            $hasHeader = true;

            if ($phoneCol === false) {
                // Headerless fallback: if the first row itself is a valid phone, treat
                // the file as having no header (common for .txt with one number per line)
                // and reprocess that row as the first data row.
                if ($this->contactSync->normalizePhone($rawHeaders[0] ?? null) !== null) {
                    $hasHeader = false;
                    $phoneCol = 0;
                    $phone2Col = false;
                    $nameCol = false;
                    $headers = [];
                } else {
                    return ['error' => 'Nenhuma coluna de telefone encontrada. Use um cabeçalho como TELEFONE, CELULAR, WHATSAPP ou NUMERO.'];
                }
            }

            // MEM-5: only preload the existing entries whose phone actually appears in
            // this upload, instead of pluck()-ing the entire destination list into memory
            // (which grows unbounded as a list matures). A first streaming pass collects
            // the file's canonical phones; the dedup set is then scoped to just those.
            $filePhones = [];

            if (! $hasHeader) {
                // Headerless: the first row is data, so scan from the very beginning.
                rewind($handle);
            }

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $row = array_map(fn ($v) => $v !== null ? $this->toUtf8($v) : null, $row);

                foreach ($this->extractPhones($row, $phoneCol, $phone2Col) as $phone) {
                    $filePhones[$phone] = true;
                }
            }

            $seenPhones = $filePhones === []
                ? collect()
                : ContactListEntry::where('contact_list_id', $list->id)
                    // strval: numeric-string array keys are silently cast to int by PHP;
                    // bind them back as strings so the varchar `phone` predicate matches
                    // on PostgreSQL (which won't coerce integer params to text).
                    ->whereIn('phone', array_map('strval', array_keys($filePhones)))
                    ->pluck('phone')
                    ->flip();

            // Rewind for the insert pass; the dedup set still accumulates phones imported
            // earlier in this same file to guard intra-file duplicates.
            rewind($handle);

            if ($hasHeader) {
                fgetcsv($handle, 0, $delimiter);
            }

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

                $phonesToImport = $this->extractPhones($row, $phoneCol, $phone2Col);

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
     * Pull the canonical (normalized) phone numbers out of an already-UTF-8 CSV row.
     * Shared by the dedup-scoping pass and the insert pass so both derive identical keys.
     *
     * @param  array<int, string|null>  $row
     * @return list<string>
     */
    private function extractPhones(array $row, int $phoneCol, int|false $phone2Col): array
    {
        $raw = [$row[$phoneCol] ?? null];

        if ($phone2Col !== false) {
            $raw[] = $row[$phone2Col] ?? null;
        }

        $phones = [];

        foreach ($raw as $value) {
            $phone = $this->contactSync->normalizePhone($value);

            if ($phone !== null) {
                $phones[] = $phone;
            }
        }

        return $phones;
    }

    /**
     * Normalize a header for alias matching: strip accents and collapse any
     * whitespace/underscore/hyphen/dot separators so that "Número  Whatsapp",
     * "numero_whatsapp" and "NUMERO-WHATSAPP" all converge to "numerowhatsapp".
     * The input is already lowercased/trimmed/BOM-stripped.
     */
    private function normalizeHeaderForMatch(string $header): string
    {
        $header = Str::ascii($header);
        $header = preg_replace('/[\s_\-.]+/', '', $header) ?? $header;

        return strtolower($header);
    }

    /**
     * Resolve a column index by walking the aliases in descending priority and
     * returning the first (leftmost) column whose normalized header matches,
     * skipping any column indexes already claimed by another field.
     *
     * @param  list<string>  $matchHeaders  headers normalized via normalizeHeaderForMatch
     * @param  list<string>  $aliases  aliases in descending priority
     * @param  list<int|false>  $exclude  column indexes already claimed
     */
    private function resolveColumn(array $matchHeaders, array $aliases, array $exclude = []): int|false
    {
        foreach ($aliases as $alias) {
            foreach ($matchHeaders as $i => $header) {
                if ($header === $alias && ! in_array($i, $exclude, true)) {
                    return $i;
                }
            }
        }

        return false;
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
