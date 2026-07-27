<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Set the negotiated amount and the forecast close date on an atendimento.
 *
 * The amount arrives already in cents as an integer. Parsing a localised decimal string
 * server-side is ambiguous — "1.234" is one thousand two hundred thirty-four in pt-BR and
 * one point two three four elsewhere — so the masked input does the conversion and the
 * server only ever sees the unit it stores.
 */
class UpdateConversationSessionValueRequest extends FormRequest
{
    /** Ten million reais. High enough for any real deal, low enough to catch a mask bug. */
    public const MAX_VALUE_CENTS = 1_000_000_000;

    /**
     * Only a member who can update the parent lead may price its atendimento.
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
            'value_cents' => ['present', 'nullable', 'integer', 'min:0', 'max:'.self::MAX_VALUE_CENTS],
            'expected_close_at' => ['present', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'value_cents.integer' => 'Informe o valor em centavos.',
            'value_cents.min' => 'O valor não pode ser negativo.',
            'value_cents.max' => 'O valor informado é alto demais.',
            'expected_close_at.date_format' => 'Informe a previsão no formato AAAA-MM-DD.',
        ];
    }
}
