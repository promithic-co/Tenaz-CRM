<?php

use App\Http\Requests\StoreAgentTemplateConfigRequest;
use Illuminate\Support\Facades\Validator;

// Valid base payload used across multiple tests
$validPayload = [
    'template_slug' => 'aria-bulk',
    'agent_provider' => 'openrouter',
    'agent_model' => 'anthropic/claude-haiku-4-5',
    'transcription_provider' => 'openai',
    'transcription_model' => 'whisper-1',
    'vision_provider' => 'openai',
    'vision_model' => 'gpt-4o',
    'temperature' => 0.4,
    'max_tokens' => 1024,
    'max_conversation_messages' => 24,
];

it('rejects an invalid agent_provider not in whitelist', function () use ($validPayload) {
    $payload = array_merge($validPayload, ['agent_provider' => 'made-up']);

    $validator = Validator::make($payload, (new StoreAgentTemplateConfigRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('agent_provider'))->toBeTrue();
});

it('rejects agent_model that is whitespace only', function () use ($validPayload) {
    $payload = array_merge($validPayload, ['agent_model' => '   ']);

    $validator = Validator::make($payload, (new StoreAgentTemplateConfigRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('agent_model'))->toBeTrue();
});

it('rejects empty agent_model string', function () use ($validPayload) {
    $payload = array_merge($validPayload, ['agent_model' => '']);

    $validator = Validator::make($payload, (new StoreAgentTemplateConfigRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('agent_model'))->toBeTrue();
});

it('accepts a fully valid payload with whitelisted provider and non-empty model', function () use ($validPayload) {
    $validator = Validator::make($validPayload, (new StoreAgentTemplateConfigRequest)->rules());

    expect($validator->passes())->toBeTrue();
});
