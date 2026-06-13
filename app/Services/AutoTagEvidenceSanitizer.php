<?php

namespace App\Services;

/**
 * Sanitizes AI-generated evidence strings before storing them on the taggables pivot.
 *
 * Rules (D-07):
 * - Redact CPF-like patterns.
 * - Redact phone/document-like digit runs (7+ consecutive digits with optional separators).
 * - Redact email addresses.
 * - Squish whitespace.
 * - Cap output at 180 characters.
 * - Return empty string when evidence becomes meaningless after sanitization.
 */
final class AutoTagEvidenceSanitizer
{
    public function sanitize(string $evidence): string
    {
        // Redact CPF patterns: 000.000.000-00 or 00000000000
        $evidence = (string) preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', '[cpf]', $evidence);

        // Redact email addresses
        $evidence = (string) preg_replace('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', '[email]', $evidence);

        // Redact phone/document-like digit runs (7+ digits, possibly with separators)
        $evidence = (string) preg_replace('/\+?\d[\d\s().\-]{6,}\d/', '[numero]', $evidence);

        // Squish whitespace
        $evidence = (string) str($evidence)->squish();

        // Cap to 180 chars
        $evidence = mb_substr($evidence, 0, 180);

        // Return empty string when only placeholders/whitespace remain
        $stripped = trim((string) preg_replace('/\[(cpf|email|numero)\]/', '', $evidence));
        if ($stripped === '') {
            return '';
        }

        return $evidence;
    }
}
