<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StartScheduledCampaignsCommand extends Command
{
    protected $signature = 'credflow:start-scheduled-campaigns';

    protected $description = 'Start campaigns that are scheduled and due to run';

    public function handle(CampaignService $service): int
    {
        $lock = Cache::lock('credflow:start-scheduled-campaigns', 55);

        if (! $lock->get()) {
            $this->info('Another instance is already running.');

            return self::SUCCESS;
        }

        try {
            return $this->startDueCampaigns($service);
        } finally {
            $lock->release();
        }
    }

    private function startDueCampaigns(CampaignService $service): int
    {
        $campaigns = Campaign::scheduledAndReady()
            ->with(['whatsappTemplate', 'contactList'])
            ->get();

        if ($campaigns->isEmpty()) {
            $this->info('No scheduled campaigns due to start.');

            return self::SUCCESS;
        }

        $started = 0;

        foreach ($campaigns as $campaign) {
            try {
                $service->start($campaign);
                $started++;
                $this->info("Started campaign [{$campaign->id}]: {$campaign->name}");
            } catch (\Throwable $e) {
                $this->error("Failed to start campaign [{$campaign->id}]: {$e->getMessage()}");
                Log::warning('credflow:start-scheduled-campaigns failed for campaign', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Started {$started} campaign(s).");

        return self::SUCCESS;
    }
}
