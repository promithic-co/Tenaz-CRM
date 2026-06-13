<?php

namespace App\Http\Requests\Playground;

use Illuminate\Foundation\Http\FormRequest;

class TesterChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sandbox', $this->route('lead'));
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'persona_prompt' => ['required', 'string', 'max:5000'],
            'cpf_to_use' => ['nullable', 'string', 'max:20'],
            'expected_values' => ['nullable', 'array'],
            'tester_model' => ['nullable', 'string', 'max:100'],
        ];
    }
}
