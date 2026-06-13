<?php

namespace App\Exceptions\StatusMachine;

use RuntimeException;

/**
 * Thrown when an attempt is made to modify the slug of a canonical status.
 *
 * Canonical slugs are hardcoded in AI tools and MUST remain immutable.
 * Only label, color, position, and is_terminal are editable on canonical statuses.
 */
class CanonicalStatusModificationException extends RuntimeException
{
    public function __construct(string $slug, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            "O status canônico '{$slug}' não pode ter seu slug modificado. Apenas label, cor e posição são editáveis.",
            $code,
            $previous,
        );
    }
}
