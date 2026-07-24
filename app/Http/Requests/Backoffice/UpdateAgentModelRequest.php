<?php

namespace App\Http\Requests\Backoffice;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentModelRequest extends FormRequest
{
    /** The route is already gated by the `super_admin` middleware. */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_super_admin;
    }

    /**
     * Mirrors the LLM rules of StoreAgentTemplateConfigRequest — same provider
     * whitelist, same model-slug shape — narrowed to the three fields the
     * backoffice edits per agent.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'agent_provider' => ['required', 'string', Rule::in(config('credflow.agent.provider_whitelist'))],
            'agent_model' => ['required', 'string', 'regex:/\S/', 'max:150'],
            'temperature' => ['required', 'numeric', 'between:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agent_provider.required' => 'Selecione um provedor.',
            'agent_provider.in' => 'Provedor inválido.',
            'agent_model.required' => 'Informe o modelo.',
            'agent_model.regex' => 'O modelo não pode ser vazio.',
            'temperature.between' => 'A temperatura precisa ficar entre 0 e 2.',
        ];
    }
}
