<?php

namespace App\Http\Requests\Playground;

use Illuminate\Foundation\Http\FormRequest;

class EvaluateRequest extends FormRequest
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
            'token_metrics' => ['nullable', 'array'],
            'evaluator_model' => ['nullable', 'string', 'max:100'],
        ];
    }
}
