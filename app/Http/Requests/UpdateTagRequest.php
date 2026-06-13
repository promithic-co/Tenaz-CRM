<?php

namespace App\Http\Requests;

use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = (string) ($this->user()?->tenantId ?? '');
        $tagId = $this->route('tag')?->id;

        return [
            'name' => ['required', 'string', 'min:1', 'max:50'],
            'color' => ['nullable', 'string', Rule::in(Tag::COLORS)],
            'is_hot' => ['sometimes', 'boolean'],
            'ai_detectable' => ['sometimes', 'boolean'],
            'ai_description' => ['required_if:ai_detectable,true', 'nullable', 'string', 'max:500'],
            'ai_min_confidence' => ['sometimes', 'nullable', 'numeric', 'min:0.50', 'max:0.99'],
            'slug' => [
                'nullable',
                'string',
                Rule::unique('tags', 'slug')
                    ->ignore($tagId)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) $this->input('name', '')),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome da tag é obrigatório.',
            'slug.unique' => 'Já existe uma tag com este nome neste tenant.',
        ];
    }
}
