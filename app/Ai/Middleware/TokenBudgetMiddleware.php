<?php

namespace App\Ai\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * Monitors token usage per agent call and logs warnings when thresholds are exceeded.
 */
class TokenBudgetMiddleware
{
    public function handle(AgentPrompt $prompt, Closure $next)
    {
        return $next($prompt)->then(function ($response) use ($prompt) {
            $promptTokens = $response->usage?->promptTokens ?? 0;
            $completionTokens = $response->usage?->completionTokens ?? 0;
            $totalTokens = $promptTokens + $completionTokens;

            $warningPrompt = config('credflow.agent.token_warning_prompt', 3000);
            $warningTotal = config('credflow.agent.token_warning_total', 4000);

            if ($promptTokens > $warningPrompt) {
                Log::warning('aria.token_budget.high_prompt_tokens', [
                    'agent' => class_basename($prompt->agent),
                    'prompt_tokens' => $promptTokens,
                    'threshold' => $warningPrompt,
                ]);
            }

            if ($totalTokens > $warningTotal) {
                Log::warning('aria.token_budget.high_total_tokens', [
                    'agent' => class_basename($prompt->agent),
                    'total_tokens' => $totalTokens,
                    'threshold' => $warningTotal,
                ]);
            }
        });
    }
}
