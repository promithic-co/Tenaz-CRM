<?php

namespace App\Exceptions\StatusMachine;

use RuntimeException;

/**
 * Thrown when an attempt is made to add a status with a slug that already exists
 * in the status machine (canonical or custom).
 */
class DuplicateSlugException extends RuntimeException
{
    public function __construct(string $slug, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            "Já existe um status com o slug '{$slug}' neste pipeline.",
            $code,
            $previous,
        );
    }
}
