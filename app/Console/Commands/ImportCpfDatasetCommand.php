<?php

namespace App\Console\Commands;

use App\Services\CpfDatasetImporter;
use Illuminate\Console\Command;

class ImportCpfDatasetCommand extends Command
{
    protected $signature = 'laboratory:import-cpf-dataset
                            {file : Path to the CSV or JSON file}
                            {--name= : Dataset name (defaults to filename)}
                            {--description= : Dataset description}
                            {--prefetch : Pre-fetch Promosys data for all CPFs}';

    protected $description = 'Import a CPF dataset for stress testing';

    public function handle(CpfDatasetImporter $importer): int
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $name = $this->option('name') ?? pathinfo($filePath, PATHINFO_FILENAME);
        $description = $this->option('description');
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $this->info("Importing dataset '{$name}' from {$filePath}...");

        $dataset = match ($extension) {
            'json' => $importer->importFromJson($filePath, $name, $description),
            default => $importer->importFromCsv($filePath, $name, $description),
        };

        $this->info("Imported {$dataset->total_entries} CPFs into dataset '{$dataset->name}'.");

        if ($this->option('prefetch')) {
            $this->info('Pre-fetching Promosys data...');
            $updated = $importer->prefetchPromosysData($dataset);
            $this->info("{$updated} entries pre-fetched from Promosys.");
        }

        return self::SUCCESS;
    }
}
