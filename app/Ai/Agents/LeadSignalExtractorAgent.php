<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Structured-output agent for extracting negotiation-state signals from a lead transcript.
 *
 * Returns a `detected` array of `{slug, confidence, evidence}` objects limited to
 * slugs provided in the instructions. The service layer validates every slug against
 * the tenant tag whitelist before writing anything.
 */
final class LeadSignalExtractorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private readonly string $instructions,
    ) {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    /**
     * Structured output schema: detected array of slug/confidence/evidence objects.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'detected' => $schema->array()->items(
                $schema->object([
                    'slug' => $schema->string()->required(),
                    'confidence' => $schema->number()->required(),
                    'evidence' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }
}
