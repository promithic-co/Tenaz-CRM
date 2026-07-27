<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `slug` and `type` are absent on purpose — both are immutable after creation.
 * See CustomFieldService::update() for why.
 */
class UpdateCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwnerOrAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'required', 'string', 'min:1', 'max:60'],
            'is_required' => ['sometimes', 'boolean'],
            'options' => ['sometimes', 'array', 'max:50'],
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
            'options.*.label.required' => 'Preencha o texto da opção.',
        ];
    }
}
