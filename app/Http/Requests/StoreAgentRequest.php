<?php

namespace App\Http\Requests;

use App\Services\AgentTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRequest extends FormRequest
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
        $validSlugs = app(AgentTemplateService::class)->slugs();
        $validNiches = array_keys(config('credflow.agent_specializations', []));

        return [
            'template_slug' => ['required', 'string', Rule::in($validSlugs)],
            'agent_niche' => ['required', 'string', Rule::in($validNiches)],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('agents')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'company_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'whatsapp_instance_id' => [
                'nullable',
                'integer',
                Rule::exists('whatsapp_instances', 'id')->where(function ($query) {
                    // Explicit tenant + user scope: raw Rule::exists queries bypass the Eloquent tenant global scope
                    $query->where('user_id', $this->user()->id)
                        ->where('tenant_id', $this->user()->tenantId)
                        ->whereNull('agent_id');
                }),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'template_slug.required' => 'Selecione um modelo para o agente.',
            'template_slug.in' => 'Modelo de agente inválido.',
            'agent_niche.required' => 'Selecione a especializacao do agente.',
            'agent_niche.in' => 'Especializacao de agente invalida.',
            'company_name.required' => 'Informe o nome da empresa ou financeira que este agente vai atender.',
            'name.unique' => 'Você já possui um agente com esse nome.',
            'whatsapp_instance_id.exists' => 'Selecione uma instância disponível que ainda não esteja vinculada a outro agente.',
        ];
    }
}
