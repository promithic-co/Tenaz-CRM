<?php

namespace App\Console\Commands;

use App\Jobs\LogAiUsageJob;
use App\Models\Lead;
use Illuminate\Console\Command;
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
    public function handle()
    {
        $this->info('Starting AI usage backfill...');

        $messages = DB::table('agent_conversation_messages')
            ->whereNotNull('usage')
            ->where('role', 'assistant')
            ->get();

        $count = 0;
        foreach ($messages as $msg) {
            $usage = json_decode($msg->usage, true);
            if (! $usage || empty($usage['promptTokens'])) {
                continue;
            }

            // user_id maps to lead_id by design in Aria's AgentFactory mapping
            $lead = Lead::find($msg->user_id);
            if (! $lead) {
                continue;
            }

            $date = \Carbon\Carbon::parse($msg->created_at)->toDateString();

            // "agent" column usually contains names like "Aria::class", let's extract model dynamically
            // AgentConversationMessages doesn't strictly store 'model' separate.
            // Let's use a default or extract from meta if possible
            $meta = json_decode($msg->meta ?? '{}', true);
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

        $this->info("Backfill complete! Processed {$count} historical LLM responses.");
    }
}
