<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentScopedConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $validNiches = array_keys(config('credflow.agent_specializations', []));

        return [
            'agent_niche' => ['required', 'string', Rule::in($validNiches)],
            'agent_name' => ['required', 'string', 'max:50'],
            'company_name' => ['required', 'string', 'max:100'],
            'agent_personality' => ['required', 'string', 'max:200'],
            'agent_greeting' => ['required', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agent_niche.required' => 'A especializacao do agente e obrigatoria.',
            'agent_niche.in' => 'Especializacao de agente invalida.',
            'agent_name.required' => 'O nome do agente é obrigatório.',
            'company_name.required' => 'O nome da empresa é obrigatório.',
            'agent_personality.required' => 'A personalidade do agente é obrigatória.',
            'agent_greeting.required' => 'A saudação inicial é obrigatória.',
        ];
    }
}
