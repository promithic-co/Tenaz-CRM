<?php

namespace App\Http\Requests;

use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusRequest extends FormRequest
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
            'label' => ['sometimes', 'required', 'string', 'min:1', 'max:50'],
            'color' => ['sometimes', 'nullable', 'string', Rule::in(Tag::COLORS)],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_terminal' => ['sometimes', 'boolean'],
            'slug' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'O label do status é obrigatório.',
            'label.max' => 'O label deve ter no máximo 50 caracteres.',
            'color.in' => 'Cor inválida. Use uma das cores disponíveis.',
        ];
    }
}
