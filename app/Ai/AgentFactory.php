<?php

namespace App\Ai;

use App\Ai\Agents\BaseCustomerServiceAgent;
use App\Models\Lead;
use App\Services\AgentConfigResolver;
use InvalidArgumentException;

/**
 * Factory for creating agent instances based on lead niche + mode.
 * Resolves niche from the lead's AgentConfig, then maps niche.modo → Agent FQCN.
 */
class AgentFactory
{
    public function __construct(
        private readonly AgentConfigResolver $configResolver,
    ) {}

    /**
     * @throws InvalidArgumentException If the agent class doesn't exist or is invalid
     */
    public function make(Lead $lead): BaseCustomerServiceAgent
    {
        $niche = $this->resolveNiche($lead);
        $agents = config('credflow.agents', []);

        // Lookup: niche.modo → niche.receptivo → inss.modo → inss.receptivo
        $class = $agents[$niche][$lead->modo]
            ?? $agents[$niche]['receptivo']
            ?? $agents['inss'][$lead->modo]
            ?? $agents['inss']['receptivo']
            ?? null;

        if (! $class || ! class_exists($class)) {
            throw new InvalidArgumentException(
                "Agent class not found for niche '{$niche}', modo '{$lead->modo}': ".($class ?? 'null')
            );
        }

        if (! is_subclass_of($class, BaseCustomerServiceAgent::class)) {
            throw new InvalidArgumentException(
                "Agent class '{$class}' must extend ".BaseCustomerServiceAgent::class
            );
        }

        return new $class($lead);
    }

    private function resolveNiche(Lead $lead): string
    {
        $config = $this->configResolver->forLead($lead);

        return $config['agent_niche'] ?? 'inss';
    }
}
