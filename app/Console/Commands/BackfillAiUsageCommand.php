<?php

namespace App\Console\Commands;

use App\Jobs\LogAiUsageJob;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BackfillAiUsageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credflow:backfill-ai-usage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfills historical AI token usage into the daily tracking table.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting AI usage backfill...');

        $count = 0;

        DB::table('agent_conversation_messages')
            ->whereNotNull('usage')
            ->where('role', 'assistant')
            ->orderBy('id')
            ->chunkById(1000, function (Collection $messages) use (&$count): void {
                $leads = Lead::query()
                    ->whereIn('id', $messages->pluck('user_id')->filter()->unique()->all())
                    ->get()
                    ->keyBy('id');

                foreach ($messages as $msg) {
                    $usage = json_decode($msg->usage, true);
                    if (! $usage || empty($usage['promptTokens'])) {
                        continue;
                    }

                    // user_id maps to lead_id by design in Aria's AgentFactory mapping
                    $lead = $leads->get($msg->user_id);
                    if (! $lead) {
                        continue;
                    }

                    $date = Carbon::parse($msg->created_at)->toDateString();
                    $model = class_basename($msg->agent) ?? 'gpt-4o-mini';

                    LogAiUsageJob::dispatchSync(
                        $usage['promptTokens'] ?? 0,
                        $usage['completionTokens'] ?? 0,
                        $model,
                        $lead->agent_id,
                        $lead->tenant_id,
                        $date
                    );

                    $count++;
                }
            });

        $this->info("Backfill complete! Processed {$count} historical LLM responses.");

        return self::SUCCESS;
    }
}
