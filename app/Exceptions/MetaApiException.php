<?php

namespace App\Exceptions;

use RuntimeException;

class MetaApiException extends RuntimeException
{
    public readonly string $sanitizedMessage;

    public readonly ?int $httpStatus;

    public readonly ?int $errorSubcode;

    public readonly ?string $errorType;

    public readonly ?string $fbtraceId;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?int $httpStatus = null,
        ?int $errorSubcode = null,
        ?string $errorType = null,
        ?string $fbtraceId = null,
    ) {
        $this->sanitizedMessage = self::sanitizeMessage($message);
        $this->httpStatus = $httpStatus;
        $this->errorSubcode = $errorSubcode;
        $this->errorType = self::sanitizeMetadata($errorType, 100);
        $this->fbtraceId = self::sanitizeMetadata($fbtraceId, 255);

        parent::__construct($this->sanitizedMessage, $code, $previous);
    }

    public static function sanitizeMessage(string $message): string
    {
        $withoutControlCharacters = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $message) ?? 'Provider error';
        $withoutUrls = preg_replace('~https?://[^\s]+~iu', '[redacted_url]', $withoutControlCharacters) ?? 'Provider error';
        $withoutBearerTokens = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer [redacted]', $withoutUrls) ?? 'Provider error';
        $withoutNamedSecrets = preg_replace(
            '/\b(access[_-]?token|authorization|token)\s*[:=]\s*[^\s,;]+/i',
            '$1=[redacted]',
            $withoutBearerTokens,
        ) ?? 'Provider error';
        $sanitized = preg_replace('/(?<!\d)\+?(?:\d[\s().-]*){10,15}(?!\d)/', '[redacted_phone]', $withoutNamedSecrets) ?? 'Provider error';

        return mb_substr($sanitized, 0, 2000);
    }

    public function isExplicitClientRejection(): bool
    {
        return $this->httpStatus !== null && $this->httpStatus >= 400 && $this->httpStatus < 500;
    }

    public static function sanitizeMetadata(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $redacted = self::sanitizeMessage($value);
        $sanitized = preg_replace('/[^A-Za-z0-9_.:\/-]/', '', $redacted) ?? '';

        return $sanitized === '' ? null : mb_substr($sanitized, 0, $maxLength);
    }
}
