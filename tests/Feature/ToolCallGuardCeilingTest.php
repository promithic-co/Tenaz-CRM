<?php

use App\Ai\Exceptions\ToolCallCeilingExceededException;
use App\Ai\Middleware\ToolCallGuardMiddleware;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Prompts\AgentPrompt;

/** Build an AgentPrompt with a non-BaseCustomerServiceAgent so event recording is skipped. */
function fakeAgentPrompt(): AgentPrompt
{
    $prompt = (new ReflectionClass(AgentPrompt::class))->newInstanceWithoutConstructor();

    $agent = new ReflectionProperty(AgentPrompt::class, 'agent');
    $agent->setValue($prompt, Mockery::mock(Agent::class));

    return $prompt;
}

/** Build a response stub whose then() runs the guard callback synchronously. */
function fakeToolResponse(int $calls): object
{
    $response = new class
    {
        /** @var array<int, object> */
        public array $toolCalls = [];

        public function then(callable $callback): mixed
        {
            $callback($this);

            return $this;
        }
    };

    $response->toolCalls = array_map(
        fn (int $i): object => (object) ['name' => 'consultar_credito_inss', 'arguments' => ['cpf' => "0000000000{$i}"]],
        range(1, $calls),
    );

    return $response;
}

test('per-tool ceiling throws once a single tool is called more than three times', function () {
    $middleware = app(ToolCallGuardMiddleware::class);
    $response = fakeToolResponse(4);

    expect(fn () => $middleware->handle(fakeAgentPrompt(), fn () => $response))
        ->toThrow(ToolCallCeilingExceededException::class);
});

test('three calls of the same tool stay under the per-tool ceiling', function () {
    $middleware = app(ToolCallGuardMiddleware::class);
    $response = fakeToolResponse(3);

    $middleware->handle(fakeAgentPrompt(), fn () => $response);

    expect(true)->toBeTrue(); // no exception thrown
});
