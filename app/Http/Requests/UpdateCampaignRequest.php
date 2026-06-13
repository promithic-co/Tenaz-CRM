<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = (string) $this->user()->tenantId;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_list_id' => [
                'sometimes',
                'required',
                Rule::exists('contact_lists', 'id')->where('tenant_id', $tenantId),
            ],
            'whatsapp_template_id' => [
                'sometimes',
                'required',
                Rule::exists('whatsapp_templates', 'id')->where('tenant_id', $tenantId),
            ],
            'template_params_mapping' => ['nullable', 'array'],
            'template_params_mapping.*' => ['string'],
            'daily_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'delay_between_ms' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'error_threshold_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome da campanha é obrigatório.',
            'contact_list_id.exists' => 'A lista de contatos selecionada não é válida.',
            'whatsapp_template_id.exists' => 'O template selecionado não é válido.',
            'scheduled_at.after' => 'O agendamento deve ser uma data futura.',
        ];
    }
}
