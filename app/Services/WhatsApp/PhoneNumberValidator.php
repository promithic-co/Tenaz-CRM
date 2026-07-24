<?php

namespace App\Services\WhatsApp;

/**
 * Lightweight E.164 phone number normalizer focused on Brazilian numbers.
 *
 * The bulk-send pipeline must reject malformed numbers BEFORE hitting the Meta Cloud API,
 * otherwise every invalid lookup costs reputation points (Meta error 131026 / 131027) and
 * burns the instance's quality tier.
 *
 * Why no libphonenumber: per project conventions (CLAUDE.md), dependencies require approval.
 * This validator covers the actual production traffic (Brazil consigned-credit leads) plus a
 * relaxed E.164 fallback for the long-tail of valid foreign numbers.
 */
class PhoneNumberValidator
{
    /** Country code dial prefix for Brazil. */
    private const BR_DIAL_PREFIX = '55';

    /**
     * Normalize a phone number to E.164 (digits only, no '+'). Returns null when the
     * input cannot be confidently mapped to a valid mobile/landline number.
     */
    public static function normalize(?string $raw, string $defaultRegion = 'BR'): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        // Strip a leading "00" international prefix (some legacy CSV imports use it).
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return match (strtoupper($defaultRegion)) {
            'BR' => self::normalizeBrazil($digits),
            default => self::normalizeGenericE164($digits),
        };
    }

    /**
     * Every digit form the same subscriber may have been stored under.
     *
     * Brazilian mobiles gained a mandatory 9th digit in 2012 and the sources feeding this
     * system disagree about it: provider echoes, CSV imports and inbound webhooks each pick
     * a side. A lookup that matches one form silently misses rows written in the other, so
     * any query that resolves a person by phone must span the whole set.
     *
     * Always includes the raw digit form, even when {@see normalize()} rejects it — a
     * 9-less mobile is exactly the case this exists to reconcile.
     *
     * @return list<string> deduplicated; empty when the input carries no digits
     */
    public static function variants(?string $raw): array
    {
        $digits = preg_replace('/\D/', '', (string) $raw) ?? '';

        if ($digits === '') {
            return [];
        }

        $forms = [$digits];

        $normalized = self::normalize($raw);
        if ($normalized !== null) {
            $forms[] = $normalized;
        }

        $siblings = [];
        foreach ($forms as $form) {
            $sibling = self::ninthDigitSibling($form);

            if ($sibling !== null) {
                $siblings[] = $sibling;
            }
        }

        return array_values(array_unique([...$forms, ...$siblings]));
    }

    /**
     * The same Brazilian mobile written with the opposite 9th-digit convention, or null
     * when the number is not a BR mobile in either form (landlines never carry the 9).
     */
    private static function ninthDigitSibling(string $digits): ?string
    {
        if (! str_starts_with($digits, self::BR_DIAL_PREFIX)) {
            return null;
        }

        $local = substr($digits, 2);
        $ddd = substr($local, 0, 2);
        $subscriber = substr($local, 2);

        if ((int) $ddd < 11 || (int) $ddd > 99) {
            return null;
        }

        // An 8-digit subscriber opening with 6-9 is a mobile that lost its 9th digit.
        if (strlen($subscriber) === 8 && in_array($subscriber[0], ['6', '7', '8', '9'], true)) {
            return self::BR_DIAL_PREFIX.$ddd.'9'.$subscriber;
        }

        // A 9-digit subscriber opening with 9 is the modern mobile form.
        if (strlen($subscriber) === 9 && $subscriber[0] === '9') {
            return self::BR_DIAL_PREFIX.$ddd.substr($subscriber, 1);
        }

        return null;
    }

    /**
     * Brazilian rules:
     *   - Mobile: 11 local digits = DDD(2) + 9 + 8-digit subscriber, total 13 with +55
     *   - Landline: 10 local digits = DDD(2) + 8-digit subscriber, total 12 with +55
     *   - DDD must be 11-99 (skipping the unassigned 20, 23, 25, 26, 29, 30, 36, 39, 40, 50, 52, 56, 57, 58, 59, 60, 70, 72, 76, 78, 80, 90 — but Meta will accept those and just fail; we don't validate that deeply here).
     */
    private static function normalizeBrazil(string $digits): ?string
    {
        // Strip leading 55 country code if present so the remainder is the local number.
        if (str_starts_with($digits, self::BR_DIAL_PREFIX) && in_array(strlen($digits), [12, 13], true)) {
            $local = substr($digits, 2);
        } else {
            $local = $digits;
        }

        $length = strlen($local);

        if (! in_array($length, [10, 11], true)) {
            return null;
        }

        $ddd = (int) substr($local, 0, 2);
        if ($ddd < 11 || $ddd > 99) {
            return null;
        }

        // Mobile MUST start with 9 after the DDD (post-2012 mandate).
        if ($length === 11 && $local[2] !== '9') {
            return null;
        }

        // Landline first digit after DDD must be 2-5 (avoids accidentally treating an
        // 11-digit mobile missing the 9 as a valid landline).
        if ($length === 10 && ! in_array($local[2], ['2', '3', '4', '5'], true)) {
            return null;
        }

        return self::BR_DIAL_PREFIX.$local;
    }

    /**
     * Generic E.164 validation for non-BR regions: 8-15 digits, first digit non-zero.
     */
    private static function normalizeGenericE164(string $digits): ?string
    {
        $length = strlen($digits);
        if ($length < 8 || $length > 15) {
            return null;
        }

        if ($digits[0] === '0') {
            return null;
        }

        return $digits;
    }
}
