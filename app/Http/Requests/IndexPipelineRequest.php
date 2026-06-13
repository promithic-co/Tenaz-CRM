<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'agent_id' => ['nullable', 'integer'],
            'instance_id' => ['nullable', 'integer'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:120'],
            'view' => ['nullable', 'in:kanban,lista'],
        ];
    }
}
