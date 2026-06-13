<?php

namespace App\Console\Commands;

use App\Models\NicheTemplate;
use Illuminate\Console\Command;

class ApplyNicheTemplateCommand extends Command
{
    protected $signature = 'credflow:apply-template {slug} {tenant_id} {--agent-id= : Optional agent ID to scope prompt templates and tools}';

    protected $description = 'Apply a niche template to a tenant, creating prompt templates, tool definitions, status machine, and custom fields.';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $tenantId = $this->argument('tenant_id');
        $agentId = $this->option('agent-id') ? (int) $this->option('agent-id') : null;

        $template = NicheTemplate::where('slug', $slug)->first();

        if (! $template) {
            $this->error("Niche template '{$slug}' not found.");
            $this->line('Available templates:');
            NicheTemplate::pluck('name', 'slug')->each(fn ($name, $s) => $this->line("  {$s}: {$name}"));

            return Command::FAILURE;
        }

        $this->info("Applying template '{$template->name}' to tenant {$tenantId}...");

        $template->apply($tenantId, $agentId);

        $this->info('Template applied successfully.');

        return Command::SUCCESS;
    }
}
