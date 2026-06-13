<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUraApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) $this->user()->tenantId;

        return [
            'name' => ['required', 'string', 'max:255'],
            'agent_id' => [
                'required',
                'integer',
                Rule::exists('agents', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'whatsapp_template_id' => [
                'nullable',
                'integer',
                Rule::exists('whatsapp_templates', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome da integração é obrigatório.',
            'agent_id.required' => 'Selecione um agente.',
            'agent_id.exists' => 'O agente selecionado não existe.',
            'whatsapp_template_id.exists' => 'O template selecionado não existe.',
        ];
    }
}
