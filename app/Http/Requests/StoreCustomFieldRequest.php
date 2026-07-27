<?php

namespace App\Http\Requests;

use App\Services\CustomFieldService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwnerOrAdmin() ?? false;
    }

    /**
     * The slug is derived from the label by CustomFieldService, never submitted:
     * smart-list filters reference it as `custom_field:<slug>`, so it must stay
     * inside the character set FilterSchema accepts.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'min:1', 'max:60'],
            'type' => ['required', 'string', Rule::in(CustomFieldService::TYPES)],
            'is_required' => ['sometimes', 'boolean'],
            'options' => ['array', 'max:50', Rule::requiredIf(fn (): bool => $this->input('type') === 'select')],
            'options.*.label' => ['required', 'string', 'max:60'],
            'options.*.value' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'label.required' => 'Dê um nome ao campo.',
            'label.max' => 'O nome deve ter no máximo 60 caracteres.',
            'type.in' => 'Tipo de campo inválido.',
            'options.required' => 'Um campo de seleção precisa de ao menos uma opção.',
            'options.*.label.required' => 'Preencha o texto da opção.',
        ];
    }
}
