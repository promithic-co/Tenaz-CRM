<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\ContactListEntry;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Services\WhatsApp\PhoneNumberValidator;
use App\Services\WhatsApp\WhatsappTemplateRenderer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mirrors campaign template sends into the conversation timeline so a real conversation
 * shows the message that (re)started it. Campaigns bypass the outbox/timeline for scale, so
 * this is the single place that bridges them back:
 *
 *   - mirrorSentTemplate(): right after a send, creating the Lead when the recipient has none.
 *   - backfillForLead(): when a recipient replies, we backfill the recent campaign templates
 *     that preceded the reply and are not in the timeline yet.
 *
 * Creating the Lead eagerly is what makes an unanswered send visible at all; the operator
 * could otherwise not tell a campaign from a silent failure. The flood that argued against
 * it is handled downstream instead: the new leads carry no last_inbound_at, which confines
 * them to the "envios" tab and subtracts them from every other one.
 *
 * Never throws into its callers — a mirror failure is logged, the send/inbound is unaffected.
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
                ->whereIn('whatsapp', $this->phoneCandidates((string) $entry->phone, $destination))
                ->first();

            $lead ??= $this->createSilentLead($campaign, $entry, $destination);

            if (! $lead) {
                return;
            }

            if ($this->timelineRowExists($lead, $providerMessageId)) {
                return;
            }

            $rendered = $this->renderSnapshot($templateComponents, $resolvedParams);

            $message = $this->timeline->record(
                lead: $lead,
                direction: 'outbound',
                senderType: 'system',
                body: $this->renderBody($rendered, $resolvedParams),
                status: 'sent',
                source: 'campaign',
                providerMessageId: $providerMessageId,
                metadata: $rendered !== null ? ['whatsapp_template' => ['rendered' => $rendered]] : null,
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
     * Backfill the recent campaign templates that reached this phone but never made it into
     * the timeline — either the lead did not exist when the campaign fired, or the mirror
     * missed because the two rows disagreed about the 9th digit. Rows are stamped with their
     * original sent_at so they sort ahead of the reply that triggered the backfill.
     *
     * Idempotent on provider_message_id, so running it on every inbound is safe; the caller
     * throttles the scan rather than relying on a lead-was-just-created flag, which is what
     * previously left returning leads without the template that reopened the conversation.
     *
     * The inbound path keeps the conservative defaults — it runs on the hot path and only
     * needs the templates immediately preceding the reply. The one-off historical replay
     * widens both bounds so it can reach sends that predate the mirror entirely.
     */
    public function backfillForLead(Lead $lead, ?int $lookbackDays = null, ?int $limit = null): void
    {
        try {
            $messages = CampaignMessage::query()
                ->whereNotNull('provider_message_id')
                ->whereIn('status', ['sent', 'delivered', 'read'])
                ->where('sent_at', '>=', now()->subDays($lookbackDays ?? self::BACKFILL_LOOKBACK_DAYS))
                ->whereHas('contactListEntry', fn ($q) => $q->whereIn('phone', $this->phoneCandidates((string) $lead->whatsapp)))
                ->whereHas('campaign', fn ($q) => $q->where('tenant_id', $lead->tenant_id))
                ->with(['campaign.whatsappTemplate'])
                ->orderBy('sent_at')
                ->limit($limit ?? self::BACKFILL_LIMIT)
                ->get();

            foreach ($messages as $message) {
                $providerMessageId = (string) $message->provider_message_id;

                if ($this->timelineRowExists($lead, $providerMessageId)) {
                    continue;
                }

                $components = $message->campaign?->whatsappTemplate?->components_json;
                $resolved = is_array($message->template_params_resolved) ? $message->template_params_resolved : [];
                $rendered = $this->renderSnapshot($components, $resolved);

                $this->timeline->record(
                    lead: $lead,
                    direction: 'outbound',
                    senderType: 'system',
                    body: $this->renderBody($rendered, $resolved),
                    status: $message->status,
                    source: 'campaign',
                    providerMessageId: $providerMessageId,
                    metadata: $rendered !== null ? ['whatsapp_template' => ['rendered' => $rendered]] : null,
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

    /**
     * Create the Lead a campaign send needs to be visible in /conversas.
     *
     * last_inbound_at is left null on purpose — that is what puts the lead in the
     * "envios" tab and keeps it out of every other one (Lead::scopeInboxFiltered),
     * so a 50k fan-out is a record of what went out rather than 50k rows of fake work.
     * The recipient's first reply stamps the column and the lead joins the real queue
     * on its own.
     *
     * firstOrCreate, not create: two staggered sends can reach the same subscriber, and
     * a duplicate lead would split the conversation in two.
     */
    private function createSilentLead(Campaign $campaign, ContactListEntry $entry, string $destination): ?Lead
    {
        $lead = Lead::withoutGlobalScopes()->firstOrCreate(
            [
                'tenant_id' => $campaign->tenant_id,
                'whatsapp' => $destination,
            ],
            [
                'nome' => $entry->name ?: null,
                'contact_id' => $entry->contact_id,
                'campaign_id' => $campaign->id,
                'whatsapp_instance_id' => $campaign->whatsapp_instance_id,
                'modo' => 'ativo',
            ],
        );

        // Durable link so later sends and the reply path find this lead by id rather than
        // re-deriving it from phone variants.
        if ($entry->lead_id === null) {
            $entry->update(['lead_id' => $lead->id]);
        }

        return $lead;
    }

    /**
     * Every phone form worth matching a lead against. A campaign entry, the destination the
     * provider actually dialled and the lead row are written by different code paths, so the
     * same subscriber can be stored with or without the BR 9th digit — matching on one form
     * alone is why campaign templates went missing from conversations.
     *
     * @return list<string>
     */
    private function phoneCandidates(string ...$phones): array
    {
        $candidates = [];

        foreach ($phones as $phone) {
            $candidates = [...$candidates, ...PhoneNumberValidator::variants($phone)];
        }

        return array_values(array_unique($candidates));
    }

    private function timelineRowExists(Lead $lead, string $providerMessageId): bool
    {
        return ConversationTimelineMessage::query()
            ->where('lead_id', $lead->id)
            ->where('provider_message_id', $providerMessageId)
            ->exists();
    }

    /**
     * Structured snapshot of the template as the customer received it, via the shared
     * renderer's example-tolerant preview. Null when the template has no structured
     * components or the renderer rejects them, so this never throws.
     *
     * @param  array<int, mixed>|null  $components
     * @param  array<string, string>  $resolved
     * @return array{header: ?array<string, mixed>, body: ?string, footer: ?string, buttons: list<array<string, mixed>>, text: string}|null
     */
    private function renderSnapshot(?array $components, array $resolved): ?array
    {
        if (! is_array($components) || $components === []) {
            return null;
        }

        $sectionParams = [
            'header' => $resolved,
            'body' => $resolved,
            'buttons' => $resolved,
        ];

        try {
            return $this->renderer->preview($components, $sectionParams);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Human-readable body for the timeline row, falling back to the raw resolved values
     * when no structured snapshot could be rendered.
     *
     * @param  array{text: string}|null  $rendered
     * @param  array<string, string>  $resolved
     */
    private function renderBody(?array $rendered, array $resolved): string
    {
        return $rendered !== null ? $rendered['text'] : trim(implode(' ', $resolved));
    }
}
