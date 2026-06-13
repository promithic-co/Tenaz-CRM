<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderStatusesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwnerOrAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'slugs' => ['required', 'array', 'min:1'],
            'slugs.*' => ['required', 'string', 'min:1', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slugs.required' => 'A lista de slugs é obrigatória.',
            'slugs.array' => 'O campo slugs deve ser um array.',
        ];
    }
}
