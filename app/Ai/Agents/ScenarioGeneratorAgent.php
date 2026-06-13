<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * Test director that drafts a per-cycle attack persona/scenario directive.
 * Promoted from the anonymous Agent subclass formerly inlined in
 * PlaygroundController::generateScenario so it can be targeted by Ai::fakeAgent.
 */
class ScenarioGeneratorAgent implements Agent
{
    use Promptable;

    public function __construct(
        private readonly string $providerName,
        private readonly string $modelName,
    ) {}

    public function provider(): string
    {
        return $this->providerName;
    }

    public function model(): ?string
    {
        return $this->modelName;
    }

    public function instructions(): string
    {
        return config('playground_prompts.scenario_generator_instructions');
    }
}
