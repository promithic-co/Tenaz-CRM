<?php

namespace App\Models;

use App\Exceptions\MetaApiException;
use Database\Factories\CampaignMessageFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CampaignMessage extends Model
{
    /** @use HasFactory<CampaignMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'contact_list_entry_id',
        'provider_message_id',
        'status',
        'provider_attempted_at',
        'provider_attempt_token',
        'provider_attempt_lease_expires_at',
        'provider_retry_not_before',
        'error_code',
        'error_subcode',
        'provider_error_code',
        'provider_http_status',
        'provider_error_type',
        'provider_error_trace_id',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'template_params_resolved',
    ];

    protected function casts(): array
    {
        return [
            'template_params_resolved' => 'array',
            'provider_http_status' => 'integer',
            'provider_attempted_at' => 'datetime',
            'provider_attempt_lease_expires_at' => 'datetime',
            'provider_retry_not_before' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contactListEntry(): BelongsTo
    {
        return $this->belongsTo(ContactListEntry::class);
    }

    /**
     * Lifecycle progression rank. Single source of truth for status ordering.
     *
     * @var array<string, int>
     */
    private const STATUS_ORDER = [
        'pending' => 0,
        'queued' => 1,
        'in_doubt' => 2,
        'sent' => 3,
        'delivered' => 4,
        'read' => 5,
        'failed' => 6,
        'skipped' => 7,
    ];

    public function statusOrder(): int
    {
        return self::STATUS_ORDER[$this->status] ?? -1;
    }

    public function canTransitionTo(string $newStatus): bool
    {
        // failed can happen at any point; otherwise must be forward progression
        if ($newStatus === 'failed') {
            return ! in_array($this->status, ['delivered', 'read'], true);
        }

        return (self::STATUS_ORDER[$newStatus] ?? -1) > $this->statusOrder();
    }

    public function markSent(string $providerMessageId): void
    {
        $this->update([
            'status' => 'sent',
            'provider_message_id' => $providerMessageId,
            'sent_at' => now(),
            'error_code' => null,
            'error_subcode' => null,
            'provider_error_code' => null,
            'provider_http_status' => null,
            'provider_error_type' => null,
            'provider_error_trace_id' => null,
            'error_message' => null,
            'provider_attempt_token' => null,
            'provider_attempt_lease_expires_at' => null,
            'provider_retry_not_before' => null,
        ]);
    }

    public function markDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markRead(): void
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    public function markFailed(string $errorCode, string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_code' => $errorCode,
            'error_message' => MetaApiException::sanitizeMessage($errorMessage),
            'failed_at' => now(),
            'provider_attempt_token' => null,
            'provider_attempt_lease_expires_at' => null,
            'provider_retry_not_before' => null,
        ]);
    }

    public function markFailedFromProvider(MetaApiException $exception): void
    {
        $this->update([
            'status' => 'failed',
            'error_code' => $exception->getCode() !== 0 ? (string) $exception->getCode() : 'META_REJECTED',
            'error_subcode' => $exception->errorSubcode !== null ? (string) $exception->errorSubcode : null,
            'provider_error_code' => $exception->getCode() !== 0 ? (string) $exception->getCode() : null,
            'provider_http_status' => $exception->httpStatus,
            'provider_error_type' => $exception->errorType,
            'provider_error_trace_id' => $exception->fbtraceId,
            'error_message' => $exception->sanitizedMessage,
            'failed_at' => now(),
            'provider_attempt_token' => null,
            'provider_attempt_lease_expires_at' => null,
            'provider_retry_not_before' => null,
        ]);
    }

    public function recordProviderError(MetaApiException $exception): void
    {
        $this->update([
            'error_code' => $exception->getCode() !== 0 ? (string) $exception->getCode() : null,
            'error_subcode' => $exception->errorSubcode !== null ? (string) $exception->errorSubcode : null,
            'provider_error_code' => $exception->getCode() !== 0 ? (string) $exception->getCode() : null,
            'provider_http_status' => $exception->httpStatus,
            'provider_error_type' => $exception->errorType,
            'provider_error_trace_id' => $exception->fbtraceId,
            'error_message' => $exception->sanitizedMessage,
        ]);
    }

    /**
     * Consent suppression at send time (CAMP-05): terminal, but NOT a delivery failure.
     * Skipped rows stay out of total_failed so a mass opt-out cannot trip the failure-rate
     * auto-pause, and out of total_sent so they never dilute delivery/read rates.
     */
    public function markSkipped(string $errorCode, string $errorMessage): void
    {
        $this->update([
            'status' => 'skipped',
            'error_code' => $errorCode,
            'error_message' => MetaApiException::sanitizeMessage($errorMessage),
            'provider_retry_not_before' => null,
        ]);
    }

    /**
     * Atomically claim the provider POST for this message, stamped only after pre-flight
     * succeeds so a pre-send failure never leaves a false in-doubt marker. The conditional
     * UPDATE (NULL → timestamp) lets exactly one caller win when duplicate queue jobs race
     * for the same row — a resume re-enqueue can legitimately coexist with a released
     * original job. The loser must return without sending.
     */
    public function claimProviderAttempt(?string $attemptToken = null, ?DateTimeInterface $leaseExpiresAt = null): bool
    {
        $now = now();
        $attemptToken ??= (string) Str::uuid();
        $leaseExpiresAt ??= $now->copy()->addSeconds(60);

        $claimed = self::query()
            ->whereKey($this->getKey())
            ->whereIn('status', ['pending', 'queued'])
            ->whereNull('provider_attempted_at')
            ->whereNull('provider_attempt_token')
            ->where(function ($query) use ($now): void {
                $query->whereNull('provider_retry_not_before')
                    ->orWhere('provider_retry_not_before', '<=', $now);
            })
            ->update([
                'provider_attempted_at' => $now,
                'provider_attempt_token' => $attemptToken,
                'provider_attempt_lease_expires_at' => $leaseExpiresAt,
                'provider_retry_not_before' => null,
                'error_code' => null,
                'error_subcode' => null,
                'provider_error_code' => null,
                'provider_http_status' => null,
                'provider_error_type' => null,
                'provider_error_trace_id' => null,
                'error_message' => null,
            ]);

        if ($claimed !== 1) {
            return false;
        }

        $this->provider_attempted_at = $now;
        $this->provider_attempt_token = $attemptToken;
        $this->provider_attempt_lease_expires_at = $leaseExpiresAt;
        $this->provider_retry_not_before = null;
        $this->syncOriginalAttributes([
            'provider_attempted_at',
            'provider_attempt_token',
            'provider_attempt_lease_expires_at',
            'provider_retry_not_before',
        ]);

        return true;
    }

    /**
     * Clear the in-doubt marker on a path that PROVES the message was not sent
     * (Meta rejected, or connection refused before any bytes left the client), so a
     * normal retry/release may re-send without tripping the in-doubt guard.
     */
    public function clearProviderAttempt(): void
    {
        $this->update([
            'provider_attempted_at' => null,
            'provider_attempt_token' => null,
            'provider_attempt_lease_expires_at' => null,
            'provider_retry_not_before' => null,
        ]);
    }

    public function hasActiveProviderAttemptLease(): bool
    {
        return $this->provider_attempted_at !== null
            && $this->provider_attempt_lease_expires_at !== null
            && $this->provider_attempt_lease_expires_at->isFuture();
    }

    public function markAbandonedProviderAttemptInDoubt(): bool
    {
        $query = self::query()
            ->whereKey($this->getKey())
            ->whereIn('status', ['pending', 'queued'])
            ->whereNotNull('provider_attempted_at')
            ->where(function ($query): void {
                $query->whereNull('provider_attempt_lease_expires_at')
                    ->orWhere('provider_attempt_lease_expires_at', '<=', now());
            });

        if ($this->provider_attempt_token === null) {
            $query->whereNull('provider_attempt_token');
        } else {
            $query->where('provider_attempt_token', $this->provider_attempt_token);
        }

        $updated = $query->update([
            'status' => 'in_doubt',
            'error_code' => 'IN_DOUBT',
            'error_message' => 'Provider attempt lease expired without a confirmed outcome.',
            'provider_attempt_token' => null,
            'provider_attempt_lease_expires_at' => null,
            'provider_retry_not_before' => null,
        ]);

        if ($updated === 1) {
            $this->refresh();
        }

        return $updated === 1;
    }

    /**
     * Fail closed when an active attempt can no longer be reconciled because the
     * expiry probe itself could not be queued. The attempt token makes this a CAS:
     * a winner that completed concurrently is never overwritten.
     */
    public function markUnreconciledProviderAttemptInDoubt(): bool
    {
        $query = self::query()
            ->whereKey($this->getKey())
            ->whereIn('status', ['pending', 'queued'])
            ->whereNotNull('provider_attempted_at');

        if ($this->provider_attempt_token === null) {
            $query->whereNull('provider_attempt_token');
        } else {
            $query->where('provider_attempt_token', $this->provider_attempt_token);
        }

        $updated = $query->update([
            'status' => 'in_doubt',
            'error_code' => 'IN_DOUBT',
            'error_message' => 'Provider attempt could not be scheduled for outcome reconciliation.',
            'provider_attempt_token' => null,
            'provider_attempt_lease_expires_at' => null,
            'provider_retry_not_before' => null,
        ]);

        if ($updated === 1) {
            $this->refresh();
        }

        return $updated === 1;
    }

    public function markSentIfOwned(string $attemptToken, string $providerMessageId): bool
    {
        $updated = self::query()
            ->whereKey($this->getKey())
            ->whereIn('status', ['pending', 'queued'])
            ->where('provider_attempt_token', $attemptToken)
            ->whereNotNull('provider_attempted_at')
            ->update([
                'status' => 'sent',
                'provider_message_id' => $providerMessageId,
                'sent_at' => now(),
                'error_code' => null,
                'error_subcode' => null,
                'provider_error_code' => null,
                'provider_http_status' => null,
                'provider_error_type' => null,
                'provider_error_trace_id' => null,
                'error_message' => null,
                'provider_attempt_token' => null,
                'provider_attempt_lease_expires_at' => null,
                'provider_retry_not_before' => null,
            ]);

        if ($updated === 1) {
            $this->refresh();
        }

        return $updated === 1;
    }

    public function markFailedFromProviderIfOwned(string $attemptToken, MetaApiException $exception): bool
    {
        $updated = self::query()
            ->whereKey($this->getKey())
            ->whereIn('status', ['pending', 'queued'])
            ->where('provider_attempt_token', $attemptToken)
            ->whereNotNull('provider_attempted_at')
            ->update(array_merge($this->providerErrorAttributes($exception), [
                'status' => 'failed',
                'error_code' => $exception->getCode() !== 0 ? (string) $exception->getCode() : 'META_REJECTED',
                'failed_at' => now(),
                'provider_attempt_token' => null,
                'provider_attempt_lease_expires_at' => null,
                'provider_retry_not_before' => null,
            ]));

        if ($updated === 1) {
            $this->refresh();
        }

        return $updated === 1;
    }

    public function markFailedIfOwned(string $attemptToken, string $errorCode, string $errorMessage): bool
    {
        $updated = self::query()
            ->whereKey($this->getKey())
            ->whereIn('status', ['pending', 'queued'])
            ->where('provider_attempt_token', $attemptToken)
            ->whereNotNull('provider_attempted_at')
            ->update([
                'status' => 'failed',
                'error_code' => $errorCode,
                'error_message' => MetaApiException::sanitizeMessage($errorMessage),
                'failed_at' => now(),
                'provider_attempt_token' => null,
                'provider_attempt_lease_expires_at' => null,
                'provider_retry_not_before' => null,
            ]);

        if ($updated === 1) {
            $this->refresh();
        }

        return $updated === 1;
    }

    public function markInDoubtFromProviderIfOwned(string $attemptToken, MetaApiException $exception): bool
    {
        $updated = self::query()
            ->whereKey($this->getKey())
            ->whereIn('status', ['pending', 'queued'])
            ->where('provider_attempt_token', $attemptToken)
            ->whereNotNull('provider_attempted_at')
            ->update(array_merge($this->providerErrorAttributes($exception), [
                'status' => 'in_doubt',
                'error_code' => 'IN_DOUBT',
                'provider_attempt_token' => null,
                'provider_attempt_lease_expires_at' => null,
                'provider_retry_not_before' => null,
            ]));

        if ($updated === 1) {
            $this->refresh();
        }

        return $updated === 1;
    }

    public function releaseProviderAttemptForRetry(
        string $attemptToken,
        DateTimeInterface $retryNotBefore,
        ?MetaApiException $exception = null,
    ): bool {
        $providerError = $exception === null ? [
            'error_code' => null,
            'error_subcode' => null,
            'provider_error_code' => null,
            'provider_http_status' => null,
            'provider_error_type' => null,
            'provider_error_trace_id' => null,
            'error_message' => null,
        ] : array_merge($this->providerErrorAttributes($exception), [
            'error_code' => $exception->getCode() !== 0 ? (string) $exception->getCode() : null,
        ]);

        $updated = self::query()
            ->whereKey($this->getKey())
            ->whereIn('status', ['pending', 'queued'])
            ->where('provider_attempt_token', $attemptToken)
            ->whereNotNull('provider_attempted_at')
            ->update(array_merge($providerError, [
                'status' => 'pending',
                'provider_attempted_at' => null,
                'provider_attempt_token' => null,
                'provider_attempt_lease_expires_at' => null,
                'provider_retry_not_before' => $retryNotBefore,
            ]));

        if ($updated === 1) {
            $this->refresh();
        }

        return $updated === 1;
    }

    /**
     * Ambiguous send: the provider POST may or may not have reached Meta. Do NOT
     * blindly re-send. The row carries no wamid (response was lost) and is resolved
     * later by a webhook status echoing the opaque key, or by reconciliation.
     */
    public function markInDoubt(string $errorMessage): void
    {
        $this->update([
            'status' => 'in_doubt',
            'error_code' => 'IN_DOUBT',
            'error_message' => MetaApiException::sanitizeMessage($errorMessage),
            'provider_attempt_token' => null,
            'provider_attempt_lease_expires_at' => null,
            'provider_retry_not_before' => null,
        ]);
    }

    public function markInDoubtFromProvider(MetaApiException $exception): void
    {
        $this->update([
            'status' => 'in_doubt',
            'error_code' => 'IN_DOUBT',
            'error_subcode' => $exception->errorSubcode !== null ? (string) $exception->errorSubcode : null,
            'provider_error_code' => $exception->getCode() !== 0 ? (string) $exception->getCode() : null,
            'provider_http_status' => $exception->httpStatus,
            'provider_error_type' => $exception->errorType,
            'provider_error_trace_id' => $exception->fbtraceId,
            'error_message' => $exception->sanitizedMessage,
            'provider_attempt_token' => null,
            'provider_attempt_lease_expires_at' => null,
            'provider_retry_not_before' => null,
        ]);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function providerErrorAttributes(MetaApiException $exception): array
    {
        return [
            'error_subcode' => $exception->errorSubcode !== null ? (string) $exception->errorSubcode : null,
            'provider_error_code' => $exception->getCode() !== 0 ? (string) $exception->getCode() : null,
            'provider_http_status' => $exception->httpStatus,
            'provider_error_type' => $exception->errorType,
            'provider_error_trace_id' => $exception->fbtraceId,
            'error_message' => $exception->sanitizedMessage,
        ];
    }
}
