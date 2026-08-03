<?php

namespace App\Services\WhatsApp;

use App\Enums\MetaHealthStatus;
use App\Models\WhatsappInstance;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads the real messaging health of a Meta Cloud number straight from the Graph
 * API, so the panel reports what Meta actually thinks of the WABA instead of
 * "we still hold an access token".
 *
 * One request per instance, against the business phone number node: its
 * `health_status` payload already carries the WABA, business portfolio and app
 * entities, so the WABA node does not need a second call. Account restrictions,
 * bans and review decisions arrive through the `account_update` /
 * `account_review_update` webhooks instead of being polled.
 *
 * @phpstan-type HealthEntity array{type: string, id: string, status: string, reasons: list<string>}
 */
class MetaHealthService
{
    /**
     * Graph fields on the WhatsApp Business Phone Number node. `health_status`
     * is the messaging verdict; the rest is the supporting detail the drawer
     * renders (display name approval, verification, limit, throughput).
     *
     * `whatsapp_business_manager_messaging_limit` replaced `messaging_limit_tier`
     * when Meta moved messaging limits to the business portfolio on 2025-10-07.
     * It reports the portfolio's limit — shared by every number in it — and is
     * requestable straight from the phone number node, so this stays one call.
     */
    private const PHONE_NUMBER_FIELDS = 'health_status,quality_rating,name_status,code_verification_status,whatsapp_business_manager_messaging_limit,throughput,platform_type,display_phone_number,verified_name,status';

    /**
     * Field set without the portfolio limit, for Graph versions that predate it.
     * `messaging_limit_tier` is deprecated and reports the old per-number limit,
     * but a stale limit beats losing the whole health snapshot.
     */
    private const LEGACY_PHONE_NUMBER_FIELDS = 'health_status,quality_rating,name_status,code_verification_status,messaging_limit_tier,throughput,platform_type,display_phone_number,verified_name,status';

    /**
     * Entities Meta reports for a messaging request, in the order the UI shows them.
     *
     * @var array<string, string>
     */
    public const ENTITY_LABELS = [
        'PHONE_NUMBER' => 'Número',
        'WABA' => 'Conta WhatsApp (WABA)',
        'BUSINESS' => 'Portfólio empresarial',
        'APP' => 'Aplicativo',
        'MESSAGE_TEMPLATE' => 'Template',
    ];

    public function __construct(
        private readonly ?string $graphApiVersion = null,
    ) {}

    /**
     * Probe Meta and persist the snapshot on the instance.
     *
     * Always writes `meta_health_checked_at` — including on failure, where the
     * status degrades to UNKNOWN. A failed probe must never read as healthy, and
     * must never read as blocked either: `MetaHealthStatus::isDegraded()` is
     * false for UNKNOWN precisely so a Graph outage cannot stop every campaign.
     */
    public function refresh(WhatsappInstance $instance, bool $refreshAccountNames = false): MetaHealthStatus
    {
        $snapshot = $this->probe(
            (string) $instance->meta_phone_number_id,
            $instance->meta_access_token,
        );

        $snapshot += $this->accountNamesPatch($instance, $refreshAccountNames);

        $instance->update($snapshot);

        Log::info('meta.health.refreshed', [
            'instance' => $instance->name,
            'phone_number_id' => $instance->meta_phone_number_id,
            'status' => $snapshot['meta_health_status'],
        ]);

        return MetaHealthStatus::fromMeta($snapshot['meta_health_status']);
    }

