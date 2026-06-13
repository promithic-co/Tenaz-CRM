<?php

namespace App\Console\Commands;

use App\Enums\WhatsAppProvider;
use App\Jobs\SyncMetaTemplatesJob;
use App\Models\WhatsappInstance;
use Illuminate\Console\Command;

class SyncTemplatesCommand extends Command
{
    protected $signature = 'credflow:sync-templates {--instance= : Specific instance ID}';

    protected $description = 'Sync WhatsApp templates for all Meta Cloud instances via Graph API.';

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
                SyncMetaTemplatesJob::dispatch($instance->id);
                $count++;
            }
        });

        $this->info("Dispatched SyncMetaTemplatesJob for {$count} instance(s).");

        return self::SUCCESS;
    }
}
