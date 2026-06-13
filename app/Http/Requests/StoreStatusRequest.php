<?php

namespace App\Http\Requests;

use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStatusRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:1', 'max:50'],
            'color' => ['nullable', 'string', Rule::in(Tag::COLORS)],
            'is_terminal' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome do status é obrigatório.',
            'name.max' => 'O nome deve ter no máximo 50 caracteres.',
            'color.in' => 'Cor inválida. Use uma das cores disponíveis.',
        ];
    }
}
