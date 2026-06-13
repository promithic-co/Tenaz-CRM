<?php

namespace Laravel\Ai\Gateway;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use ReflectionClass;

/**
 * Extended TextGenerationOptions that checks for method-based overrides
 * before falling back to PHP attributes.
 *
 * This enables runtime configuration of temperature, maxTokens, and maxSteps
 * via agent methods that read from AppSetting (user-scoped).
 */
class TextGenerationOptions
{
    public function __construct(
        public readonly ?int $maxSteps = null,
        public readonly ?int $maxTokens = null,
        public readonly ?float $temperature = null,
    ) {}

    public static function forAgent(Agent $agent): self
    {
        $reflection = new ReflectionClass($agent);

        return new self(
            maxSteps: self::resolveValue($agent, $reflection, 'maxSteps', MaxSteps::class),
            maxTokens: self::resolveValue($agent, $reflection, 'maxTokens', MaxTokens::class),
            temperature: self::resolveValue($agent, $reflection, 'temperature', Temperature::class),
        );
    }

    /**
     * Resolve a value by checking for a method first, then falling back to the PHP attribute.
     */
    private static function resolveValue(Agent $agent, ReflectionClass $reflection, string $method, string $attributeClass): int|float|null
    {
        if (method_exists($agent, $method)) {
            $value = $agent->{$method}();
            if ($value !== null) {
                return $value;
            }
        }

        $attrs = $reflection->getAttributes($attributeClass);

        return ! empty($attrs) ? $attrs[0]->newInstance()->value : null;
    }
}
