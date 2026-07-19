<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactListEntry;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Services\WhatsApp\WhatsappTemplateRenderer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mirrors campaign template sends into the conversation timeline so a real conversation
 * shows the message that (re)started it. Campaigns bypass the outbox/timeline for scale, so
 * this is the single place that bridges them back — for the two moments a lead is known:
 *
 *   - mirrorSentTemplate(): right after a send, when the recipient already has a Lead.
 *   - backfillForNewLead(): when a recipient replies for the first time and their Lead is
 *     created, we backfill the recent campaign templates that preceded the reply.
 *
 * Never eagerly creates Leads (a 100k-contact fan-out must not flood /conversas), and never
 * throws into its callers — a mirror failure is logged, the send/inbound is unaffected.
 */
class CampaignConversationTimelineWriter
{
    /** Only backfill templates sent within this window before the first reply. */
    private const BACKFILL_LOOKBACK_DAYS = 30;

    private const BACKFILL_LIMIT = 5;

    public function __construct(
        private readonly ConversationTimelineService $timeline,
        private readonly WhatsappTemplateRenderer $renderer,
    ) {}

    /**
     * @param  array<string, string>  $resolvedParams
     * @param  array<int, mixed>|null  $templateComponents
     */
    public function mirrorSentTemplate(
        Campaign $campaign,
        ContactListEntry $entry,
        string $destination,
        string $providerMessageId,
        array $resolvedParams,
        ?array $templateComponents,
    ): void {
        try {
            $lead = Lead::withoutGlobalScopes()
                ->where('tenant_id', $campaign->tenant_id)
                ->whereIn('whatsapp', array_values(array_unique([(string) $entry->phone, $destination])))
                ->first();

            if (! $lead) {
                return;
            }

            if ($this->timelineRowExists($lead, $providerMessageId)) {
                return;
            }

            $message = $this->timeline->record(
                lead: $lead,
                direction: 'outbound',
                senderType: 'system',
                body: $this->renderBody($templateComponents, $resolvedParams),
                status: 'sent',
                source: 'campaign',
                providerMessageId: $providerMessageId,
            );
            $this->timeline->broadcast($message);

            if ($lead->whatsapp_instance_id === null && $campaign->whatsapp_instance_id !== null) {
                $lead->update(['whatsapp_instance_id' => $campaign->whatsapp_instance_id]);
            }
        } catch (Throwable $e) {
            Log::warning('campaign_timeline.mirror_failed', [
                'campaign_id' => $campaign->id,
                'entry_id' => $entry->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Backfill the recent campaign templates that reached this phone before the lead was
     * created by their first reply. Rows are stamped with their original sent_at so they sort
     * into the timeline ahead of the reply. Idempotent on provider_message_id.
     */
    public function backfillForNewLead(Lead $lead): void
    {
        try {
            $messages = CampaignMessage::query()
                ->whereNotNull('provider_message_id')
                ->whereIn('status', ['sent', 'delivered', 'read'])
                ->where('sent_at', '>=', now()->subDays(self::BACKFILL_LOOKBACK_DAYS))
                ->whereHas('contactListEntry', fn ($q) => $q->where('phone', $lead->whatsapp))
                ->whereHas('campaign', fn ($q) => $q->where('tenant_id', $lead->tenant_id))
                ->with(['campaign.whatsappTemplate'])
                ->orderBy('sent_at')
                ->limit(self::BACKFILL_LIMIT)
                ->get();

            foreach ($messages as $message) {
                $providerMessageId = (string) $message->provider_message_id;

                if ($this->timelineRowExists($lead, $providerMessageId)) {
                    continue;
                }

                $components = $message->campaign?->whatsappTemplate?->components_json;
                $resolved = is_array($message->template_params_resolved) ? $message->template_params_resolved : [];

                $this->timeline->record(
                    lead: $lead,
                    direction: 'outbound',
                    senderType: 'system',
                    body: $this->renderBody($components, $resolved),
                    status: $message->status,
                    source: 'campaign',
                    providerMessageId: $providerMessageId,
                    occurredAt: $message->sent_at,
                );
            }
        } catch (Throwable $e) {
            Log::warning('campaign_timeline.backfill_failed', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function timelineRowExists(Lead $lead, string $providerMessageId): bool
    {
        return ConversationTimelineMessage::query()
            ->where('lead_id', $lead->id)
            ->where('provider_message_id', $providerMessageId)
            ->exists();
    }

    /**
     * Human-readable snapshot of the template as the customer received it, via the shared
     * renderer's example-tolerant preview. Falls back to the raw resolved values when the
     * template has no structured components or the renderer rejects them, so this never throws.
     *
     * @param  array<int, mixed>|null  $components
     * @param  array<string, string>  $resolved
     */
    private function renderBody(?array $components, array $resolved): string
    {
        if (! is_array($components) || $components === []) {
            return trim(implode(' ', $resolved));
        }

        $sectionParams = [
            'header' => $resolved,
            'body' => $resolved,
            'buttons' => $resolved,
        ];

        try {
            return $this->renderer->preview($components, $sectionParams)['text'];
        } catch (Throwable) {
            return trim(implode(' ', $resolved));
        }
    }
}
