<?php

namespace App\Enums;

/**
 * Identifies who/what created a tag-to-taggable link in the `taggables` pivot.
 *
 * The source governs permission semantics:
 * - Manual = human action via HTTP (LeadTagController). Ground truth.
 * - Ai = autonomous tagging by the AI SDR. Must not touch Manual pivots.
 * - Import = bulk CSV / external feed.
 * - System = automated server-side rules (e.g., status transitions).
 */
enum TaggableSource: string
{
    case Manual = 'manual';
    case Ai = 'ai';
    case Import = 'import';
    case System = 'system';

    /**
     * Default source used by callers that do not explicitly opt-in
     * (e.g., HasTags::attachTag without a $source argument).
     */
    public static function default(): self
    {
        return self::Manual;
    }
}
