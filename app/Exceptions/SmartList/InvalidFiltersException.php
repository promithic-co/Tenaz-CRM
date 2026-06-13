<?php

namespace App\Exceptions\SmartList;

use RuntimeException;

/**
 * Thrown when a filters_json payload fails FilterSchema validation.
 *
 * Caught by:
 * - `ValidFilterJson` rule (mapped to 422 via ValidationException).
 * - `SmartListResolverService` (propagated to job logs + aborts materialization).
 */
class InvalidFiltersException extends RuntimeException
{
    public function __construct(string $message = 'Invalid smart list filters.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
