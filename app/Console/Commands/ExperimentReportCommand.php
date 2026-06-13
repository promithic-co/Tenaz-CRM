<?php

namespace App\Console\Commands;

use App\Models\PromptExperiment;
use Illuminate\Console\Command;

class ExperimentReportCommand extends Command
{
    protected $signature = 'credflow:experiment-report {slug? : Experiment slug (omit to list all)}';

    protected $description = 'Show A/B experiment conversion results per variant.';

    public function handle(): int
    {
        $slug = $this->argument('slug');

        if (! $slug) {
            $experiments = PromptExperiment::all(['id', 'slug', 'name', 'prompt_type', 'is_active']);
            $this->table(
                ['ID', 'Slug', 'Name', 'Type', 'Active'],
                $experiments->map(fn ($e) => [
                    $e->id,
                    $e->slug,
                    $e->name,
                    $e->prompt_type,
                    $e->is_active ? 'yes' : 'no',
                ])
            );

            return Command::SUCCESS;
        }

        $experiment = PromptExperiment::where('slug', $slug)->first();

        if (! $experiment) {
            $this->error("Experiment '{$slug}' not found.");

            return Command::FAILURE;
        }

        $this->info("Experiment: {$experiment->name} ({$experiment->slug})");
        $this->line("Type: {$experiment->prompt_type} | Active: ".($experiment->is_active ? 'yes' : 'no'));
        $this->newLine();

        $results = $experiment->results();

        if ($results->isEmpty()) {
            $this->warn('No leads assigned to this experiment yet.');

            return Command::SUCCESS;
        }

        $rows = $results->map(fn ($data, $variant) => [
            $variant,
            $data['assigned'],
            $data['converted'],
            $data['rate'].'%',
        ])->values()->all();

        $this->table(['Variant', 'Assigned', 'Converted', 'Rate'], $rows);

        return Command::SUCCESS;
    }
}
