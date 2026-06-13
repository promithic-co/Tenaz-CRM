<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransitionRequest extends FormRequest
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
            'from' => ['required', 'string', 'min:1', 'max:80'],
            'to' => ['required', 'string', 'min:1', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.required' => 'O status de origem é obrigatório.',
            'to.required' => 'O status de destino é obrigatório.',
        ];
    }
}
