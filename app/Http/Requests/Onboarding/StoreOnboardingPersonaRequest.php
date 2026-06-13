<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class StoreOnboardingPersonaRequest extends FormRequest
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
     * Exactly the Phase 59 four-field persona contract (D-20).
     * No LLM, parameter, or escalation fields are validated here — they are
     * not persisted and are silently ignored even if submitted.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'agent_name'        => ['required', 'string', 'max:50'],
            'company_name'      => ['required', 'string', 'max:100'],
            'agent_personality' => ['required', 'string', 'max:200'],
            'agent_greeting'    => ['required', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agent_name.required'        => 'O nome do agente é obrigatório.',
            'company_name.required'      => 'O nome da empresa é obrigatório.',
            'agent_personality.required' => 'A personalidade do agente é obrigatória.',
            'agent_greeting.required'    => 'A saudação inicial é obrigatória.',
        ];
    }
}
