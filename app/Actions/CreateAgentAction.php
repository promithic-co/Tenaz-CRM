<?php

namespace App\Actions;

use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\WhatsappInstance;
use App\Services\AgentTemplateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAgentAction
{
    /**
     * Create an agent with optional WhatsApp instance linking.
     *
     * The agent starts inactive. It becomes active only when a free
     * tenant-owned instance is successfully locked and linked (T-60-06).
     * The entire operation runs inside a DB transaction.
     *
     * When $whatsappInstanceId is supplied but the candidate row is unavailable
     * (race, cross-tenant, or already-assigned), the agent is left inactive and
     * no exception is thrown — the caller decides messaging.
     */
    public function execute(
        int $userId,
        string $tenantId,
        string $name,
        string $templateSlug,
        string $companyName,
        string $agentNiche = 'inss',
        ?string $description = null,
        ?int $whatsappInstanceId = null,
    ): Agent {
        return DB::transaction(function () use (
            $userId,
            $tenantId,
            $name,
            $templateSlug,
            $companyName,
            $agentNiche,
            $description,
            $whatsappInstanceId,
        ) {
            $slug = Str::slug($name).'-'.$userId.'-'.Str::lower(Str::random(6));

            $agent = Agent::create([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'is_active' => false, // remains false until a free instance is proven linked (T-60-06)
                // BelongsToTenant scope already filters by tenant — first agent in tenant becomes default
                'is_default' => ! Agent::query()->exists(),
            ]);

            $templateDefaults = app(AgentTemplateService::class)->defaults($templateSlug);

            AgentConfig::firstOrCreate(
                ['agent_id' => $agent->id],
                array_merge($templateDefaults, [
                    'tenant_id' => $tenantId,
                    'template_slug' => $templateSlug,
                    'agent_name' => $name,
                    'company_name' => $companyName,
                    'agent_niche' => $agentNiche,
                ])
            );

            // Instance link: only when an ID is supplied (D-13).
            // Lock the free candidate inside the transaction to prevent races (T-60-04).
            if ($whatsappInstanceId !== null) {
                $instance = WhatsappInstance::query()
                    ->where('id', $whatsappInstanceId)
                    ->where('user_id', $userId)
                    ->where('tenant_id', $tenantId) // explicit active-tenant scope — raw query bypasses Eloquent global scope
                    ->whereNull('agent_id')
                    ->lockForUpdate()
                    ->first();

                if ($instance) {
                    $instance->update(['agent_id' => $agent->id]);
                    $agent->update(['is_active' => true]); // active ONLY after successful link (T-60-06)
                }
            }

            return $agent;
        });
    }
}
