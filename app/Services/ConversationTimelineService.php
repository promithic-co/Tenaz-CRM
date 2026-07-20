<?php

namespace App\Services;

use App\Events\NewConversationMessage;
use App\Models\ConversationSession;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConversationTimelineService
{
    /**
     * @param  array<string, mixed>|null  $media
     * @param  array<string, mixed>|null  $metadata  extra structured payload (e.g. a template snapshot)
     * @param  \DateTimeInterface|null  $occurredAt  overrides created_at/updated_at — used when
     *                                               backfilling historical rows (campaign templates)
     *                                               so they sort into the timeline at their real time.
     */
    public function record(
        Lead $lead,
        string $direction,
        string $senderType,
        string $body = '',
        ?array $media = null,
        string $status = 'received',
        string $source = 'webhook',
        string $channel = 'whatsapp',
        ?string $interactionId = null,
        ?string $providerMessageId = null,
        ?string $conversationId = null,
        ?array $metadata = null,
        ?\DateTimeInterface $occurredAt = null,
        ?int $sessionId = null,
    ): ConversationTimelineMessage {
        // Agent-authored rows are pre-marked synced because laravel/ai already wrote them
        // into agent_conversation_messages when $agent->prompt() returned. Lead/human rows
        // start NULL so ConversationContextSynchronizer picks them up on the next turn.
        $syncedAt = $senderType === 'agent' ? now() : null;

        // Stamp the atendimento this message belongs to. Callers on the inbound path pass
        // the session they just opened; every other path (operator reply, follow-up,
        // campaign) falls back to the lead's currently-open session so history segments
        // correctly without touching each call site. Global scopes are bypassed because
        // this runs on the queue where the tenant scope is inert.
        $resolvedSessionId = $sessionId ?? ConversationSession::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('status', ConversationSession::STATUS_OPEN)
            ->value('id');

        $message = new ConversationTimelineMessage([
            'tenant_id' => (string) $lead->tenant_id,
            'lead_id' => $lead->id,
            'session_id' => $resolvedSessionId,
            'conversation_id' => $conversationId ?? $lead->conversation_id,
            'direction' => $direction,
            'sender_type' => $senderType,
            'channel' => $channel,
            'body' => $body,
            'media' => $media,
            'status' => $status,
            'source' => $source,
            'interaction_id' => $interactionId,
            'provider_message_id' => $providerMessageId,
            'metadata' => $metadata,
            'synced_to_agent_at' => $syncedAt,
        ]);

        // Setting the timestamps before save() marks them dirty, so Eloquent's
        // updateTimestamps() leaves them untouched and the backfilled row keeps its real time.
        if ($occurredAt !== null) {
            $message->created_at = $occurredAt;
            $message->updated_at = $occurredAt;
        }

        $message->save();

        return $message;
    }

    public function broadcast(ConversationTimelineMessage $message, bool $toOthers = false): void
    {
        // Realtime broadcast must never abort callers responsible for CRM
        // persistence — the inbound is already in the timeline by the time we
        // get here. Log the failure and let the polling fallback recover the UI.
        try {
            $pending = broadcast(new NewConversationMessage($message->lead_id, $this->toFrontendMessage($message)));

            if ($toOthers) {
                $pending->toOthers();
            }
        } catch (\Throwable $e) {
            Log::warning('conversation_timeline.broadcast_failed', [
                'timeline_message_id' => $message->id,
                'lead_id' => $message->lead_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toFrontendMessage(ConversationTimelineMessage $message): array
    {
        return [
            'id' => $message->id,
            'session_id' => $message->session_id,
            'role' => match ($message->sender_type) {
                'lead' => 'user',
                'human' => 'operator',
                default => 'assistant',
            },
            'content' => (string) $message->body,
            'hora' => $message->created_at?->format('H:i') ?? now()->format('H:i'),
            'media' => $this->sanitizeMediaForFrontend($message->media),
            'direction' => $message->direction,
            'sender_type' => $message->sender_type,
            'channel' => $message->channel,
            'status' => $message->status,
            'source' => $message->source,
            'interaction_id' => $message->interaction_id,
            'provider_message_id' => $message->provider_message_id,
            'template' => $this->templateSnapshotForFrontend($message->metadata),
        ];
    }

    /**
     * Extract the structured template snapshot recorded at send time so the UI can
     * render header/body/footer/buttons as a real template bubble instead of the
     * flattened `[Botão] …` text stored in `body`. Returns null for non-template rows.
     *
     * @param  array<string, mixed>|null  $metadata
     * @return array{header: array<string, mixed>|null, body: string|null, footer: string|null, buttons: list<array{type: string, text: string}>}|null
     */
    private function templateSnapshotForFrontend(?array $metadata): ?array
    {
        $rendered = $metadata['whatsapp_template']['rendered'] ?? null;

        if (! is_array($rendered)) {
            return null;
        }

        $header = null;
        if (is_array($rendered['header'] ?? null)) {
            $format = (string) ($rendered['header']['format'] ?? 'TEXT');
            $header = $format === 'TEXT'
                ? ['format' => 'TEXT', 'text' => (string) ($rendered['header']['text'] ?? '')]
                : ['format' => $format];
        }

        $buttons = [];
        foreach (is_array($rendered['buttons'] ?? null) ? $rendered['buttons'] : [] as $button) {
            if (is_array($button) && is_string($button['text'] ?? null) && trim($button['text']) !== '') {
                $buttons[] = [
                    'type' => (string) ($button['type'] ?? 'QUICK_REPLY'),
                    'text' => $button['text'],
                ];
            }
        }

        return [
            'header' => $header,
            'body' => is_string($rendered['body'] ?? null) ? $rendered['body'] : null,
            'footer' => is_string($rendered['footer'] ?? null) ? $rendered['footer'] : null,
            'buttons' => $buttons,
        ];
    }

    /**
     * Strip server-side disclosure fields (absolute paths) before sending the media
     * descriptor to the frontend. The operator UI only needs metadata + a future
     * signed URL — never the raw filesystem path.
     *
     * @param  array<string, mixed>|null  $media
     * @return array<string, mixed>|null
     */
    private function sanitizeMediaForFrontend(?array $media): ?array
    {
        if ($media === null) {
            return null;
        }

        return [
            'type' => $media['type'] ?? null,
            'mime_type' => $media['mime_type'] ?? null,
            'filename' => $media['filename'] ?? null,
            'size_bytes' => $media['size_bytes'] ?? null,
            'duration_secs' => $media['duration_secs'] ?? null,
            'caption' => $media['caption'] ?? null,
            'original_hash' => $media['original_hash'] ?? null,
        ];
    }

    /**
     * Read the legacy laravel/ai conversation rows directly from
     * `agent_conversation_messages` for a lead.
     *
     * This is the fallback / backfill reader for pre-timeline leads — the
     * timeline (forLead) remains the primary source of truth for the UI. The
     * in-file ban on touching `agent_conversation_messages` applies to the
     * controller/UI layer; this service owns the table and is the sanctioned
     * place for the raw read.
     *
     * The returned shape always carries `role`, `content`, `hora` (H:i) and a
     * `media` descriptor decoded from the `_aria_media` envelope inside the row's
     * `attachments` JSON (null when absent). Rows are always returned in
     * ascending chronological order; when $newestFirst is true the query fetches
     * the latest $limit rows before re-sorting ascending (the preview shape).
     *
     * @return list<array{role: mixed, content: mixed, hora: string, media: array<string, mixed>|null}>
     */
    public function legacyMessages(Lead $lead, int $limit = 200, bool $newestFirst = false): array
    {
        if (! $lead->conversation_id) {
            return [];
        }

        $query = DB::table('agent_conversation_messages')
            ->where('conversation_id', $lead->conversation_id);

        $rows = $newestFirst
            ? $query->orderByDesc('created_at')->limit($limit)->get(['role', 'content', 'attachments', 'created_at'])->sortBy('created_at')->values()
            : $query->orderBy('created_at')->limit($limit)->get(['role', 'content', 'attachments', 'created_at']);

        return $rows
            ->map(fn ($message): array => [
                'role' => $message->role,
                'content' => $message->content,
                'hora' => Carbon::parse($message->created_at)->format('H:i'),
                'media' => $message->attachments
                    ? (json_decode($message->attachments, true)['_aria_media'] ?? null)
                    : null,
            ])
            ->all();
    }

    /**
     * Append a single row to the legacy `agent_conversation_messages` table for a
     * lead's conversation (e.g. the playground evaluation summary). Supplies every
     * NOT NULL column the laravel/ai schema requires so the insert is valid on any
     * driver. No-op when the lead has no conversation. This service owns the table;
     * the controller/action layer must route appends through here.
     */
    public function appendLegacyMessage(Lead $lead, string $role, string $content): void
    {
        if (! $lead->conversation_id) {
            return;
        }

        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid(),
            'conversation_id' => $lead->conversation_id,
            'user_id' => null,
            'agent' => '',
            'role' => $role,
            'content' => $content,
            'attachments' => '',
            'tool_calls' => '',
            'tool_results' => '',
            'usage' => '',
            'meta' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forLead(Lead $lead, int $limit = 200): array
    {
        return ConversationTimelineMessage::query()
            ->where('lead_id', $lead->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (ConversationTimelineMessage $message) => $this->toFrontendMessage($message))
            ->all();
    }
}
