<?php

namespace App\Http\Requests\Playground;

use Illuminate\Foundation\Http\FormRequest;

class StoreSandboxLeadRequest extends FormRequest
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
            'label' => ['nullable', 'string', 'max:100'],
            'system_prompt' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
