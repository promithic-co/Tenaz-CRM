<?php

namespace App\Actions;

use App\Models\FollowupMessage;
use App\Models\Lead;
use App\Services\FollowUpWindowService;
use Illuminate\Support\Facades\DB;

class ClearLeadHistoryAction
{
    public function __construct(private FollowUpWindowService $window) {}

    /**
     * Wipe a lead's full conversation history and agent memory: the legacy
     * laravel/ai conversation + messages, the conversation timeline, and the
     * follow-up history. The lead row itself is preserved; only conversation
     * pointer + follow-up counters/status are recomputed.
     *
     * The follow-up status is re-derived from the WhatsApp free-form window
     * (active when still in-window, otherwise inactive) and the counter resets.
     *
     * Runs inside a transaction so a partial wipe can't leave context corrupted.
     *
     * Authorization is the caller's responsibility — this action does NOT
     * authorize.
     */
    public function clearForLead(Lead $lead): void
    {
        DB::transaction(function () use ($lead): void {
            if ($lead->conversation_id) {
                DB::table('agent_conversation_messages')
                    ->where('conversation_id', $lead->conversation_id)
                    ->delete();

                DB::table('agent_conversations')
                    ->where('id', $lead->conversation_id)
                    ->delete();
            }

            DB::table('conversation_timeline_messages')
                ->where('lead_id', $lead->id)
                ->delete();

            FollowupMessage::withoutGlobalScopes()
                ->where('lead_id', $lead->id)
                ->delete();

            $stillInWindow = $this->window->canSendFreeFormMessage($lead);
            $lead->update([
                'conversation_id' => null,
                'followup_count' => 0,
                'followup_status' => $stillInWindow ? 'active' : 'inactive',
            ]);
        });
    }

    /**
     * Delete only the sandbox conversation rows (legacy laravel/ai conversation
     * + its messages) for a sandbox lead. Does not touch the timeline,
     * follow-up history, or any lead attributes — callers manage lead lifecycle
     * (delete/reset) themselves.
     *
     * Authorization is the caller's responsibility — this action does NOT
     * authorize.
     */
    public function clearSandboxConversation(Lead $lead): void
    {
        if (! $lead->conversation_id) {
            return;
        }

        DB::table('agent_conversation_messages')
            ->where('conversation_id', $lead->conversation_id)
            ->delete();

        DB::table('agent_conversations')
            ->where('id', $lead->conversation_id)
            ->delete();
    }
}
