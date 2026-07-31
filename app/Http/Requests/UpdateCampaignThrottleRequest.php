<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignThrottleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'daily_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            'delay_between_ms' => ['required', 'integer', 'min:0', 'max:60000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'daily_limit.required' => 'O limite diário é obrigatório.',
            'daily_limit.min' => 'O limite diário deve ser de ao menos 1 mensagem.',
            'delay_between_ms.required' => 'O atraso entre envios é obrigatório.',
        ];
    }
}
