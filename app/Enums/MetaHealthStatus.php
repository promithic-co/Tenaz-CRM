<?php

namespace App\Enums;

/**
 * Meta's messaging health status, as returned by the `health_status.can_send_message`
 * field on the WhatsApp Business Phone Number / WABA / Message Template nodes.
 *
 * `Unknown` is ours, not Meta's: the Graph call can fail (expired token, a
 * coexistence token without access to the WABA node, a timeout) and a failed
 * probe must never be mistaken for a healthy account — nor block a campaign.
 */
enum MetaHealthStatus: string
{
    case Available = 'AVAILABLE';

    case Limited = 'LIMITED';

    case Blocked = 'BLOCKED';

    case Unknown = 'UNKNOWN';

    public static function fromMeta(mixed $value): self
    {
        if (! is_string($value)) {
            return self::Unknown;
        }

        return self::tryFrom(strtoupper(trim($value))) ?? self::Unknown;
    }

    /** Meta confirmed the account cannot send at all. */
    public function isBlocked(): bool
    {
        return $this === self::Blocked;
    }

    /** Meta confirmed sending works but under a restriction. */
    public function isLimited(): bool
    {
        return $this === self::Limited;
    }

    /**
     * True only when Meta positively reported a problem. A failed probe
     * (`Unknown`) is deliberately excluded so it can never gate a send.
     */
    public function isDegraded(): bool
    {
        return $this === self::Blocked || $this === self::Limited;
    }

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Conectado',
            self::Limited => 'Limitado',
            self::Blocked => 'Bloqueado',
            self::Unknown => 'Sem dados',
        };
    }
}
