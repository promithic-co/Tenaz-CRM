<?php

namespace App\Services\WhatsApp;

use App\Events\InstanceHealthChanged;
use App\Jobs\SyncMetaHealthJob;
use App\Models\WhatsappInstance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Applies the WABA-level webhooks that describe why an account can or cannot
 * message: restrictions, policy violations, bans, review outcomes, capability
 * alerts and display-name decisions.
 *
 * The Meta app has been subscribed to every field handled here since the
 * integration was built, but nothing consumed them — they reached
 * MetaWebhookController, matched no branch and were dropped, so the panel could
 * only ever report "we hold a token". This is the consumer.
 *
 * Meta remains the authority on the resulting messaging verdict: each handled
 * event persists its own detail and then queues SyncMetaHealthJob rather than
 * inferring a health status locally, so the panel and the campaign gate never
 * disagree with `health_status`.
 */
class MetaAccountHealthWebhookService
{
    /**
     * WABA webhook fields this service owns. Kept as a list so the controller
     * can route on it without repeating the names.
     *
     * @var list<string>
     */
    public const HANDLED_FIELDS = [
        'account_update',
        'account_review_update',
        'account_alerts',
        'business_capability_update',
        'phone_number_name_update',
        'security',
    ];

    /**
     * Meta documents `max_daily_conversations_per_business` as an integer but
     * lists TIER_ constants as its values, and ships both shapes in practice.
     * Legacy per-number tiers are included so an old payload still resolves.
     *
     * @var array<int, string>
     */
    private const LIMIT_TIERS = [
        -1 => 'TIER_UNLIMITED',
        50 => 'TIER_50',
        250 => 'TIER_250',
        1000 => 'TIER_1K',
        2000 => 'TIER_2K',
        10000 => 'TIER_10K',
        100000 => 'TIER_100K',
    ];

    /**
     * Restriction types that stop a campaign dead. Meta ships several other
     * restriction types (calling, utility template creation) that leave
     * business-initiated messaging intact, so they are recorded but not treated
     * as a send blocker.
     *
     * @var list<string>
     */
    public const MESSAGING_RESTRICTIONS = [
        'RESTRICTED_BIZ_INITIATED_MESSAGING',
        'RESTRICTED_CUSTOMER_INITIATED_MESSAGING',
    ];

