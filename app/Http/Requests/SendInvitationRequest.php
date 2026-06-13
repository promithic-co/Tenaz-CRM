<?php

namespace App\Http\Requests;

use App\Enums\TenantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isOwnerOrAdmin();
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenantId;

        return [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('tenant_invitations', 'email')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('accepted_at')),
            ],
            'role' => [
                'required',
                'string',
                Rule::in(array_map(fn (TenantRole $r) => $r->value, TenantRole::assignable())),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Já existe um convite pendente para este email.',
            'role.in' => 'O perfil selecionado não é válido.',
        ];
    }
}