    /**
     * Fetch and normalise the phone number node into instance columns.
     *
     * @return array<string, mixed>
     */
    public function probe(string $phoneNumberId, ?string $accessToken): array
    {
        if ($phoneNumberId === '' || ! filled($accessToken)) {
            return $this->unknownSnapshot();
        }

        $response = $this->requestNode($phoneNumberId, (string) $accessToken, self::PHONE_NUMBER_FIELDS);

        // Graph rejects the whole request for an unknown field rather than
        // omitting it, so a version without the portfolio limit field would take
        // the entire health snapshot down with it. Retry once without it.
        if ($response?->successful() === false && $this->rejectedPortfolioLimitField($response)) {
            Log::warning('meta.health.portfolio_limit_unsupported', [
                'phone_number_id' => $phoneNumberId,
                'graph_version' => $this->version(),
            ]);

            $response = $this->requestNode($phoneNumberId, (string) $accessToken, self::LEGACY_PHONE_NUMBER_FIELDS);
        }

        if ($response === null) {
            return $this->unknownSnapshot();
        }

        if (! $response->successful()) {
            Log::warning('meta.health.probe_failed', [
                'phone_number_id' => $phoneNumberId,
                'status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);

            return $this->unknownSnapshot();
        }

        return $this->snapshotFromPayload((array) $response->json());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function snapshotFromPayload(array $payload): array
    {
        $health = is_array($payload['health_status'] ?? null) ? $payload['health_status'] : [];
        $entities = $this->normaliseEntities((array) ($health['entities'] ?? []));

        return [
            'meta_health_status' => $this->deriveOverallStatus($entities, $health)->value,
            'meta_health_entities' => $entities,
            'meta_name_status' => $this->nullableString($payload['name_status'] ?? null),
            'meta_code_verification_status' => $this->nullableString($payload['code_verification_status'] ?? null),
            'meta_portfolio_messaging_limit' => $this->nullableString(
                $payload['whatsapp_business_manager_messaging_limit']
                    ?? $payload['messaging_limit_tier']
                    ?? null
            ),
            'meta_throughput_level' => $this->nullableString(
                is_array($payload['throughput'] ?? null) ? ($payload['throughput']['level'] ?? null) : null
            ),
            'meta_number_status' => $this->nullableString($payload['status'] ?? null),
            'meta_verified_name' => $this->nullableString($payload['verified_name'] ?? null),
            'meta_health_checked_at' => now(),
        ] + $this->qualityRatingPatch($payload);
    }

    /**
     * Names of the WhatsApp account and the business portfolio that owns it.
     *
     * The panel used to show bare IDs, which say nothing to the person reading
     * them. One `GET /{waba_id}` returns both names, so this is a single extra
     * call — and it only runs when a name is missing or the user pressed
     * refresh, keeping the 15 minute scheduler at one request per instance.
     *
     * @return array<string, string|null>
     */
    private function accountNamesPatch(WhatsappInstance $instance, bool $force): array
    {
        $wabaId = (string) $instance->meta_waba_id;

        if ($wabaId === '' || ! filled($instance->meta_access_token)) {
            return [];
        }

        if (! $force && filled($instance->meta_waba_name)) {
            return [];
        }

        // A coexistence token can read the phone number node without ever
        // reaching the WABA node, so this lookup can fail permanently. Left
        // ungated, the 15 minute scheduler would log the same warning ~96 times
        // a day per instance and bury everything worth reading.
        if (! $force && ! Cache::add("meta_account_names:{$instance->id}", true, now()->addHours(6))) {
            return [];
        }

        try {
            $response = Http::withToken((string) $instance->meta_access_token)
                ->timeout(10)
                ->get("https://graph.facebook.com/{$this->version()}/{$wabaId}", [
                    'fields' => 'name,owner_business_info{name}',
                ]);
        } catch (Throwable $e) {
            Log::warning('meta.health.account_names_exception', [
                'waba_id' => $wabaId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('meta.health.account_names_failed', [
                'waba_id' => $wabaId,
                'status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);

            return [];
        }

        // A coexistence token can read the phone number node without reaching the
        // WABA node, so keep whatever name is already stored rather than blanking it.
        return array_filter([
            'meta_waba_name' => $this->nullableString($response->json('name')),
            'meta_business_name' => $this->nullableString($response->json('owner_business_info.name')),
        ], fn (?string $name): bool => $name !== null);
    }

    /**
     * Derive the messaging verdict from the per-entity `can_send_message` values
     * rather than trusting `health_status.can_send_message`.
     *
     * Meta documents the aggregate as covering messaging only, but its own
     * examples show it set to BLOCKED while every entity's `can_send_message` is
     * AVAILABLE — the block came from `can_receive_call_sip`. Recomputing from
     * the messaging field keeps a number that merely lacks SIP configuration from
     * being reported as unable to message (and, once the campaign gate is wired,
     * from having its campaigns refused).
     *
     * @param  list<array<string, mixed>>  $entities
     * @param  array<string, mixed>  $health
     */
    private function deriveOverallStatus(array $entities, array $health): MetaHealthStatus
    {
        if ($entities === []) {
            return MetaHealthStatus::fromMeta($health['can_send_message'] ?? null);
        }

        $statuses = array_map(
            fn (array $entity): MetaHealthStatus => MetaHealthStatus::fromMeta($entity['status'] ?? null),
            $entities,
        );

        foreach ([MetaHealthStatus::Blocked, MetaHealthStatus::Limited] as $degraded) {
            if (in_array($degraded, $statuses, true)) {
                return $degraded;
            }
        }

        return in_array(MetaHealthStatus::Unknown, $statuses, true)
            ? MetaHealthStatus::Unknown
            : MetaHealthStatus::Available;
    }

    /**
     * Flatten Meta's entity list into the shape the drawer renders.
     *
     * `additional_info` (LIMITED) and `errors` (BLOCKED) are only harvested when
     * that entity's own `can_send_message` is degraded. An entity can carry
     * errors that belong to `can_receive_call_sip` while messaging is fine;
     * surfacing those would tell the user their number is blocked from sending
     * when it is only missing calling configuration.
     *
     * @param  array<int, mixed>  $entities
     * @return list<array<string, mixed>>
     */
    private function normaliseEntities(array $entities): array
    {
        $normalised = [];

        foreach ($entities as $entity) {
            if (! is_array($entity)) {
                continue;
            }

            $status = MetaHealthStatus::fromMeta($entity['can_send_message'] ?? null);

            $normalised[] = [
                'type' => strtoupper((string) ($entity['entity_type'] ?? 'UNKNOWN')),
                'id' => (string) ($entity['id'] ?? ''),
                'status' => $status->value,
                'reasons' => $status->isDegraded() ? $this->reasonsFor($entity) : [],
            ];
        }

        return $normalised;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return list<string>
     */
    private function reasonsFor(array $entity): array
    {
        $reasons = [];

        foreach ((array) ($entity['additional_info'] ?? []) as $info) {
            if (is_string($info) && trim($info) !== '') {
                $reasons[] = trim($info);
            }
        }

        foreach ((array) ($entity['errors'] ?? []) as $error) {
            if (! is_array($error)) {
                continue;
            }

            $description = trim((string) ($error['error_description'] ?? ''));
            $solution = trim((string) ($error['possible_solution'] ?? ''));

            $text = trim($description.($solution !== '' ? " {$solution}" : ''));

            if ($text !== '') {
                $reasons[] = $text;
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * Only overwrite the webhook-owned quality rating when Meta actually returned
     * one. `quality_rating` is absent on numbers with no messaging volume, and a
     * blind write would erase a rating the `phone_number_quality_update` webhook
     * had already delivered.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function qualityRatingPatch(array $payload): array
    {
        $rating = $this->nullableString($payload['quality_rating'] ?? null);

        if ($rating === null || strtoupper($rating) === 'NA' || strtoupper($rating) === 'UNKNOWN') {
            return [];
        }

        return ['meta_quality_rating' => strtoupper($rating)];
    }

    private function requestNode(string $phoneNumberId, string $accessToken, string $fields): ?Response
    {
        try {
            return Http::withToken($accessToken)
                ->timeout(10)
                ->get("https://graph.facebook.com/{$this->version()}/{$phoneNumberId}", [
                    'fields' => $fields,
                ]);
        } catch (Throwable $e) {
            Log::warning('meta.health.probe_exception', [
                'phone_number_id' => $phoneNumberId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function rejectedPortfolioLimitField(Response $response): bool
    {
        return str_contains(
            (string) $response->json('error.message'),
            'whatsapp_business_manager_messaging_limit',
        );
    }

    /** @return array<string, mixed> */
    private function unknownSnapshot(): array
    {
        return [
            'meta_health_status' => MetaHealthStatus::Unknown->value,
            'meta_health_entities' => [],
            'meta_health_checked_at' => now(),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function version(): string
    {
        return $this->graphApiVersion ?? (string) config('services.meta.graph_api_version', 'v23.0');
    }
}
