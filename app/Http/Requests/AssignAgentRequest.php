<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and validates assigning (or unassigning) an agent to a tenant
 * user. Owner/Administrator only; the target agent must belong to the actor's
 * active tenant.
 */
class AssignAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Cross-tenant agent → 404 (distinct from the 403 below). The agent is
        // already tenant-scoped at route binding; this is the defensive guard.
        abort_if($this->route('agent')->tenant_id !== $this->user()->tenantId, 404);

        return $this->user()?->isOwnerOrAdmin() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('tenant_user', 'user_id')
                    ->where(fn ($q) => $q->where('tenant_id', $this->user()->tenantId)),
            ],
        ];
    }
}
