<?php

namespace App\Services;

use App\Jobs\ProcessLeadFollowUpJob;
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
 * @see AgentService
 * @see ProcessLeadFollowUpJob
 */
class ConversationContextSynchronizer
{
    /** Retry budget for the per-turn sync-mark hand-off before it is escalated (ATOM-5). */
    private const MARK_SYNCED_MAX_ATTEMPTS = 3;

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

        // MEM-7: a fresh builder for each use (exists / chunkById) — chunkById mutates the
        // query, so it must not be shared. Bounds memory to one chunk even if a lead has
        // accumulated a long unsynced backlog (AI paused while many inbound messages land).
        $pendingQuery = fn () => ConversationTimelineMessage::query()
            ->where('lead_id', $lead->id)
            ->whereNull('synced_to_agent_at')
            ->whereIn('sender_type', ['lead', 'human']);

        if (! $pendingQuery()->exists()) {
            return 0;
        }

        $agentName = $this->resolveAgentName($lead);
        $userId = is_numeric($lead->tenant_id) ? (int) $lead->tenant_id : null;
        $chunkSize = (int) config('credflow.conversation_sync_chunk_size', 500);
        $synced = 0;

        try {
            DB::transaction(function () use ($pendingQuery, $lead, $agentName, $userId, $chunkSize, &$synced): void {
                $pendingQuery()
                    ->select(['id', 'sender_type', 'body', 'media', 'source', 'created_at'])
                    ->orderBy('id')
                    ->chunkById($chunkSize, function ($rows) use ($lead, $agentName, $userId, &$synced): void {
                        $syncedIds = [];

                        foreach ($rows as $row) {
                            $role = $row->sender_type === 'lead' ? 'user' : 'assistant';

                            DB::table('agent_conversation_messages')->insert([
                                'id' => (string) Str::uuid(),
                                'conversation_id' => $lead->conversation_id,
                                'user_id' => $userId,
                                'agent' => $agentName,
                                'role' => $role,
                                'content' => (string) ($row->body ?? ''),
                                'attachments' => $this->buildAttachments($row),
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

                        // Mark this chunk synced before paging on; keyset advances by id so
                        // already-flipped rows are never revisited, and the whole sync stays
                        // atomic — a later-chunk failure rolls back every insert and flip.
                        ConversationTimelineMessage::query()
                            ->whereIn('id', $syncedIds)
                            ->update(['synced_to_agent_at' => now()]);
                    });
            });
        } catch (Throwable $e) {
            Log::error('conversation_sync.failed', [
                'lead_id' => $lead->id,
                'conversation_id' => $lead->conversation_id,
                'synced_before_failure' => $synced,
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
     * Mark the inbound timeline row(s) for a completed agent turn as synced, so the next
     * syncPending() won't re-mirror them — laravel/ai already wrote its own user row during
     * prompt(). The mark is a single idempotent UPDATE, but if it fails silently the row
     * stays unsynced and gets mirrored again next turn, duplicating the user message in
     * agent memory (ATOM-5). So retry the hand-off a few times, and on exhaustion escalate
     * to error rather than swallowing it. Returns true once marked (or there was nothing
     * to mark); false if every attempt failed.
     */
    public function markTurnSynced(Lead $lead, string $interactionId): bool
    {
        for ($attempt = 1; $attempt <= self::MARK_SYNCED_MAX_ATTEMPTS; $attempt++) {
            try {
                ConversationTimelineMessage::query()
                    ->where('lead_id', $lead->id)
                    ->where('interaction_id', $interactionId)
                    ->where('sender_type', 'lead')
                    ->whereNull('synced_to_agent_at')
                    ->update(['synced_to_agent_at' => now()]);

                return true;
            } catch (Throwable $e) {
                Log::warning('conversation_sync.mark_turn_attempt_failed', [
                    'lead_id' => $lead->id,
                    'interaction_id' => $interactionId,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::error('conversation_sync.mark_turn_failed', [
            'lead_id' => $lead->id,
            'interaction_id' => $interactionId,
        ]);

        return false;
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
