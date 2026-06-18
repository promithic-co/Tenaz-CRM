<?php

namespace App\Http\Requests\Playground;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScanBlindspotsRequest extends FormRequest
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
            'tester_model' => ['nullable', 'string', 'max:100', Rule::in(config('credflow.agent.playground_models'))],
            'focus_areas' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
