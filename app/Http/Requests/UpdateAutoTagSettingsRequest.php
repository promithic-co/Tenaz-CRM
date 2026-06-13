<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAutoTagSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'auto_tagging_enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'auto_tagging_enabled.required' => 'O campo de ativação de auto-tag é obrigatório.',
            'auto_tagging_enabled.boolean' => 'O campo de ativação de auto-tag deve ser verdadeiro ou falso.',
        ];
    }
}
