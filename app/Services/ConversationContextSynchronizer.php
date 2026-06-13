<?php

namespace App\Services;

use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mirrors un-synced rows from `conversation_timeline_messages` (source of truth) into
 * `agent_conversation_messages` (laravel/ai memory) so the agent always sees the
 * complete conversation — including operator turns and inbound messages received
 * while the AI was paused.
 *
 * Called immediately before any `$agent->continue($conversation_id)->prompt(...)` call.
 *
 * @see \App\Services\AgentService
 * @see \App\Jobs\ProcessLeadFollowUpJob
 */
class ConversationContextSynchronizer
{
    /**
     * Mirror every timeline row with `synced_to_agent_at = NULL` for this lead into the
     * agent's memory table, then mark them synced. Returns the count of rows mirrored.
     *
     * Safe to call when there's nothing to sync — early-returns 0.
     */
    public function syncPending(Lead $lead): int
    {
        if ($lead->conversation_id === null) {
            // No agent conversation yet — laravel/ai will create one on the first
            // $agent->forUser() call and write the first user message itself.
            return 0;
        }

        $pending = ConversationTimelineMessage::query()
            ->where('lead_id', $lead->id)
            ->whereNull('synced_to_agent_at')
            ->whereIn('sender_type', ['lead', 'human'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        $agentName = $this->resolveAgentName($lead);
        $userId = is_numeric($lead->tenant_id) ? (int) $lead->tenant_id : null;
        $synced = 0;
        $syncedIds = [];

        try {
            DB::transaction(function () use ($pending, $lead, $agentName, $userId, &$synced, &$syncedIds) {
                foreach ($pending as $row) {
                    $role = $row->sender_type === 'lead' ? 'user' : 'assistant';

                    $attachments = $this->buildAttachments($row);

                    DB::table('agent_conversation_messages')->insert([
                        'id' => (string) Str::uuid(),
                        'conversation_id' => $lead->conversation_id,
                        'user_id' => $userId,
                        'agent' => $agentName,
                        'role' => $role,
                        'content' => (string) ($row->body ?? ''),
                        'attachments' => $attachments,
                        'tool_calls' => '[]',
                        'tool_results' => '[]',
                        'usage' => '{}',
                        'meta' => json_encode([
                            '_aria_sync_origin' => 'timeline',
                            '_aria_timeline_id' => $row->id,
                            '_aria_sender_type' => $row->sender_type,
                            '_aria_source' => $row->source,
                        ]),
                        'created_at' => $row->created_at,
                        'updated_at' => $row->created_at,
                    ]);

                    $syncedIds[] = $row->id;
                    $synced++;
                }

                if ($syncedIds !== []) {
                    ConversationTimelineMessage::query()
                        ->whereIn('id', $syncedIds)
                        ->update(['synced_to_agent_at' => now()]);
                }
            });
        } catch (Throwable $e) {
            Log::error('conversation_sync.failed', [
                'lead_id' => $lead->id,
                'conversation_id' => $lead->conversation_id,
                'pending_count' => $pending->count(),
                'error' => $e->getMessage(),
            ]);

            // Swallow — the agent should still answer using whatever context it has,
            // rather than crash the whole turn because the sync errored.
            return 0;
        }

        Log::info('conversation_sync.applied', [
            'lead_id' => $lead->id,
            'conversation_id' => $lead->conversation_id,
            'rows_synced' => $synced,
        ]);

        return $synced;
    }

    /**
     * Mark a specific timeline row as synced. Called by AgentService after laravel/ai
     * has written its own row for the user/assistant turn — keeps the timeline row
     * from being mirrored again on the next call.
     */
    public function markSynced(int $timelineMessageId): void
    {
        ConversationTimelineMessage::query()
            ->where('id', $timelineMessageId)
            ->whereNull('synced_to_agent_at')
            ->update(['synced_to_agent_at' => now()]);
    }

    /**
     * Build the attachments JSON for a synthesized agent_conversation_messages row.
     * Preserves origin metadata so we can audit later which side wrote what.
     */
    private function buildAttachments(ConversationTimelineMessage $row): string
    {
        $payload = [
            '_aria_origin' => $row->sender_type === 'human' ? 'operator' : 'lead',
        ];

        if ($row->media) {
            $payload['_aria_media'] = $row->media;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function resolveAgentName(Lead $lead): string
    {
        $name = $lead->agent?->name;

        return is_string($name) && $name !== '' ? $name : 'aria';
    }
}
