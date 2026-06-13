<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * Red-team scanner that produces a JSON attack plan for the configured agent.
 * Promoted from the anonymous Agent subclass formerly inlined in
 * PlaygroundController::scanBlindspots so it can be targeted by Ai::fakeAgent.
 */
class BlindspotScannerAgent implements Agent
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
        return config('playground_prompts.blindspot_scanner_instructions');
    }
}
