<?php

namespace App\Http\Requests\Playground;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSandboxPromptRequest extends FormRequest
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
            'system_prompt' => ['nullable', 'string', 'max:10000'],
            'label' => ['nullable', 'string', 'max:100'],
        ];
    }
}
