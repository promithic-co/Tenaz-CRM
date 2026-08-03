<?php

namespace App\Services;

use App\Events\InstanceQualityRatingChanged;
use App\Jobs\DispatchMetaQualityAutoPauseJob;
use App\Models\Campaign;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\WhatsApp\MetaAccountHealthWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Applies Meta quality / template status webhook updates to the matching
 * instance, templates and campaigns. Extracted verbatim from
 * MetaWebhookController::handleQualityOrTemplateUpdate so the security
 * boundary (signature verification) remains in the controller.
 */
class TemplateStatusUpdateService
{
    public function handle(Request $request, WhatsappInstance $instance, string $field): void
    {
        $value = $request->input('entry.0.changes.0.value', []);

        $this->handleValue($instance, $field, is_array($value) ? $value : []);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function handleValue(WhatsappInstance $instance, string $field, array $value): void
    {
        if ($field === 'phone_number_quality_update') {
            $event = (string) ($value['event'] ?? '');
            $newRating = match ($event) {
                'FLAGGED' => 'RED',
                'ONBOARDING' => 'GREEN',
                default => (string) ($value['new_quality_score'] ?? $instance->meta_quality_rating ?? 'GREEN'),
            };
            $patch = ['meta_quality_rating' => $newRating];

            // Since the move to portfolio-based limits this webhook also carries the
            // portfolio's ceiling. `current_limit` is deliberately ignored: Meta
            // redefined it to mean either the portfolio limit or the number's
            // throughput level, with nothing in the payload saying which.
            // business_capability_update is what fans the value across the WABA.
            $portfolioLimit = MetaAccountHealthWebhookService::portfolioLimitTier(
                $value['max_daily_conversations_per_business'] ?? null
            );

            if ($portfolioLimit !== null) {
                $patch['meta_portfolio_messaging_limit'] = $portfolioLimit;
            }

            $instance->update($patch);
            InstanceQualityRatingChanged::dispatch($instance->id, $newRating);
            app(DashboardMetricsService::class)->dispatchUpdate((string) $instance->tenant_id);
            Log::info('meta.quality_phone_update', ['instance' => $instance->name, 'rating' => $newRating, 'event' => $event]);

            if ($newRating === 'RED') {
                DispatchMetaQualityAutoPauseJob::dispatch($instance->id);
            }

            return;
        }

        if ($field === 'message_template_quality_update') {
            $templateName = $value['message_template_name'] ?? null;
            $language = $value['message_template_language'] ?? null;
            $score = (string) ($value['new_quality_score'] ?? '');

            Log::info('meta.template_quality_update', [
                'instance' => $instance->name,
                'template' => $templateName,
                'previous' => $value['previous_quality_score'] ?? null,
                'score' => $score,
            ]);

            if ($templateName && $score !== '') {
                WhatsappTemplate::withoutGlobalScope('tenant')
                    ->where('whatsapp_instance_id', $instance->id)
                    ->where('meta_template_name', (string) $templateName)
                    ->when($language, fn ($query) => $query->where('language', (string) $language))
                    ->update(['quality_score' => $score]);
            }

            // A template dropping to RED is the same account-level risk signal the
            // status webhook already acts on, so it takes the same auto-pause path.
            if (strtoupper($score) === 'RED') {
                $instance->update(['meta_quality_rating' => 'RED']);
                DispatchMetaQualityAutoPauseJob::dispatch($instance->id);
            }

            return;
        }

        if ($field === 'message_template_status_update') {
            $templateName = $value['message_template_name'] ?? null;
            $newStatus = $value['event'] ?? null;
            $score = (string) ($value['new_quality_score'] ?? $value['quality_score']['score'] ?? '');
            Log::info('meta.quality_template_update', [
                'instance' => $instance->name,
                'template' => $templateName,
                'score' => $score,
            ]);

            if ($templateName && $newStatus) {
                $statusEvents = ['APPROVED', 'REJECTED', 'PAUSED', 'DISABLED', 'PENDING', 'FLAGGED', 'IN_APPEAL', 'PENDING_DELETION', 'DELETED', 'LIMIT_EXCEEDED'];
                $update = [];

                if (in_array((string) $newStatus, $statusEvents, true)) {
                    $update['status'] = (string) $newStatus;
                }

                if ($score !== '') {
                    $update['quality_score'] = $score;
                }

                if (isset($value['reason'])) {
                    $update['rejected_reason'] = (string) $value['reason'];
                }

                $templateIds = WhatsappTemplate::withoutGlobalScope('tenant')
                    ->where('whatsapp_instance_id', $instance->id)
                    ->where('meta_template_name', (string) $templateName)
                    ->pluck('id');

                if ($templateIds->isNotEmpty()) {
                    if ($update !== []) {
                        WhatsappTemplate::withoutGlobalScope('tenant')
                            ->whereIn('id', $templateIds)
                            ->update($update);
                    }

                    if (in_array((string) $newStatus, ['REJECTED', 'PAUSED', 'DISABLED'], true)) {
                        Campaign::withoutGlobalScope('tenant')
                            ->whereIn('whatsapp_template_id', $templateIds)
                            ->whereIn('status', ['draft', 'scheduled', 'sending', 'paused'])
                            ->update(['status' => 'paused']);
                    }
                }
            }

            if ($score === 'RED') {
                $instance->update(['meta_quality_rating' => 'RED']);
                DispatchMetaQualityAutoPauseJob::dispatch($instance->id);
            }

            return;
        }

        if ($field === 'template_category_update') {
            $templateName = $value['message_template_name'] ?? $value['template_name'] ?? null;
            $language = $value['message_template_language'] ?? $value['language'] ?? null;
            $newCategory = $value['new_category'] ?? $value['category'] ?? null;

            Log::info('meta.template_category_update', [
                'instance' => $instance->name,
                'template' => $templateName,
                'language' => $language,
                'new_category' => $newCategory,
            ]);

            if ($templateName && $newCategory) {
                WhatsappTemplate::withoutGlobalScope('tenant')
                    ->where('whatsapp_instance_id', $instance->id)
                    ->where('meta_template_name', (string) $templateName)
                    ->when($language, fn ($query) => $query->where('language', (string) $language))
                    ->update(['category' => strtoupper((string) $newCategory)]);
            }
        }
    }
}
