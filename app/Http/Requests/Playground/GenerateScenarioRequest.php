<?php

namespace App\Http\Requests\Playground;

use Illuminate\Foundation\Http\FormRequest;

class GenerateScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'objective' => ['required', 'string', 'max:1000'],
            'cycle' => ['required', 'integer', 'min:1'],
            'tester_model' => ['nullable', 'string', 'max:100'],
        ];
    }
}
