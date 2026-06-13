<?php

namespace App\Actions;

use App\Ai\Agents\CredFlowAgent;
use App\Models\Lead;
use App\Services\ConversationTimelineService;
use Throwable;

class RunPlaygroundChatAction
{
    public function __construct(private readonly ConversationTimelineService $timeline) {}

    /**
     * Run one playground chat turn for a sandbox lead and assemble the debug
     * payload (tokens, duration, steps, tool calls, model). Continues the
     * existing conversation or starts a new one (persisting the conversation_id).
     * On failure returns the 500 error envelope with the error message.
     *
     * @return array{status: int, payload: array<string, mixed>}
     */
    public function execute(Lead $lead, string $message, ?string $modelOverride): array
    {
        $startMs = microtime(true);
        $toolCalls = [];

        try {
            $agent = new CredFlowAgent($lead, $lead->sandbox_system_prompt ?: null);

            if ($modelOverride) {
                $provider = str_contains($modelOverride, '/') ? 'openrouter' : 'openai';
                $agent->withModelOverride($provider, $modelOverride);
            }

            if ($lead->conversation_id) {
                $response = $agent
                    ->continue($lead->conversation_id, as: $lead)
                    ->prompt($message);
            } else {
                $response = $agent->forUser($lead)->prompt($message);
                $lead->update(['conversation_id' => $response->conversationId]);
            }

            $durationMs = (int) round((microtime(true) - $startMs) * 1000);
            $text = (string) $response;

            $tokensIn = $response->usage?->promptTokens ?? null;
            $tokensOut = $response->usage?->completionTokens ?? null;
            $steps = $response->steps ?? null;

            if (! empty($response->steps)) {
                foreach ($response->steps as $step) {
                    foreach ($step->toolCalls ?? [] as $tc) {
                        $toolCalls[] = [
                            'name' => $tc->name ?? $tc->function?->name ?? '?',
                            'input' => $tc->arguments ?? $tc->function?->arguments ?? null,
                            'output' => null,
                        ];
                    }
                    foreach ($step->toolResults ?? [] as $i => $tr) {
                        if (isset($toolCalls[$i])) {
                            $toolCalls[$i]['output'] = $tr->content ?? $tr;
                        }
                    }
                }
            }

            return [
                'status' => 200,
                'payload' => [
                    'reply' => $text,
                    'messages' => $this->timeline->legacyMessages($lead),
                    'debug' => [
                        'tokens_in' => $tokensIn,
                        'tokens_out' => $tokensOut,
                        'duration' => $durationMs,
                        'steps' => $steps,
                        'tool_calls' => $toolCalls,
                        'model' => $agent->model(),
                    ],
                ],
            ];
        } catch (Throwable $e) {
            return [
                'status' => 500,
                'payload' => [
                    'reply' => 'Erro no agente: '.$e->getMessage(),
                    'messages' => $this->timeline->legacyMessages($lead),
                    'debug' => ['error' => $e->getMessage()],
                ],
            ];
        }
    }
}
