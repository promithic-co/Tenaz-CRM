<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\Cache;

class AgentContextResolver
{
    /**
     * @return array{tenant_id:string,user_id:?int,agent_id:?int,instance_id:?int}
     */
    public function resolveFromInstanceName(?string $instanceName): array
    {
        if (! $instanceName) {
            return [
                'tenant_id' => 'default',
                'user_id' => null,
                'agent_id' => null,
                'instance_id' => null,
            ];
        }

        return Cache::remember("agent_context_instance_{$instanceName}", 300, function () use ($instanceName): array {
            // Prefer instances that have an agent assigned (properly configured).
            // When duplicate names exist across tenants, the one with a valid
            // agent→user relationship wins, ensuring tenant isolation.
            $instance = WhatsappInstance::query()
                ->where('name', $instanceName)
                ->whereNotNull('agent_id')
                ->with('agent:id,user_id')
                ->first();

            // Fallback: instance exists but has no agent assigned yet
            $instance ??= WhatsappInstance::query()->where('name', $instanceName)->first();

            if ($instance) {
                // Derive tenant from the agent's owner (not instance user_id)
                // to guarantee the lead lands in the correct tenant.
                $ownerUserId = $instance->agent?->user_id ?? $instance->user_id;
                $ownerUser = User::find($ownerUserId);

                return [
                    'tenant_id' => $ownerUser?->tenantId ?? (string) $ownerUserId,
                    'user_id' => $ownerUserId,
                    'agent_id' => $instance->agent_id,
                    'instance_id' => $instance->id,
                ];
            }

            $setting = AppSetting::with('user')
                ->where('key', 'evolution_instance_name')
                ->where('value', $instanceName)
                ->whereNotNull('user_id')
                ->first();

            $userId = $setting?->user_id;
            $tenantId = $setting?->user ? $setting->user->tenantId : 'default';
            $defaultAgentId = $userId
                ? Agent::query()
                    ->where('user_id', $userId)
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->value('id')
                : null;

            return [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'agent_id' => $defaultAgentId,
                'instance_id' => null,
            ];
        });
    }
}
