<?php

namespace App\Exceptions\StatusMachine;

use RuntimeException;

/**
 * Thrown when an attempt is made to delete a status that still has leads assigned to it.
 *
 * The caller must first reassign or move all leads away from this status
 * before deletion is allowed.
 */
class StatusInUseException extends RuntimeException
{
    public function __construct(string $slug, int $leadCount, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            "O status '{$slug}' não pode ser removido pois possui {$leadCount} lead(s) atribuído(s). Mova os leads antes de deletar.",
            $code,
            $previous,
        );
    }
}
