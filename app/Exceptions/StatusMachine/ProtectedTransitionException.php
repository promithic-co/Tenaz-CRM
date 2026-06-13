<?php

namespace App\Exceptions\StatusMachine;

use RuntimeException;

/**
 * Thrown when an attempt is made to remove a canonical AI-dependent transition.
 *
 * Certain transitions between canonical statuses are used by AI tools and must
 * remain intact. Only non-canonical (custom) transitions may be removed.
 */
class ProtectedTransitionException extends RuntimeException
{
    public function __construct(string $from, string $to, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            "A transição '{$from}' → '{$to}' é canônica e não pode ser removida. A IA depende desta transição.",
            $code,
            $previous,
        );
    }
}
