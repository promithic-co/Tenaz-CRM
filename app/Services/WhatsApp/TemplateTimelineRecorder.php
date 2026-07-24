<?php

namespace App\Services\WhatsApp;

use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\WhatsappTemplate;
use App\Services\CampaignConversationTimelineWriter;
use App\Services\ConversationTimelineService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes an outbound WhatsApp template into the conversation timeline.
 *
 * Templates leave this system through four doors: the operator's composer, campaigns, the URA
 * trigger and the post-call nudge. Only the first two ever wrote a timeline row, so a
 * conversation opened by a URA template showed the customer's reply with nothing above it —
 * the operator could not see what the customer was replying to.
 *
 * This is the recording path for the job senders. It never throws into them: the message has
 * already reached the customer by the time we get here, so a failed timeline write is logged
 * and swallowed rather than turning a delivered send into a retry that double-messages.
 *
 * Campaigns keep their own writer ({@see CampaignConversationTimelineWriter}),
 * which additionally resolves the lead by phone and backfills historical rows.
 */
class TemplateTimelineRecorder
{
    public function __construct(
        private readonly ConversationTimelineService $timeline,
        private readonly WhatsappTemplateRenderer $renderer,
    ) {}

    /**
     * Record a template that was just sent to this lead, and broadcast it so an operator with
     * the conversation open sees it without reloading.
     *
     * Idempotent on provider_message_id when one is supplied — a job retry that re-sends after
     * a lost provider response will not stack a second row.
     *
     * @param  list<string>  $variables  positional body variables: ["João", "INSS"] fills {{1}}, {{2}}
     */
    public function record(
        Lead $lead,
        WhatsappTemplate $template,
        array $variables = [],
        string $source = 'system',
        ?string $interactionId = null,
        ?string $providerMessageId = null,
    ): ?ConversationTimelineMessage {
        try {
            if ($providerMessageId !== null && $this->alreadyRecorded($lead, $providerMessageId)) {
                return null;
            }

            $components = is_array($template->components_json) ? $template->components_json : [];
            $rendered = $this->renderSnapshot($components, $variables);

            $message = $this->timeline->record(
                lead: $lead,
                direction: 'outbound',
                senderType: 'system',
                body: $rendered['text'] ?? trim(implode(' ', $variables)),
                status: 'sent',
                source: $source,
                interactionId: $interactionId,
                providerMessageId: $providerMessageId,
                metadata: [
                    'whatsapp_template' => array_filter([
                        'id' => $template->id,
                        'name' => $template->name,
                        'meta_template_name' => $template->meta_template_name,
                        'language' => $template->language,
                        'category' => $template->category,
                        'rendered' => $rendered,
                    ], fn (mixed $value): bool => $value !== null),
                ],
            );

            $this->timeline->broadcast($message);

            return $message;
        } catch (Throwable $e) {
            Log::warning('template_timeline.record_failed', [
                'lead_id' => $lead->id,
                'template_id' => $template->id,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function alreadyRecorded(Lead $lead, string $providerMessageId): bool
    {
        return ConversationTimelineMessage::query()
            ->where('lead_id', $lead->id)
            ->where('provider_message_id', $providerMessageId)
            ->exists();
    }

    /**
     * Structured snapshot of the template as the customer received it. Uses the renderer's
     * example-tolerant preview so a template whose variables were not all supplied still
     * produces a readable row instead of costing us the record entirely. Null when the
     * template carries no structured components or the renderer rejects them.
     *
     * @param  array<int, mixed>  $components
     * @param  list<string>  $variables
     * @return array{header: ?array<string, mixed>, body: ?string, footer: ?string, buttons: list<array<string, mixed>>, text: string}|null
     */
    private function renderSnapshot(array $components, array $variables): ?array
    {
        if ($components === []) {
            return null;
        }

        // The renderer keys parameters by 1-based position within a section; the job senders
        // carry them as a plain positional list because that is the shape Meta's body
        // component takes.
        $positional = [];
        foreach (array_values($variables) as $index => $value) {
            $positional[(string) ($index + 1)] = (string) $value;
        }

        try {
            return $this->renderer->preview($components, [
                'header' => $positional,
                'body' => $positional,
                'buttons' => $positional,
            ]);
        } catch (Throwable) {
            return null;
        }
    }
}
