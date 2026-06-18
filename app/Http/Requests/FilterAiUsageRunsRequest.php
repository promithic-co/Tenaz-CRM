<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterAiUsageRunsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'agent_id' => ['nullable', 'integer'],
            'architecture_version' => ['nullable', Rule::in(['legacy_prompt', 'folder_skills', 'hybrid'])],
            'model' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['success', 'fallback', 'timeout', 'error', 'human_handoff'])],
        ];
    }
}
