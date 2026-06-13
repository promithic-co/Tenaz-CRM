<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for the resolve/close ticket lifecycle endpoints — both
 * accept the same optional resolution metadata. Authorization stays in the
 * controller (`authorize('update', $ticket)`) alongside the distinct lifecycle
 * calls and flash messages each action issues.
 */
class ResolveTicketRequest extends FormRequest
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
            'resolution_reason' => ['nullable', 'string', 'max:120'],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
