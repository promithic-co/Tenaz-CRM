<?php

namespace App\Console\Commands;

use App\Enums\WhatsAppProvider;
use App\Jobs\SyncMetaHealthJob;
use App\Models\WhatsappInstance;
use Illuminate\Console\Command;

class SyncMetaHealthCommand extends Command
{
    protected $signature = 'credflow:sync-meta-health {--instance= : Specific instance ID}';

    protected $description = 'Refresh the Meta messaging health snapshot for all Meta Cloud instances via Graph API.';

    public function handle(): int
    {
        $instanceId = $this->option('instance');

        $query = WhatsappInstance::withoutGlobalScope('tenant')
            ->where('provider', WhatsAppProvider::MetaCloud->value);

        if ($instanceId) {
            $query->where('id', (int) $instanceId);
        }

        $count = 0;

        $query->chunkById(100, function ($instances) use (&$count): void {
            foreach ($instances as $instance) {
                SyncMetaHealthJob::dispatch($instance->id);
                $count++;
            }
        });

        $this->info("Dispatched SyncMetaHealthJob for {$count} instance(s).");

        return self::SUCCESS;
    }
}
