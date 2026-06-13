<?php

namespace App\Actions;

use App\Models\Agent;
use App\Models\WhatsappInstance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Re-points an agent at a WhatsApp instance (or unlinks it), under a row lock,
 * deriving activation from the persisted relationship and invalidating the
 * routing cache for every affected instance name.
 */
class ReassignAgentInstanceAction
{
    /**
     * @throws ValidationException when the candidate instance is unavailable
     *                             (race, cross-tenant, or already assigned).
     */
    public function execute(Agent $agent, int $userId, ?string $tenantId, ?int $newInstanceId): void
    {
        $affectedNames = [];

        DB::transaction(function () use ($agent, $userId, $tenantId, $newInstanceId, &$affectedNames): void {
            // Lock current instance rows for this agent to prevent race on unlink
            $currentInstances = WhatsappInstance::query()
                ->where('agent_id', $agent->id)
                ->lockForUpdate()
                ->get();

            foreach ($currentInstances as $inst) {
                $affectedNames[] = $inst->name;
            }

            if ($newInstanceId) {
                // Lock and inspect the candidate while it is still free.
                // Explicit user_id + tenant_id constraint: raw query bypasses Eloquent tenant global scope (T-60-04).
                $candidate = WhatsappInstance::query()
                    ->where('id', $newInstanceId)
                    ->where('user_id', $userId)
                    ->where('tenant_id', $tenantId)
                    ->whereNull('agent_id')
                    ->lockForUpdate()
                    ->first();

                if (! $candidate) {
                    // Race, cross-tenant, or already-assigned — preserve existing link and abort
                    throw new ValidationException(
                        validator([], []),
                        back()->withErrors(['whatsapp_instance_id' => 'Instância indisponível. Selecione outra instância livre.'])
                    );
                }

                $affectedNames[] = $candidate->name;

                // Unlink current rows only after candidate resolution succeeds
                WhatsappInstance::query()->where('agent_id', $agent->id)->update(['agent_id' => null]);

                // Link the validated candidate
                $candidate->update(['agent_id' => $agent->id]);
            } else {
                // Explicit null → unlink
                WhatsappInstance::query()->where('agent_id', $agent->id)->update(['agent_id' => null]);
            }

            // Derive activation from the persisted relationship after the write (T-60-06)
            $agent->update(['is_active' => $agent->whatsappInstance()->exists()]);
        });

        // Invalidate routing cache for all affected instance names (outside transaction — cache is not transactional)
        foreach ($affectedNames as $name) {
            Cache::forget("agent_context_instance_{$name}");
        }
    }
}
