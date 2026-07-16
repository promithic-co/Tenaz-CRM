<?php

namespace App\Jobs;

use App\Events\CampaignProgressUpdated;
use App\Exceptions\MetaAmbiguousSendException;
use App\Exceptions\MetaInvalidNumberException;
use App\Exceptions\MetaNoWhatsAppException;
use App\Exceptions\MetaRateLimitException;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\Contact;
use App\Models\ContactListEntry;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\AgentInteractionEventService;
use App\Services\BroadcastDebouncer;
use App\Services\CampaignService;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\WhatsApp\PhoneNumberValidator;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class SendCampaignMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    // Cap genuine failures, not waits. Every $this->release() (per-tenant fairness gate, per-instance
    // throttle, Meta-429 backoff) re-pops the job and increments attempts(), which an attempt-count
    // `tries` would treat as a failed try — failing a message for merely queueing behind a gate.
    // maxExceptions only counts *thrown* exceptions (Worker::markJobAsFailedIfWillExceedMaxExceptions),
    // so real provider errors still fail fast after 3 while throttle releases stay free. Paired with
    // retryUntil() below, which makes the worker ignore attempts() entirely while inside the window.
    public int $maxExceptions = 3;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly CampaignMessage $campaignMessage,
        public readonly ?string $interactionId = null,
        public readonly ?\DateTimeInterface $scheduledFor = null,
    ) {
        $this->onQueue('campaigns');
    }

    /**
     * Time-based retry budget. When set and unexpired, the worker skips the attempts()>=tries check
     * (Worker::markJobAsFailedIfAlreadyExceedsMaxAttempts), so a message released repeatedly by a
     * fairness/throttle gate is never failed just for waiting; genuine errors are still bounded by
     * $maxExceptions. The deadline is stamped once at first dispatch (Laravel serializes it into the
     * payload), so it does not slide on each release.
     *
     * Anchored to $scheduledFor, not dispatch time: the fan-out staggers each message by
     * index * delay_between_ms, so a deadline counted from dispatch would already be expired when a
     * far-tail message first pops — the worker fails such a job before ever running it. Counting the
     * window from the message's own send slot gives every message the full budget once it becomes
     * eligible. Returns null when the window is 0/disabled, which reverts to the plain
     * attempt-count $tries behaviour.
     */
    public function retryUntil(): ?\DateTimeInterface
    {
        $windowSeconds = (int) config('credflow.campaigns.send_retry_window_seconds', 21600);

        if ($windowSeconds <= 0) {
            return null;
        }

        $base = $this->scheduledFor !== null ? Carbon::instance($this->scheduledFor) : now();

        return $base->addSeconds($windowSeconds);
    }

    public function handle(CampaignService $service, WhatsAppProviderFactory $factory, BroadcastDebouncer $debouncer): void
    {
        $interactionEvents = app(AgentInteractionEventService::class);
        $interactionId = $this->interactionId ?? $interactionEvents->newInteractionId();
        $message = $this->campaignMessage->fresh();

        if (! $message) {
            return;
        }

        $campaign = $message->campaign()->first();

        if (! $campaign || ! $campaign->isSending()) {
            // Park the row back in 'pending': returning consumes this queue job, so a message
            // whose delayed job fires during a pause would otherwise be stranded 'queued' with
            // no live job — and the resume dispatcher skips 'queued' rows. 'pending' with no
            // provider attempt marks it re-enqueueable by the next DispatchCampaignJob run.
            if ($campaign
                && in_array($message->status, ['pending', 'queued'], true)
                && $message->provider_attempted_at === null) {
                $message->update(['status' => 'pending']);
            }

            Log::info('SendCampaignMessageJob: campaign not sending, skipping', [
                'interaction_id' => $interactionId,
                'message_id' => $message->id,
                'campaign_status' => $campaign?->status,
            ]);

            return;
        }

        if (! in_array($message->status, ['pending', 'queued'])) {
            return;
        }

        // Defensive in-doubt guard: a prior attempt already reached the provider POST
        // stage without confirming. Re-POSTing risks a duplicate template send.
        if ($message->provider_attempted_at !== null) {
            $message->markInDoubt('Re-execution after an unconfirmed provider attempt; not re-sending.');

            return;
        }

        // Daily-limit safety net (CAMP-04): the fan-out enqueues only within the day's budget,
        // but day-boundary drift, resumes, and concurrent workers at the limit can still pop a
        // job past it. Park the row 'pending' (CAMP-02 semantics: no live job, re-enqueueable)
        // for the monitor revive to re-dispatch inside the next day's budget.
        if (! $service->checkDailyLimit($campaign)) {
            $message->update(['status' => 'pending']);

            Log::info('SendCampaignMessageJob: daily limit reached, parking message for revive', [
                'interaction_id' => $interactionId,
                'message_id' => $message->id,
                'campaign_id' => $campaign->id,
            ]);

            return;
        }

        $entry = $message->contactListEntry;

        if (! $entry) {
            $message->markFailed('NO_ENTRY', 'Contact list entry not found');
            $service->checkAndAutoPause($campaign);

            return;
        }

        $contact = $entry->contact_id ? $entry->contact()->withoutGlobalScopes()->first() : null;

        // Consent gate at send time (CAMP-05 / LGPD): the fan-out suppression only covers
        // opt-outs known at dispatch — a contact who opts out while their staggered/parked job
        // waits (up to hours or days) would still receive the template. The entry snapshot OR
        // the canonical contact opting out suppresses the send here, right before any side
        // effect. Skipped is terminal and deliberately not 'failed', so a mass opt-out can
        // never trip the failure-rate auto-pause.
        if ($entry->opt_in_status === 'opted_out' || ($contact !== null && $contact->opt_in_status === Contact::OPT_OUT)) {
            $message->markSkipped('OPTED_OUT', 'Recipient opted out before the send slot.');

            Log::info('SendCampaignMessageJob: recipient opted out, skipping send', [
                'interaction_id' => $interactionId,
                'message_id' => $message->id,
                'campaign_id' => $campaign->id,
            ]);

            $interactionEvents->record(
                interactionId: $interactionId,
                tenantId: $campaign->tenant_id,
                eventType: 'outbound_skipped_optout',
                eventSource: 'send_campaign_message_job',
                payload: [
                    'campaign_id' => $campaign->id,
                    'campaign_message_id' => $message->id,
                    'contact_list_entry_id' => $entry->id,
                ],
                severity: 'warning',
            );

            return;
        }

        // Resolve template params from mapping
        $resolvedParams = $this->resolveTemplateParams(
            $campaign->template_params_mapping ?? [],
            $entry,
            $contact
        );

        $message->update(['status' => 'queued', 'template_params_resolved' => $resolvedParams]);

        $interactionEvents->record(
            interactionId: $interactionId,
            tenantId: $campaign->tenant_id,
            eventType: 'outbound_queued',
            eventSource: 'send_campaign_message_job',
            payload: [
                'campaign_id' => $campaign->id,
                'campaign_message_id' => $message->id,
                'contact_list_entry_id' => $entry->id,
                'channel' => 'whatsapp',
                'source' => 'campaign',
            ],
        );

        [$instance, $template] = $this->resolveSendConfig($campaign);

        if (! $instance || ! $template) {
            $message->markFailed('NO_INSTANCE_OR_TEMPLATE', 'Campaign is missing a WhatsApp instance or template.');
            $service->checkAndAutoPause($campaign->fresh());

            return;
        }

        if ($template->status !== 'APPROVED') {
            $message->markFailed('TEMPLATE_NOT_APPROVED', "Template status: {$template->status}");
            $service->checkAndAutoPause($campaign->fresh());

            return;
        }

        // Per-tenant fairness gate (SCALE-2): the `campaigns` queue is a single FIFO shared by
        // every tenant, so one large tenant's fan-out can occupy all campaign workers and starve
        // smaller tenants (and the latency-sensitive delivery webhooks on the same queue). Cap
        // sends per tenant per minute using the same sliding-counter shape as the per-instance
        // throttle below; an over-budget job releases back to the queue tail, freeing workers to
        // pick up other tenants' jobs. Backed by the cache store (Redis in prod — atomic and shared
        // across workers) for parity with the SCALE-1 gate and deterministic tests. Fails open on a
        // cache outage so a degraded cache can never stall all sends. 0 disables (the per-instance
        // rate_per_minute already bounds single-instance tenants).
        $tenantRatePerMinute = (int) config('credflow.campaigns.tenant_rate_per_minute', 0);
        if ($tenantRatePerMinute > 0) {
            $tenantBucket = (int) floor(now()->timestamp / 60);
            $tenantThrottleKey = "campaign_tenant_throttle:{$campaign->tenant_id}:{$tenantBucket}";

            try {
                Cache::add($tenantThrottleKey, 0, 120);
                $tenantCount = (int) Cache::increment($tenantThrottleKey);
            } catch (Throwable $e) {
                Log::warning('SendCampaignMessageJob: tenant fairness throttle failed, proceeding', [
                    'error' => $e->getMessage(),
                    'tenant_id' => $campaign->tenant_id,
                ]);
                $tenantCount = 0;
            }

            if ($tenantCount > $tenantRatePerMinute) {
                $tenantDelay = 60 - (now()->timestamp % 60) + random_int(0, 10);

                Log::info('SendCampaignMessageJob: throttled per-tenant for fairness, releasing', [
                    'interaction_id' => $interactionId,
                    'message_id' => $message->id,
                    'tenant_id' => $campaign->tenant_id,
                    'current_minute_count' => $tenantCount,
                    'limit_per_minute' => $tenantRatePerMinute,
                    'release_after_seconds' => $tenantDelay,
                ]);

                $message->update(['status' => 'pending']);
                $this->release($tenantDelay);

                return;
            }
        }

        // Per-instance throttle: protect Meta reputation by capping send rate. We use a
        // simple sliding counter keyed by instance + minute bucket — if the bucket exceeds
        // the configured limit, release the job back to the queue. Cheaper than Redis::funnel
        // and works with any cache store the project happens to use.
        $ratePerMinute = (int) config('credflow.campaigns.rate_per_minute', 80);
        if ($ratePerMinute > 0) {
            $bucket = (int) floor(now()->timestamp / 60);
            $throttleKey = "meta_send_throttle:{$instance->id}:{$bucket}";

            try {
                $current = Redis::incr($throttleKey);
                if ($current === 1) {
                    Redis::expire($throttleKey, 120);
                }
            } catch (Throwable $e) {
                // Redis unavailable — fail open: log and let the send proceed so a Redis
                // outage doesn't block the campaign entirely.
                Log::warning('SendCampaignMessageJob: throttle check failed, proceeding', [
                    'error' => $e->getMessage(),
                    'instance_id' => $instance->id,
                ]);
                $current = 0;
            }

            if ($current > $ratePerMinute) {
                $delay = 60 - (now()->timestamp % 60) + random_int(0, 10);

                Log::info('SendCampaignMessageJob: throttled per-instance, releasing', [
                    'interaction_id' => $interactionId,
                    'message_id' => $message->id,
                    'instance_id' => $instance->id,
                    'current_minute_count' => $current,
                    'limit_per_minute' => $ratePerMinute,
                    'release_after_seconds' => $delay,
                ]);

                $message->update(['status' => 'pending']);
                $this->release($delay);

                return;
            }
        }

        // Validate destination before hitting the provider — bad numbers burn Meta reputation
        // (errors 131026/131027). Mark as failed without retry so the campaign moves on.
        $destination = PhoneNumberValidator::normalize((string) $entry->phone);
        if ($destination === null) {
            $message->markFailed('INVALID_PHONE', "Invalid phone number: {$entry->phone}");
            $service->checkAndAutoPause($campaign->fresh());

            $interactionEvents->record(
                interactionId: $interactionId,
                tenantId: $campaign->tenant_id,
                eventType: 'outbound_failed',
                eventSource: 'send_campaign_message_job',
                payload: [
                    'campaign_id' => $campaign->id,
                    'campaign_message_id' => $message->id,
                    'error' => 'INVALID_PHONE',
                    'raw_phone' => (string) $entry->phone,
                ],
                severity: 'warning',
            );

            return;
        }

        try {
            $provider = $factory->makeProvider($instance);

            // Atomically claim immediately before the POST so an ambiguous failure is never
            // retried. Losing the claim means a concurrent duplicate job (a resume re-enqueue
            // racing a released original) owns this message — let the owner finish it.
            if (! $message->claimProviderAttempt()) {
                Log::info('SendCampaignMessageJob: lost provider claim to a concurrent job, skipping', [
                    'interaction_id' => $interactionId,
                    'message_id' => $message->id,
                ]);

                return;
            }

            $providerMessageId = $provider->sendTemplate(
                phone: $destination,
                templateName: (string) ($template->meta_template_name ?? $template->name),
                langCode: (string) ($template->language ?? 'pt_BR'),
                components: $this->buildMetaComponents($resolvedParams, $template),
                opaqueId: (string) $message->id,
            );

            if ($providerMessageId === null || $providerMessageId === '') {
                // 2xx with no message id is undecidable — do not retry into a duplicate.
                throw new MetaAmbiguousSendException('Meta returned no message id for the template send.');
            }

            $message->markSent($providerMessageId);

            $this->dispatchProgressIfReady($debouncer, $campaign);
            app(DashboardMetricsService::class)->dispatchUpdate((string) $campaign->tenant_id);

            Log::info('SendCampaignMessageJob: sent', [
                'interaction_id' => $interactionId,
                'message_id' => $message->id,
                'provider' => $instance->provider->value,
            ]);

            $interactionEvents->record(
                interactionId: $interactionId,
                tenantId: $campaign->tenant_id,
                eventType: 'outbound_sent',
                eventSource: 'send_campaign_message_job',
                payload: [
                    'campaign_id' => $campaign->id,
                    'campaign_message_id' => $message->id,
                    'provider' => $instance->provider->value,
                    'provider_message_id' => $providerMessageId,
                    'destination' => $destination,
                ],
            );
        } catch (MetaRateLimitException $e) {
            // Rate-limit: back off WITHOUT marking the message failed or incrementing the
            // counter. release() reschedules the job so we can try once Meta's window opens.
            $delay = (int) config('credflow.campaigns.rate_limit_release_seconds', 60);

            Log::warning('SendCampaignMessageJob: rate limited by Meta, releasing', [
                'interaction_id' => $interactionId,
                'message_id' => $message->id,
                'release_after_seconds' => $delay,
            ]);

            $interactionEvents->record(
                interactionId: $interactionId,
                tenantId: $campaign->tenant_id,
                eventType: 'outbound_throttled',
                eventSource: 'send_campaign_message_job',
                payload: [
                    'campaign_id' => $campaign->id,
                    'campaign_message_id' => $message->id,
                    'release_after_seconds' => $delay,
                ],
                severity: 'warning',
            );

            // Reset to pending so the job's status check on re-execution lets it through.
            // Meta rejected the send (nothing delivered) — clear the in-doubt marker so the
            // released retry is allowed to actually re-send.
            $message->update(['status' => 'pending']);
            $message->clearProviderAttempt();
            $this->release($delay + random_int(0, 60));

            return;
        } catch (MetaInvalidNumberException|MetaNoWhatsAppException $e) {
            // Permanent destination failures — no retry, no exception propagation.
            $code = $e instanceof MetaInvalidNumberException ? 'INVALID_NUMBER' : 'NO_WHATSAPP';
            $message->markFailed($code, $e->getMessage());
            $service->checkAndAutoPause($campaign->fresh());
            $this->dispatchProgressIfReady($debouncer, $campaign);

            $interactionEvents->record(
                interactionId: $interactionId,
                tenantId: $campaign->tenant_id,
                eventType: 'outbound_failed',
                eventSource: 'send_campaign_message_job',
                payload: [
                    'campaign_id' => $campaign->id,
                    'campaign_message_id' => $message->id,
                    'error_code' => $code,
                    'error' => $e->getMessage(),
                    'destination' => $destination,
                ],
                severity: 'warning',
            );

            return;
        } catch (MetaAmbiguousSendException $e) {
            // Undecidable send (timeout/reset/5xx/empty id). The message MAY have reached
            // the contact, so we neither re-send nor count it as failed. The row is left
            // in_doubt for a webhook (echoing the opaque id) or reconciliation to resolve.
            $message->markInDoubt($e->getMessage());

            $interactionEvents->record(
                interactionId: $interactionId,
                tenantId: $campaign->tenant_id,
                eventType: 'outbound_in_doubt',
                eventSource: 'send_campaign_message_job',
                payload: [
                    'campaign_id' => $campaign->id,
                    'campaign_message_id' => $message->id,
                    'error' => $e->getMessage(),
                    'destination' => $destination,
                ],
                severity: 'error',
            );

            return;
        } catch (Throwable $e) {
            // A connection-refused / DNS failure proves nothing reached Meta — clear the
            // in-doubt marker so the retry re-sends. Any other unknown error keeps the
            // marker (possibly-sent), so a retry resolves to in_doubt rather than duplicating.
            if ($e instanceof ConnectionException) {
                $message->clearProviderAttempt();
            }

            // Transient/unknown error: rethrow WITHOUT finalizing so the queue retries
            // (tries=3, backoff). The message stays 'queued', and only the framework's
            // failed() callback marks it failed + counts it once after retries exhaust.
            // Marking failed here would early-return every retry and make tries/backoff
            // dead config.
            $interactionEvents->record(
                interactionId: $interactionId,
                tenantId: $campaign->tenant_id,
                eventType: 'outbound_retrying',
                eventSource: 'send_campaign_message_job',
                payload: [
                    'campaign_id' => $campaign->id,
                    'campaign_message_id' => $message->id,
                    'error' => $e->getMessage(),
                ],
                severity: 'warning',
            );

            throw $e;
        }
    }

    private function dispatchProgressIfReady(BroadcastDebouncer $debouncer, ?Campaign $campaign): void
    {
        if (! $campaign) {
            return;
        }

        // Gate before reloading: the broadcast is debounced to 1/2s per campaign, so
        // reloading the campaign row on every send (just to maybe-broadcast) is wasted DB
        // load across the fan-out (SCALE-4). Only refresh the counters when we actually fire.
        if (! $debouncer->shouldFire("campaign:{$campaign->id}:progress", 2)) {
            return;
        }

        $campaign = $campaign->fresh() ?? $campaign;

        CampaignProgressUpdated::dispatch($campaign->id, [
            'sent' => (int) ($campaign->total_sent ?? 0),
            'delivered' => (int) ($campaign->total_delivered ?? 0),
            'failed' => (int) ($campaign->total_failed ?? 0),
            'read' => (int) ($campaign->total_read ?? 0),
            'skipped' => (int) ($campaign->total_skipped ?? 0),
            'pending' => (int) ($campaign->total_recipients ?? 0) - (int) ($campaign->total_sent ?? 0) - (int) ($campaign->total_failed ?? 0) - (int) ($campaign->total_skipped ?? 0),
        ]);
    }

    /**
     * Resolve the campaign's WhatsApp instance + template, cached by their own id.
     *
     * The fan-out previously loaded both on every message — two extra queries per send
     * across a 100k-contact campaign. They are cached keyed by entity id (shared across
     * every campaign that uses the same instance/template, not per campaign) and busted
     * the instant either row is saved or deleted via the model observers — so a rotated
     * access token or a revoked/unapproved template takes effect immediately rather than
     * lingering for a TTL window. The TTL is only a backstop for any non-Eloquent write
     * path; the live campaign-status gate still hits the DB per message. Set
     * send_config_cache_seconds=0 to disable and always resolve fresh.
     *
     * @return array{0: ?WhatsappInstance, 1: ?WhatsappTemplate}
     */
    private function resolveSendConfig(Campaign $campaign): array
    {
        $ttl = (int) config('credflow.campaigns.send_config_cache_seconds', 300);

        if ($ttl <= 0) {
            return [
                $campaign->whatsappInstance()->first(),
                $campaign->whatsappTemplate()->first(),
            ];
        }

        $instance = $campaign->whatsapp_instance_id
            ? Cache::remember(
                "whatsapp_send_instance:{$campaign->whatsapp_instance_id}",
                $ttl,
                fn (): ?WhatsappInstance => $campaign->whatsappInstance()->first()
            )
            : null;

        $template = $campaign->whatsapp_template_id
            ? Cache::remember(
                "whatsapp_send_template:{$campaign->whatsapp_template_id}",
                $ttl,
                fn (): ?WhatsappTemplate => $campaign->whatsappTemplate()->first()
            )
            : null;

        return [$instance, $template];
    }

    public function failed(Throwable $e): void
    {
        $message = $this->campaignMessage->fresh();

        if (! $message) {
            return;
        }

        // Idempotency guard: when the in-flight catch already marked the message as failed
        // and incremented the campaign counter, the framework's failed() callback must not
        // double-count. We only finalize messages still in flight (pending/queued).
        if (! in_array($message->status, ['pending', 'queued'], true)) {
            return;
        }

        // A retryUntil expiry while the campaign sits paused is not a send failure — the job
        // never ran and nothing reached the provider. Park the row for the resume dispatcher
        // to re-enqueue with a fresh window instead of failing it.
        if ($e instanceof MaxAttemptsExceededException
            && $message->provider_attempted_at === null
            && $message->campaign?->isPaused()) {
            $message->update(['status' => 'pending']);

            return;
        }

        $message->markFailed('JOB_FAILED', $e->getMessage());

        $campaign = $message->campaign;

        if ($campaign) {
            app(CampaignService::class)->checkAndAutoPause($campaign->fresh());
        }
    }

    /**
     * Resolve template params using dot notation from mapping.
     * Mapping: {"1":"name","2":"extra_data.valor"} → param values.
     *
     * @param  array<string, string>  $mapping
     * @return array<string, string>
     */
    private function resolveTemplateParams(array $mapping, ContactListEntry $entry, ?Contact $contact): array
    {
        $resolved = [];

        foreach ($mapping as $paramIndex => $path) {
            // Canonical contact lookups: prefer contact fields when path starts with "contact.".
            // Falls back to the entry snapshot when the contact isn't linked (legacy entries).
            if (str_starts_with($path, 'contact.')) {
                $sub = substr($path, 8);
                if ($contact !== null) {
                    if ($sub === 'name' || $sub === 'phone' || $sub === 'email' || $sub === 'cpf') {
                        $resolved[(string) $paramIndex] = (string) ($contact->{$sub} ?? '');
                    } elseif (str_starts_with($sub, 'extra_data.')) {
                        $resolved[(string) $paramIndex] = (string) Arr::get(
                            $contact->extra_data ?? [],
                            substr($sub, 11),
                            ''
                        );
                    } else {
                        $resolved[(string) $paramIndex] = (string) data_get($contact, $sub, '');
                    }

                    continue;
                }

                // Fallback to entry-level data for legacy entries with no canonical contact.
                $path = match ($sub) {
                    'name' => 'name',
                    'phone' => 'phone',
                    default => str_starts_with($sub, 'extra_data.') ? $sub : $path,
                };
            }

            if (isset($entry->{$path}) && ! is_array($entry->{$path})) {
                $resolved[(string) $paramIndex] = (string) $entry->{$path};

                continue;
            }

            $parts = explode('.', $path, 2);

            if (count($parts) === 2 && $parts[0] === 'extra_data') {
                $extraData = $entry->extra_data ?? [];
                $resolved[(string) $paramIndex] = (string) Arr::get($extraData, $parts[1], '');

                continue;
            }

            $resolved[(string) $paramIndex] = (string) data_get($entry, $path, '');
        }

        return $resolved;
    }

    /**
     * Build Meta Cloud API components array from resolved params.
     *
     * @param  array<string, string>  $resolved
     * @return list<array<string, mixed>>
     */
    private function buildMetaComponents(array $resolved, ?WhatsappTemplate $template = null): array
    {
        if ($resolved === []) {
            return [];
        }

        $components = $template?->components_json ?? null;
        if (! is_array($components) || $components === []) {
            uksort($resolved, fn (string $a, string $b): int => (int) $a <=> (int) $b);

            $parameters = array_map(fn (string $value): array => [
                'type' => 'text',
                'text' => $value,
            ], array_values($resolved));

            return [
                [
                    'type' => 'body',
                    'parameters' => $parameters,
                ],
            ];
        }

        $built = [];

        foreach ($components as $component) {
            $type = strtolower((string) ($component['type'] ?? ''));

            if ($type === 'header') {
                $parameters = $this->buildParametersForTemplateComponent($component, $resolved);

                if ($parameters !== []) {
                    $built[] = ['type' => 'header', 'parameters' => $parameters];
                }
            }

            if ($type === 'body') {
                $parameters = $this->buildParametersForTemplateComponent($component, $resolved);

                if ($parameters !== []) {
                    $built[] = ['type' => 'body', 'parameters' => $parameters];
                }
            }

            if ($type === 'buttons') {
                foreach (($component['buttons'] ?? []) as $index => $button) {
                    $parameters = $this->buildParametersForTemplateComponent($button, $resolved);

                    if ($parameters !== []) {
                        $built[] = [
                            'type' => 'button',
                            'sub_type' => strtolower((string) ($button['type'] ?? 'url')),
                            'index' => (string) $index,
                            'parameters' => $parameters,
                        ];
                    }
                }
            }
        }

        return $built;
    }

    /**
     * @param  array<string, mixed>  $component
     * @param  array<string, string>  $resolved
     * @return list<array<string, mixed>>
     */
    private function buildParametersForTemplateComponent(array $component, array $resolved): array
    {
        $text = implode(' ', array_filter([
            $component['text'] ?? null,
            $component['url'] ?? null,
        ], 'is_string'));

        preg_match_all('/\{\{(\d+)\}\}/', $text, $matches);

        $indexes = array_values(array_unique($matches[1] ?? []));
        // Meta reads parameters positionally: parameters[0] fills {{1}}, [1] fills {{2}}.
        // Order by the placeholder number, not by appearance in the text, so a body like
        // "{{2}} ... {{1}}" still maps each value to the correct slot.
        sort($indexes, SORT_NUMERIC);
        if ($indexes === []) {
            return [];
        }

        return array_values(array_map(
            fn (string $index): array => ['type' => 'text', 'text' => (string) ($resolved[$index] ?? '')],
            $indexes
        ));
    }
}
