<?php

namespace App\Http\Requests;

use App\Models\ConversationSession;
use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloseConversationSessionRequest extends FormRequest
{
    /**
     * Outcomes an operator may pick when closing an atendimento by hand.
     * `abandoned` is excluded — it is reserved for the auto-close scheduler.
     *
     * @var list<string>
     */
    public const SELECTABLE_OUTCOMES = [
        ConversationSession::OUTCOME_CONVERTED,
        ConversationSession::OUTCOME_LOST,
        ConversationSession::OUTCOME_NO_RESPONSE,
        ConversationSession::OUTCOME_MANUAL_CLOSE,
    ];

    /**
     * Only a member who can update the parent lead may close its atendimento.
     */
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead && ($this->user()?->can('update', $lead) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', Rule::in(self::SELECTABLE_OUTCOMES)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'outcome.required' => 'Informe o resultado do atendimento.',
            'outcome.in' => 'O resultado informado é inválido.',
        ];
    }
}
