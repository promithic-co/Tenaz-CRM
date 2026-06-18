<?php

namespace App\Http\Requests\Playground;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'objective' => ['required', 'string', 'max:1000'],
            'cycle' => ['required', 'integer', 'min:1'],
            'tester_model' => ['nullable', 'string', 'max:100', Rule::in(config('credflow.agent.playground_models'))],
        ];
    }
}
