<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkLeadActionRequest extends FormRequest
{
    public const ACTIONS = [
        'pause-ai',
        'resume-ai',
        'pause-followup',
        'resume-followup',
        'disable-followup',
        'delete',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lead_ids' => ['required', 'array', 'min:1', 'max:100'],
            'lead_ids.*' => ['integer', 'min:1'],
            'action' => ['required', 'string', 'in:'.implode(',', self::ACTIONS)],
        ];
    }
}
