<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Operator notes about the person behind the conversation.
 *
 * Notes live on the Contact (the canonical identity) but are written from the
 * conversation panel, so authorization runs against the parent lead: any member
 * who can work the conversation may leave notes on it. That is deliberately
 * broader than `contatos.update` (owner/administrator), because writing a note
 * is the atendente's core habit, not an administrative act.
 */
class UpdateLeadNotesRequest extends FormRequest
{
    private const MAX_LENGTH = 5000;

    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead && ($this->user()?->can('update', $lead) ?? false);
    }

    /**
     * An empty textarea clears the note rather than failing validation, so the
     * operator can undo a note the same way they wrote it.
     */
    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');

        if (is_string($notes) && trim($notes) === '') {
            $this->merge(['notes' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notes' => ['present', 'nullable', 'string', 'max:'.self::MAX_LENGTH],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'notes.max' => 'As notas devem ter no máximo '.self::MAX_LENGTH.' caracteres.',
        ];
    }
}
