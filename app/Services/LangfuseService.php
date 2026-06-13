<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Langfuse LLM observability integration.
 * Implemented as a lightweight HTTP wrapper against the Langfuse REST API
 * since no official PHP SDK exists.
 *
 * Traces use the app tenant id as Langfuse `userId` so multiple SaaS tenants can filter in the UI.
 * `sessionId` groups turns in the same conversation (lead thread).
 *
 * @see https://api.reference.langfuse.com
 */
class LangfuseService
{
    private const MAX_BODY_CHARS = 120_000;

    private bool $enabled;

    private string $host;

    private string $basicAuth;

    public function __construct()
    {
        $this->enabled = (bool) config('laboratory.langfuse.enabled', false);
        $this->host = rtrim(config('laboratory.langfuse.host', 'https://cloud.langfuse.com'), '/');
        $public = (string) config('laboratory.langfuse.public_key', '');
        $secret = (string) config('laboratory.langfuse.secret_key', '');
        $this->basicAuth = base64_encode($public.':'.$secret);
        if ($this->enabled && ($public === '' || $secret === '')) {
            $this->enabled = false;
            Log::warning('langfuse.disabled_missing_keys');
        }
    }

    /**
     * Record one LLM turn: trace + generation (multi-tenant safe).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function logAgentLlmTurn(
        string $traceId,
        string $tenantId,
        string $sessionId,
        int $leadId,
        ?int $agentId,
        string $model,
        string $input,
        string $output,
        int $promptTokens,
        int $completionTokens,
        ?float $durationMs,
        array $metadata = [],
    ): void {
        if (! $this->enabled) {
            return;
        }

        $input = $this->clip($input);
        $output = $this->clip($output);

        $traceMetadata = array_filter(
            array_merge($metadata, [
                'lead_id' => $leadId,
                'agent_id' => $agentId,
            ]),
            fn ($v) => $v !== null,
        );

        $this->post('/api/public/traces', [
            'id' => $traceId,
            'name' => 'agent-llm-turn',
            'userId' => $tenantId,
            'sessionId' => $sessionId,
            'metadata' => $traceMetadata,
            'input' => $input,
            'output' => $output,
        ], 'langfuse.trace_error');

        $usage = [
            'promptTokens' => $promptTokens,
            'completionTokens' => $completionTokens,
            'totalTokens' => $promptTokens + $completionTokens,
        ];

        $this->post('/api/public/generations', [
            'id' => (string) Str::uuid(),
            'traceId' => $traceId,
            'name' => 'llm-call',
            'model' => $model,
            'input' => $input,
            'output' => $output,
            'usage' => $usage,
            'metadata' => array_filter([
                'duration_ms' => $durationMs,
            ], fn ($v) => $v !== null),
        ], 'langfuse.generation_error');
    }

    /**
     * Create a trace for a lead conversation interaction.
     *
     * @deprecated Prefer {@see logAgentLlmTurn} for correct tenant/session semantics.
     */
    public function traceInteraction(
        string $traceId,
        int $leadId,
        int $agentId,
        string $input,
        string $output,
        array $metadata = [],
    ): void {
        if (! $this->enabled) {
            return;
        }

        $this->post('/api/public/traces', [
            'id' => $traceId,
            'name' => 'agent-interaction',
            'userId' => (string) $leadId,
            'metadata' => array_merge($metadata, [
                'agent_id' => $agentId,
                'lead_id' => $leadId,
            ]),
            'input' => $this->clip($input),
            'output' => $this->clip($output),
        ], 'langfuse.trace_error');
    }

    private function clip(string $text): string
    {
        if (strlen($text) <= self::MAX_BODY_CHARS) {
            return $text;
        }

        return mb_substr($text, 0, self::MAX_BODY_CHARS).'…[truncated]';
    }

    /**
     * Record an LLM generation (token usage, model, cost).
     */
    public function recordGeneration(
        string $traceId,
        string $model,
        string $input,
        string $output,
        ?array $usage = null,
        ?float $durationMs = null,
    ): void {
        if (! $this->enabled) {
            return;
        }

        $this->post('/api/public/generations', [
            'traceId' => $traceId,
            'name' => 'llm-call',
            'model' => $model,
            'input' => $this->clip($input),
            'output' => $this->clip($output),
            'usage' => $usage,
            'metadata' => ['duration_ms' => $durationMs],
        ], 'langfuse.generation_error');
    }

    /**
     * Score a conversation (for quality tracking).
     * Call from Playground evaluations or operator feedback.
     */
    public function scoreTrace(string $traceId, string $name, float $value, ?string $comment = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->post('/api/public/scores', [
            'traceId' => $traceId,
            'name' => $name,
            'value' => $value,
            'comment' => $comment,
        ], 'langfuse.score_error');
    }

    private function post(string $path, array $payload, string $logKey): void
    {
        try {
            Http::withHeader('Authorization', 'Basic '.$this->basicAuth)
                ->timeout(3)
                ->post($this->host.$path, $payload);
        } catch (\Throwable $e) {
            Log::warning($logKey, ['error' => $e->getMessage()]);
        }
    }
}