    public function handles(string $field): bool
    {
        return in_array($field, self::HANDLED_FIELDS, true);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function handle(WhatsappInstance $instance, string $field, array $value): void
    {
        match ($field) {
            'account_update' => $this->handleAccountUpdate($instance, $value),
            'account_review_update' => $this->handleAccountReview($instance, $value),
            'account_alerts' => $this->handleAccountAlert($instance, $value),
            'business_capability_update' => $this->handleCapabilityUpdate($instance, $value),
            'phone_number_name_update' => $this->handleNameUpdate($instance, $value),
            'security' => $this->handleSecurity($instance, $value),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function handleAccountUpdate(WhatsappInstance $instance, array $value): void
    {
        $event = strtoupper((string) ($value['event'] ?? ''));
        $patch = [];

        if ($event === 'ACCOUNT_RESTRICTION') {
            $patch['restrictions'] = $this->normaliseRestrictions((array) ($value['restriction_info'] ?? []));
        }

        if ($event === 'ACCOUNT_VIOLATION') {
            $violation = is_array($value['violation_info'] ?? null) ? $value['violation_info'] : [];
            $patch['violation_type'] = $this->nullableString($violation['violation_type'] ?? null);
        }

        if ($event === 'DISABLED_UPDATE') {
            $banInfo = is_array($value['ban_info'] ?? null) ? $value['ban_info'] : [];
            $banState = $this->nullableString($banInfo['waba_ban_state'] ?? null);

            // REINSTATE clears the ban rather than recording one — storing it as a
            // ban state would leave a reinstated account permanently flagged.
            $instance->update([
                'meta_ban_state' => strtoupper((string) $banState) === 'REINSTATE' ? null : $banState,
            ]);

            $patch['ban_date'] = $this->nullableString($banInfo['waba_ban_date'] ?? null);
        }

        if ($event === 'ACCOUNT_DELETED') {
            $instance->update(['meta_ban_state' => 'ACCOUNT_DELETED']);
        }

        $this->mergeAccountDetail($instance, $patch + ['last_event' => $event]);

        Log::warning('meta.account_update', [
            'instance' => $instance->name,
            'waba_id' => $instance->meta_waba_id,
            'event' => $event,
        ]);

        $this->requestHealthRefresh($instance);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function handleAccountReview(WhatsappInstance $instance, array $value): void
    {
        $decision = $this->nullableString($value['decision'] ?? null);

        $instance->update(['meta_account_review_status' => $decision]);

        Log::info('meta.account_review_update', [
            'instance' => $instance->name,
            'decision' => $decision,
        ]);

        $this->requestHealthRefresh($instance);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function handleAccountAlert(WhatsappInstance $instance, array $value): void
    {
        $alert = is_array($value['alert_info'] ?? null) ? $value['alert_info'] : [];

        $entry = [
            'entity_type' => $this->nullableString($value['entity_type'] ?? null),
            'entity_id' => $this->nullableString($value['entity_id'] ?? null),
            'type' => $this->nullableString($alert['alert_type'] ?? null),
            'severity' => $this->nullableString($alert['alert_severity'] ?? null),
            'status' => $this->nullableString($alert['alert_status'] ?? null),
            'description' => $this->nullableString($alert['alert_description'] ?? null),
            'received_at' => now()->toIso8601String(),
        ];

        $detail = $this->accountDetail($instance);
        $alerts = array_values(array_filter(
            (array) ($detail['alerts'] ?? []),
            // One live alert per type: Meta re-sends the same alert_type as its
            // status changes, and keeping the history would grow this column
            // without bound and render duplicates in the drawer.
            fn (mixed $existing): bool => is_array($existing) && ($existing['type'] ?? null) !== $entry['type'],
        ));

        // RESOLVED alerts are dropped rather than kept with a status: the drawer
        // lists this array as "what is wrong right now".
        if (strtoupper((string) $entry['status']) !== 'RESOLVED') {
            $alerts[] = $entry;
        }

        $this->mergeAccountDetail($instance, ['alerts' => $alerts]);

        Log::info('meta.account_alert', [
            'instance' => $instance->name,
            'alert_type' => $entry['type'],
            'severity' => $entry['severity'],
        ]);
    }

    /**
     * Messaging limits moved to the business portfolio on 2025-10-07, so this
     * ceiling is shared by every number in the portfolio rather than owned by
     * the number that happened to trigger the webhook.
     *
     * Meta delivers one of these per WABA, so writing it across the WABA's
     * instances covers the portfolio without guessing at its membership.
     *
     * @param  array<string, mixed>  $value
     */
    private function handleCapabilityUpdate(WhatsappInstance $instance, array $value): void
    {
        $limit = self::portfolioLimitTier(
            $value['max_daily_conversations_per_business']
                // Removed by Meta in February 2026; still read so a payload from
                // an older webhook version is not silently ignored.
                ?? $value['max_daily_conversation_per_phone']
                ?? null
        );

        if ($limit === null) {
            return;
        }

        $this->instancesSharingPortfolio($instance)
            ->update(['meta_portfolio_messaging_limit' => $limit]);

        // The mass update bypassed this model, so pull the row back in line with
        // the database for anything reading the instance after this handler.
        $instance->refresh();

        Log::info('meta.business_capability_update', [
            'instance' => $instance->name,
            'waba_id' => $instance->meta_waba_id,
            'portfolio_messaging_limit' => $limit,
            'max_phone_numbers_per_waba' => $value['max_phone_numbers_per_waba'] ?? null,
        ]);
    }

    /**
     * Normalise Meta's messaging limit into a TIER_ constant.
     *
     * Shared with TemplateStatusUpdateService: `phone_number_quality_update`
     * carries the same parameter.
     */
    public static function portfolioLimitTier(mixed $value): ?string
    {
        if (is_string($value) && str_starts_with(strtoupper(trim($value)), 'TIER_')) {
            return strtoupper(trim($value));
        }

        if (! is_numeric($value)) {
            return null;
        }

        return self::LIMIT_TIERS[(int) $value] ?? null;
    }

    /**
     * @return Builder<WhatsappInstance>
     */
    private function instancesSharingPortfolio(WhatsappInstance $instance): Builder
    {
        $query = WhatsappInstance::withoutGlobalScope('tenant');

        // A null WABA id would match every unlinked instance in the database,
        // so an instance without one only ever updates itself.
        return filled($instance->meta_waba_id)
            ? $query->where('meta_waba_id', $instance->meta_waba_id)
            : $query->whereKey($instance->getKey());
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function handleNameUpdate(WhatsappInstance $instance, array $value): void
    {
        $decision = $this->nullableString($value['decision'] ?? null);

        $instance->update(['meta_name_status' => $decision]);

        $this->mergeAccountDetail($instance, [
            'name_decision' => $decision,
            'name_rejection_reason' => $this->nullableString($value['rejection_reason'] ?? null),
            'requested_verified_name' => $this->nullableString($value['requested_verified_name'] ?? null),
        ]);

        Log::info('meta.phone_number_name_update', [
            'instance' => $instance->name,
            'decision' => $decision,
        ]);

        // An approved display name lifts the messaging limit that made the number
        // LIMITED, so re-probe instead of leaving a stale restriction on screen.
        $this->requestHealthRefresh($instance);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function handleSecurity(WhatsappInstance $instance, array $value): void
    {
        $this->mergeAccountDetail($instance, [
            'security_event' => $this->nullableString($value['event'] ?? null),
            'security_event_at' => now()->toIso8601String(),
        ]);

        Log::warning('meta.security_event', [
            'instance' => $instance->name,
            'event' => $value['event'] ?? null,
        ]);
    }

    /**
     * @param  array<int, mixed>  $restrictionInfo
     * @return list<array<string, mixed>>
     */
    private function normaliseRestrictions(array $restrictionInfo): array
    {
        $restrictions = [];

        foreach ($restrictionInfo as $restriction) {
            if (! is_array($restriction)) {
                continue;
            }

            $type = $this->nullableString($restriction['restriction_type'] ?? null);

            if ($type === null) {
                continue;
            }

            $expiration = $restriction['expiration'] ?? null;

            $restrictions[] = [
                'type' => strtoupper($type),
                'expires_at' => is_numeric($expiration)
                    ? now()->setTimestamp((int) $expiration)->toIso8601String()
                    : null,
            ];
        }

        return $restrictions;
    }

    /**
     * @return array<string, mixed>
     */
    private function accountDetail(WhatsappInstance $instance): array
    {
        $detail = $instance->meta_account_restrictions;

        return is_array($detail) ? $detail : [];
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function mergeAccountDetail(WhatsappInstance $instance, array $patch): void
    {
        $instance->update([
            'meta_account_restrictions' => array_merge(
                $this->accountDetail($instance),
                $patch,
                ['updated_at' => now()->toIso8601String()],
            ),
        ]);
    }

    private function requestHealthRefresh(WhatsappInstance $instance): void
    {
        SyncMetaHealthJob::dispatch($instance->id);

        // The queued probe carries the authoritative status, but a restriction or
        // ban webhook is exactly when an operator is watching the screen. Push the
        // detail change immediately so the panel does not look idle until the job
        // lands.
        InstanceHealthChanged::dispatch(
            $instance->id,
            $instance->healthStatus()->value,
            $instance->healthReasons(),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
