<?php

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Promptable;

/**
 * Plan 0.4 (F9/F14): drift detector for the laravel/ai namespace-hijack override
 * (composer.json exclude-from-classmap + files autoload). The vendor class is excluded
 * from the classmap, so runtime reflection only ever sees our override — a reflection-only
 * test would be a tautology. We therefore parse the vendor SOURCE from disk and assert its
 * shape matches the contract our override depends on. If a future (pinned-but-changed)
 * vendor file diverges, this fails instead of silently shadowing a broken contract.
 */
test('vendor TextGenerationOptions source still matches the contract the override depends on', function () {
    $vendorPath = base_path('vendor/laravel/ai/src/Gateway/TextGenerationOptions.php');

    expect(file_exists($vendorPath))->toBeTrue('vendor TextGenerationOptions.php is missing — override may be dangling');

    $source = file_get_contents($vendorPath);
    $normalized = preg_replace('/\s+/', ' ', $source);

    expect($normalized)
        ->toContain('public readonly ?int $maxSteps')
        ->toContain('public readonly ?int $maxTokens')
        ->toContain('public readonly ?float $temperature')
        ->toContain('public static function forAgent(Agent $agent): self');
});

test('the loaded override exposes the three promoted readonly properties', function () {
    $reflection = new ReflectionClass(TextGenerationOptions::class);
    $params = collect($reflection->getConstructor()->getParameters())
        ->keyBy(fn (ReflectionParameter $p) => $p->getName());

    expect($params->keys()->all())->toEqualCanonicalizing(['maxSteps', 'maxTokens', 'temperature']);

    foreach (['maxSteps' => 'int', 'maxTokens' => 'int', 'temperature' => 'float'] as $name => $type) {
        $param = $params->get($name);
        expect($param->isPromoted())->toBeTrue("{$name} must be a promoted property");
        expect($param->getType()->allowsNull())->toBeTrue("{$name} must be nullable");
        expect($param->getType()->getName())->toBe($type);
    }

    $prop = $reflection->getProperty('temperature');
    expect($prop->isReadOnly())->toBeTrue();
});

test('override forAgent prefers a method value over the PHP attribute', function () {
    $agent = new #[Temperature(0.1)] class implements Agent
    {
        use Promptable;

        public function instructions(): string
        {
            return 'test';
        }

        public function temperature(): ?float
        {
            return 0.7;
        }
    };

    expect(TextGenerationOptions::forAgent($agent)->temperature)->toBe(0.7);
});

test('override forAgent falls back to the PHP attribute when no method exists', function () {
    $agent = new #[MaxSteps(9)] class implements Agent
    {
        use Promptable;

        public function instructions(): string
        {
            return 'test';
        }
    };

    expect(TextGenerationOptions::forAgent($agent)->maxSteps)->toBe(9);
});
