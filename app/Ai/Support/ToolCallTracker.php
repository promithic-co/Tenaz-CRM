<?php

namespace App\Ai\Support;

/**
 * Request-scoped tracker for tool call invocations.
 * Replaces the static array in ToolCallGuardMiddleware to prevent
 * state leaking between requests in long-running queue workers.
 *
 * Registered as app()->scoped() in AppServiceProvider.
 */
class ToolCallTracker
{
    /** @var array<string, list<string>> Tool name → list of argument hashes */
    private array $toolCalls = [];

    public function record(string $toolName, string $argsHash): void
    {
        $this->toolCalls[$toolName][] = $argsHash;
    }

    public function totalCalls(): int
    {
        return array_sum(array_map('count', $this->toolCalls));
    }

    public function callsForTool(string $toolName): int
    {
        return count($this->toolCalls[$toolName] ?? []);
    }
}
