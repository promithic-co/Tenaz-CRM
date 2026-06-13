<?php

namespace App\Ai\Exceptions;

use RuntimeException;

/**
 * Thrown when the total tool call ceiling is exceeded during a single prompt cycle.
 * AgentService catches this specifically and returns a safe escalation message.
 */
class ToolCallCeilingExceededException extends RuntimeException
{
    public function __construct(int $totalSteps, int $maxSteps)
    {
        parent::__construct(
            "Tool call ceiling exceeded: {$totalSteps} calls (max: {$maxSteps})"
        );
    }
}
