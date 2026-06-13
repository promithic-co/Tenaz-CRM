<?php

namespace App\Services;

use App\Ai\Agents\LeadSignalExtractorAgent;
use App\Enums\TaggableSource;
use App\Jobs\LogAiUsageJob;
use App\Models\AppSetting;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\Tag;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates AI-assisted tag evaluation for a Lead.
 *
 * Safety guarantees owned by this service (D-03 through D-08):
 * - Returns early without LLM call when auto-tagging is disabled or no ai_detectable tags exist.
 * - Only assigns existing tenant tags where ai_detectable=true.
 * - Validates every returned slug against the preloaded tenant whitelist.
 * - Enforces per-tag confidence thresholds.
 * - Skips tags that already have a manual pivot (Manual always wins).
 * - Sanitizes evidence before storing.
 * - Never logs transcript text, CPF, phone, or raw document numbers.
 */
class LeadAutoTaggingService
{
    public function __construct(
        private readonly AutoTagEvidenceSanitizer $sanitizer,
    ) {}

    /**
     * Evaluate a Lead and apply AI-detected tags.
     *
     * @return array{skipped: bool, reason?: string, applied?: string[]}
     */
    public function evaluate(Lead $lead, string $trigger, ?int $requestedByUserId = null): array
    {
        $tenantId = (string) $lead->tenant_id;

        // Guard: tenant feature flag
        if (! AppSetting::getForTenant($tenantId, 'auto_tagging_enabled', false)) {
            return ['skipped' => true, 'reason' => 'disabled'];
        }

        // Guard: at least one ai_detectable tag must exist
        $detectableTags = Tag::query()
            ->where('tenant_id', $tenantId)
            ->where('ai_detectable', true)
            ->get();

        if ($detectableTags->isEmpty()) {
            return ['skipped' => true, 'reason' => 'no_detectable_tags'];
        }

        // Build instructions with closed taxonomy
        $slugList = $detectableTags->map(fn (Tag $t) => '"'.$t->slug.'" — '.$t->ai_description)->implode("\n");
        $instructions = <<<INSTRUCTIONS
        Você é um analisador de estado de negociação. Analise a conversa e identifique quais tags comerciais se aplicam ao lead.

        REGRAS CRÍTICAS:
        - Retorne APENAS slugs da lista abaixo. Nunca invente slugs ou crie novos.
        - Não classifique traços pessoais, dados sensíveis ou informações demográficas.
        - Confiança deve refletir evidências concretas da conversa.
        - Evidência: frase curta e objetiva que justifica a tag. Máximo 100 caracteres.

        TAGS DISPONÍVEIS:
        {$slugList}
        INSTRUCTIONS;

        // Build sanitized transcript (latest 20 messages, body only)
        $transcript = $this->buildTranscript($lead);

        // Call structured agent
        $agentConfig = AppSetting::getAgentConfig();
        $model = (string) ($agentConfig['agent_model'] ?? 'gpt-4o-mini');

        $agent = new LeadSignalExtractorAgent($instructions);
        $response = $agent->prompt($transcript, model: $model, timeout: 45);

        $detected = $response['detected'] ?? [];
        $applied = [];

        foreach ($detected as $signal) {
            $slug = $signal['slug'] ?? null;
            $confidence = (float) ($signal['confidence'] ?? 0);
            $rawEvidence = (string) ($signal['evidence'] ?? '');

            if (! $slug) {
                continue;
            }

            // Validate slug against tenant whitelist
            $tag = $detectableTags->firstWhere('slug', $slug);
            if (! $tag) {
                continue;
            }

            // Enforce per-tag confidence threshold
            if ($confidence < (float) $tag->ai_min_confidence) {
                continue;
            }

            // Manual wins: skip if manual pivot already exists
            if ($lead->tags()->where('tags.id', $tag->id)->wherePivot('source', TaggableSource::Manual->value)->exists()) {
                continue;
            }

            $sanitizedEvidence = $this->sanitizer->sanitize($rawEvidence);

            $lead->attachTag($tag, TaggableSource::Ai, null, [
                'ai_confidence' => $confidence,
                'ai_evidence' => $sanitizedEvidence ?: null,
                'ai_evaluated_at' => now()->toDateTimeString(),
            ]);

            $applied[] = $slug;
        }

        // Stamp cooldown/debug timestamp
        $lead->forceFill(['last_auto_tag_at' => now()])->saveQuietly();

        // Track usage for future credit accounting (feature='auto_tag')
        if ($response->usage->promptTokens > 0 || $response->usage->completionTokens > 0) {
            LogAiUsageJob::dispatch(
                $response->usage->promptTokens,
                $response->usage->completionTokens,
                $model,
                null,
                $tenantId,
            );
        }

        Log::info('auto-tag evaluation completed', [
            'lead_id' => $lead->id,
            'tenant_id' => $tenantId,
            'trigger' => $trigger,
            'applied_count' => count($applied),
            'applied_slugs' => $applied,
        ]);

        return ['skipped' => false, 'applied' => $applied];
    }

    /**
     * Build a sanitized transcript string from the latest conversation messages.
     * Caps at 20 messages. Body text only — no media, no metadata.
     */
    private function buildTranscript(Lead $lead): string
    {
        $messages = ConversationTimelineMessage::query()
            ->where('lead_id', $lead->id)
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['direction', 'body'])
            ->reverse();

        if ($messages->isEmpty()) {
            return '[Sem mensagens disponíveis para análise]';
        }

        $lines = $messages->map(function (ConversationTimelineMessage $msg): string {
            $role = $msg->direction === 'inbound' ? 'Cliente' : 'Agente';
            // Truncate individual message bodies to avoid passing full transcripts
            $body = mb_substr((string) $msg->body, 0, 200);

            return "{$role}: {$body}";
        });

        return $lines->implode("\n");
    }
}
