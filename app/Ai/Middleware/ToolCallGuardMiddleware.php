<?php

namespace App\Ai\Middleware;

use App\Ai\Agents\BaseCustomerServiceAgent;
use App\Ai\Exceptions\ToolCallCeilingExceededException;
use App\Ai\Support\ToolCallTracker;
use App\Services\AgentInteractionContext;
use App\Services\AgentInteractionEventService;
use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * Detects and prevents infinite tool-calling loops within a single prompt cycle.
 * Tracks tool invocations via a request-scoped ToolCallTracker and blocks
 * execution when the ceiling is exceeded.
 */
class ToolCallGuardMiddleware
{
    /** Max times any single tool can be called with different args in one prompt cycle. */
    private const MAX_CALLS_PER_TOOL = 3;

    public function __construct(
        private readonly ToolCallTracker $tracker,
        private readonly AgentInteractionContext $interactionContext,
        private readonly AgentInteractionEventService $interactionEvents,
    ) {}

    public function handle(AgentPrompt $prompt, Closure $next)
    {
        return $next($prompt)->then(function ($response) use ($prompt) {
            $interactionId = $this->interactionContext->interactionId();

            if (empty($response->toolCalls)) {
                return;
            }

            foreach ($response->toolCalls as $call) {
                $toolName = $call->name ?? 'unknown';
                $argsHash = md5(json_encode($call->arguments ?? []));

                $this->tracker->record($toolName, $argsHash);

                if ($interactionId && $prompt->agent instanceof BaseCustomerServiceAgent) {
                    $this->interactionEvents->recordForLead(
                        interactionId: $interactionId,
                        lead: $prompt->agent->lead,
                        eventType: 'tool_called',
                        eventSource: 'tool_call_guard_middleware',
                        payload: [
                            'tool' => $toolName,
                            'args_hash' => $argsHash,
                        ],
                    );
                }

                $callCount = $this->tracker->callsForTool($toolName);
                if ($callCount > self::MAX_CALLS_PER_TOOL) {
                    Log::warning('aria.tool_guard.excessive_calls', [
                        'interaction_id' => $interactionId,
                        'agent' => class_basename($prompt->agent),
                        'tool' => $toolName,
                        'call_count' => $callCount,
                    ]);
                }
            }

            $totalSteps = $this->tracker->totalCalls();
            $maxTotalSteps = config('credflow.agent.max_total_steps', 12);

            if ($totalSteps > $maxTotalSteps) {
                Log::critical('aria.tool_guard.step_ceiling_exceeded', [
                    'interaction_id' => $interactionId,
                    'agent' => class_basename($prompt->agent),
                    'total_steps' => $totalSteps,
                    'max' => $maxTotalSteps,
                ]);

                throw new ToolCallCeilingExceededException($totalSteps, $maxTotalSteps);
            }
        });
    }
}
