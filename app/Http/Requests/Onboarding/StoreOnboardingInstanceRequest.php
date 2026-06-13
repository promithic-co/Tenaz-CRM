<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingInstanceRequest extends FormRequest
{
    /**
     * Only incomplete non-super-admin tenant owners may submit this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && ! $user->is_super_admin
            && $user->isOwner()
            && $user->onboarded_at === null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'whatsapp_instance_id' => [
                'nullable',
                'integer',
                Rule::exists('whatsapp_instances', 'id')
                    ->where(fn ($q) => $q
                        ->where('user_id', $user?->id)
                        ->where('tenant_id', $user?->tenantId)
                        ->whereNull('agent_id')
                    ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'whatsapp_instance_id.exists' => 'A instância selecionada não está disponível ou não pertence à sua conta.',
        ];
    }
}
