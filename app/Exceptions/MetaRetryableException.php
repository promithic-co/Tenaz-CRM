<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A sanitized transient failure that is safe for the queue worker to report.
 *
 * The original throwable is deliberately not retained because HTTP transport
 * exceptions may embed access tokens, request URLs, or recipient identifiers.
 */
class MetaRetryableException extends RuntimeException
{
    public function __construct(string $message = 'Transient provider failure.')
    {
        parent::__construct(MetaApiException::sanitizeMessage($message));
    }
}
