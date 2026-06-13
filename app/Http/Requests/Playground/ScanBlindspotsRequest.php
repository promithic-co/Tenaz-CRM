<?php

namespace App\Http\Requests\Playground;

use Illuminate\Foundation\Http\FormRequest;

class ScanBlindspotsRequest extends FormRequest
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
            'tester_model' => ['nullable', 'string', 'max:100'],
            'focus_areas' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
