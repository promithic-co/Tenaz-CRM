<?php

namespace App\Services;

use App\Models\CpfDataset;
use App\Models\CpfDatasetEntry;
use App\Models\User;
use App\Support\CsvDelimiterDetector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CpfDatasetImporter
{
    public function __construct(
        private readonly CreditoQualificacaoService $qualificacaoService,
    ) {}

    public function importFromCsv(string $filePath, string $datasetName, ?string $description = null, ?int $userId = null): CpfDataset
    {
        $userId = $userId ?? User::query()->first()?->id;

        $dataset = CpfDataset::create([
            'user_id' => $userId,
            'name' => $datasetName,
            'description' => $description,
            'total_entries' => 0,
        ]);

        $handle = fopen($filePath, 'r');
        $delimiter = CsvDelimiterDetector::detect($handle);
        $headers = fgetcsv($handle, 0, $delimiter);
        if ($headers === false) {
            fclose($handle);

            return $dataset->refresh();
        }
        $headers = array_map(function ($h) {
            $h = trim($h);
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);

            return strtolower($h);
        }, $headers);
        $numHeaders = count($headers);

        if (! in_array('cpf', $headers, true)) {
            fclose($handle);

            return $dataset->refresh();
        }

        $count = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row = array_pad(array_slice($row, 0, $numHeaders), $numHeaders, '');
            $data = array_combine($headers, $row);
            $cpf = preg_replace('/\D/', '', $data['cpf'] ?? '');

            $cpf = $this->normalizeCpfLength($cpf);
            if ($cpf === null || ! $this->isValidCpf($cpf)) {
                continue;
            }

            $qualifiedJson = null;
            if (isset($data['qualified_json']) && ! empty($data['qualified_json'])) {
                $qualifiedJson = json_decode($data['qualified_json'], true);
            }

            CpfDatasetEntry::create([
                'cpf_dataset_id' => $dataset->id,
                'cpf' => $cpf,
                'nome' => $data['nome'] ?? '',
                'status_expected' => $data['status_expected'] ?? 'QUALIFICADO',
                'qualified_json' => $qualifiedJson,
                'promosys_raw' => null,
            ]);

            $count++;
        }

        fclose($handle);
        $dataset->update(['total_entries' => $count]);

        return $dataset->refresh();
    }

    public function importFromJson(string $filePath, string $datasetName, ?string $description = null, ?int $userId = null): CpfDataset
    {
        $userId = $userId ?? User::query()->first()?->id;

        $dataset = CpfDataset::create([
            'user_id' => $userId,
            'name' => $datasetName,
            'description' => $description,
            'total_entries' => 0,
        ]);

        $entries = json_decode(file_get_contents($filePath), true);
        $count = 0;

        foreach ($entries as $entry) {
            $cpf = preg_replace('/\D/', '', $entry['cpf'] ?? '');
            $cpf = $this->normalizeCpfLength($cpf);
            if ($cpf === null || ! $this->isValidCpf($cpf)) {
                continue;
            }

            CpfDatasetEntry::create([
                'cpf_dataset_id' => $dataset->id,
                'cpf' => $cpf,
                'nome' => $entry['nome'] ?? '',
                'status_expected' => $entry['status_expected'] ?? 'QUALIFICADO',
                'qualified_json' => $entry['qualified_json'] ?? null,
                'promosys_raw' => $entry['promosys_raw'] ?? null,
            ]);

            $count++;
        }

        $dataset->update(['total_entries' => $count]);

        return $dataset->refresh();
    }

    public function prefetchPromosysData(CpfDataset $dataset): int
    {
        $webhookUrl = config('services.credflow.webhook_consulta');
        $entries = $dataset->entries()->whereNull('qualified_json')->get();
        $updated = 0;

        foreach ($entries as $entry) {
            try {
                $response = Http::post($webhookUrl, ['cpf' => $entry->cpf]);

                if ($response->successful()) {
                    $raw = $response->json();
                    $qualified = $this->qualificacaoService->qualificar($raw);

                    $entry->update([
                        'promosys_raw' => $raw,
                        'qualified_json' => $qualified,
                        'status_expected' => $qualified['status'] ?? 'DESQUALIFICADO',
                    ]);

                    $updated++;
                }
            } catch (\Throwable $e) {
                Log::warning('cpf_dataset.prefetch_failed', [
                    'cpf' => $entry->cpf,
                    'error' => $e->getMessage(),
                ]);
            }

            sleep(2);
        }

        return $updated;
    }

    /**
     * Normalize CPF to 11 digits. Excel often drops leading zero when saving as CSV.
     */
    private function normalizeCpfLength(string $cpf): ?string
    {
        $len = strlen($cpf);
        if ($len === 11) {
            return $cpf;
        }
        if ($len === 10) {
            return '0'.$cpf;
        }

        return null;
    }

    private function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($i = 9; $i <= 10; $i++) {
            $sum = 0;
            for ($j = 0; $j < $i; $j++) {
                $sum += (int) $cpf[$j] * ($i + 1 - $j);
            }
            $remainder = ($sum * 10) % 11;
            $remainder = $remainder === 10 ? 0 : $remainder;

            if ((int) $cpf[$i] !== $remainder) {
                return false;
            }
        }

        return true;
    }
}
