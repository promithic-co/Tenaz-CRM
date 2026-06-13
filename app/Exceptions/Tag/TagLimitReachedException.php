<?php

namespace App\Exceptions\Tag;

use RuntimeException;

/**
 * Thrown when a tenant has hit `Tag::MAX_PER_TENANT` and a new tag is requested.
 *
 * Caught by:
 * - `TagController::store` (mapped to 422 via ValidationException).
 * - Phase 50 auto-tag jobs (logged + tag creation skipped, lead remains untagged
 *   by AI for the run; manual tags unaffected).
 */
class TagLimitReachedException extends RuntimeException
{
    public function __construct(string $message = 'Tenant has reached the tag limit.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
