<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemoveCampaignRecipientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'message_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'message_ids.*' => ['required', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'message_ids.required' => 'Selecione ao menos um destinatário.',
            'message_ids.array' => 'Os destinatários selecionados são inválidos.',
            'message_ids.min' => 'Selecione ao menos um destinatário.',
            'message_ids.max' => 'Selecione no máximo 1.000 destinatários por vez.',
            'message_ids.*.integer' => 'Um destinatário selecionado é inválido.',
        ];
    }
}
