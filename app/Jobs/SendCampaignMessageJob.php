<?php

namespace App\Jobs;

use App\Events\CampaignProgressUpdated;
use App\Exceptions\MetaInvalidNumberException;
use App\Exceptions\MetaNoWhatsAppException;
use App\Exceptions\MetaRateLimitException;
use App\Models\CampaignMessage;
use App\Models\WhatsappTemplate;
use App\Services\AgentInteractionEventService;
use App\Services\BroadcastDebouncer;
use App\Services\CampaignService;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\WhatsApp\PhoneNumberValidator;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class SendCampaignMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly CampaignMessage $campaignMessage,
        public readonly ?string $interactionId = null,
    ) {
        $this->onQueue('campaigns');
    }

    public function handle(CampaignService $service, WhatsAppProviderFactory $factory, BroadcastDebouncer $debouncer): void
    {
        $interactionEvents = app(AgentInteractionEventService::class);
        $interactionId = $this->interactionId ?? $interactionEvents->newInteractionId();
        $message = $this->campaignMessage->fresh();

        if (! $message) {
            return;
        }

        $campaign = $message->campaign()->with(['whatsappInstance', 'whatsappTemplate'])->first();

        if (! $campaign || ! $campaign->isSending()) {
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

        $entry = $message->contactListEntry;

        if (! $entry) {
            $message->markFailed('NO_ENTRY', 'Contact list entry not found');
            $campaign->incrementCounter('total_failed');
            $service->checkAndAutoPause($campaign);

            return;
        }

        // Resolve template params from mapping
        $resolvedParams = $this->resolveTemplateParams(
            $campaign->template_params_mapping ?? [],
            $entry
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

        $instance = $campaign->whatsappInstance;
        $template = $campaign->whatsappTemplate;

        if (! $instance || ! $template) {
            $message->markFailed('NO_INSTANCE_OR_TEMPLATE', 'Campaign is missing a WhatsApp instance or template.');
            $campaign->incrementCounter('total_failed');
            $service->checkAndAutoPause($campaign->fresh());

            return;
        }

        if ($template->status !== 'APPROVED') {
            $message->markFailed('TEMPLATE_NOT_APPROVED', "Template status: {$template->status}");
            $campaign->incrementCounter('total_failed');
            $service->checkAndAutoPause($campaign->fresh());

            return;
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
            $campaign->incrementCounter('total_failed');
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

            $providerMessageId = $provider->sendTemplate(
                phone: $destination,
                templateName: (string) ($template->meta_template_name ?? $template->name),
                langCode: (string) ($template->language ?? 'pt_BR'),
                components: $this->buildMetaComponents($resolvedParams, $template),
            );

            $message->markSent($providerMessageId);
            $campaign->incrementCounter('total_sent');

            $this->dispatchProgressIfReady($debouncer, $campaign->fresh());
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
            $message->update(['status' => 'pending']);
            $this->release($delay + random_int(0, 60));

            return;
        } catch (MetaInvalidNumberException|MetaNoWhatsAppException $e) {
            // Permanent destination failures — no retry, no exception propagation.
            $code = $e instanceof MetaInvalidNumberException ? 'INVALID_NUMBER' : 'NO_WHATSAPP';
            $message->markFailed($code, $e->getMessage());
            $campaign->incrementCounter('total_failed');
            $freshCampaign = $campaign->fresh();
            $service->checkAndAutoPause($freshCampaign);
            $this->dispatchProgressIfReady($debouncer, $freshCampaign);

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
        } catch (Throwable $e) {
            $message->markFailed('EXCEPTION', $e->getMessage());
            $campaign->incrementCounter('total_failed');
            $freshCampaign = $campaign->fresh();
            $service->checkAndAutoPause($freshCampaign);
            $this->dispatchProgressIfReady($debouncer, $freshCampaign);

            $interactionEvents->record(
                interactionId: $interactionId,
                tenantId: $campaign->tenant_id,
                eventType: 'outbound_failed',
                eventSource: 'send_campaign_message_job',
                payload: [
                    'campaign_id' => $campaign->id,
                    'campaign_message_id' => $message->id,
                    'error' => $e->getMessage(),
                ],
                severity: 'error',
            );

            throw $e;
        }
    }

    private function dispatchProgressIfReady(BroadcastDebouncer $debouncer, ?\App\Models\Campaign $campaign): void
    {
        if (! $campaign) {
            return;
        }

        if ($debouncer->shouldFire("campaign:{$campaign->id}:progress", 2)) {
            CampaignProgressUpdated::dispatch($campaign->id, [
                'sent' => (int) ($campaign->total_sent ?? 0),
                'delivered' => (int) ($campaign->total_delivered ?? 0),
                'failed' => (int) ($campaign->total_failed ?? 0),
                'read' => (int) ($campaign->total_read ?? 0),
                'pending' => (int) ($campaign->total_recipients ?? 0) - (int) ($campaign->total_sent ?? 0) - (int) ($campaign->total_failed ?? 0),
            ]);
        }
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

        $message->markFailed('JOB_FAILED', $e->getMessage());

        $campaign = $message->campaign;

        if ($campaign) {
            $campaign->incrementCounter('total_failed');
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
    private function resolveTemplateParams(array $mapping, \App\Models\ContactListEntry $entry): array
    {
        $resolved = [];
        $contact = $entry->contact_id ? $entry->contact()->withoutGlobalScopes()->first() : null;

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
        if ($indexes === []) {
            return [];
        }

        return array_values(array_map(
            fn (string $index): array => ['type' => 'text', 'text' => (string) ($resolved[$index] ?? '')],
            $indexes
        ));
    }
}
