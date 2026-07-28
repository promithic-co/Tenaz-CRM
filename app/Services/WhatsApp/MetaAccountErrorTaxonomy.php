<?php

namespace App\Services\WhatsApp;

/**
 * Account-level Meta Cloud failures: errors owned by the WhatsApp Business Account
 * itself rather than by one recipient. Every subsequent send on the same account
 * fails identically, so they must pause the campaign on first sight instead of
 * burning the whole fan-out until the percentage failure-rate auto-pause trips.
 *
 * Deliberately shared by both detection paths — the provider's POST response and
 * the delivery-status webhook (which carries no HTTP status to classify) — so the
 * two can never drift on which codes are fatal.
 */
class MetaAccountErrorTaxonomy
{
    public const PAUSE_REASON_ACCOUNT_BLOCKED = 'meta_account_blocked';

    public const PAUSE_REASON_ACCOUNT_PAYMENT = 'meta_account_payment_issue';

    public const FAILURE_REASON_ACCOUNT_BLOCKED = 'Conta Meta bloqueada ou restrita: campanha pausada ate a conta ser regularizada na Meta.';

    public const FAILURE_REASON_ACCOUNT_PAYMENT = 'Pagamento da conta Meta com problema: campanha pausada ate o metodo de pagamento ser regularizado.';

    /**
     * 131031: Business Account locked/restricted (e.g. unverified business, policy action).
     *
     * @var list<int>
     */
    private const BLOCKED_CODES = [131031];

    /**
     * 131042: business eligibility / payment method problem on the WABA.
     *
     * @var list<int>
     */
    private const PAYMENT_CODES = [131042];

    /**
     * @return list<int>
     */
    public static function codes(): array
    {
        return array_merge(self::BLOCKED_CODES, self::PAYMENT_CODES);
    }

    public static function isAccountLevel(int|string|null $code): bool
    {
        return self::pauseReasonCode($code) !== null;
    }

    public static function pauseReasonCode(int|string|null $code): ?string
    {
        $normalized = self::normalizeCode($code);

        if ($normalized === null) {
            return null;
        }

        return match (true) {
            in_array($normalized, self::BLOCKED_CODES, true) => self::PAUSE_REASON_ACCOUNT_BLOCKED,
            in_array($normalized, self::PAYMENT_CODES, true) => self::PAUSE_REASON_ACCOUNT_PAYMENT,
            default => null,
        };
    }

    public static function failureReason(int|string|null $code): ?string
    {
        return match (self::pauseReasonCode($code)) {
            self::PAUSE_REASON_ACCOUNT_BLOCKED => self::FAILURE_REASON_ACCOUNT_BLOCKED,
            self::PAUSE_REASON_ACCOUNT_PAYMENT => self::FAILURE_REASON_ACCOUNT_PAYMENT,
            default => null,
        };
    }

    private static function normalizeCode(int|string|null $code): ?int
    {
        if (is_int($code)) {
            return $code;
        }

        return is_string($code) && ctype_digit($code) ? (int) $code : null;
    }
}
