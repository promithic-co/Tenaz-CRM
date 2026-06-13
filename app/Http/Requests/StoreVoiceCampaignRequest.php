<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoiceCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $validVoices = [
            'Google.pt-BR-Standard-A',
            'Google.pt-BR-Standard-B',
            'Google.pt-BR-Standard-C',
            'Polly.Camila-Neural',
            'Polly.Thiago-Neural',
            'Polly.Vitoria-Neural',
        ];

        return [
            'name' => ['required', 'string', 'max:200'],
            'contact_list_id' => ['required', 'integer', 'exists:contact_lists,id'],
            'voice_instance_id' => ['required', 'integer', 'exists:voice_instances,id'],
            'greeting_template' => ['nullable', 'string', 'max:500'],
            'tts_voice' => ['nullable', 'string', 'in:'.implode(',', $validVoices)],
            'post_call_message' => ['nullable', 'string', 'max:1000'],
            'delay_between_calls_ms' => ['nullable', 'integer', 'min:1000', 'max:60000'],
            'dtmf_actions' => ['nullable', 'array', 'max:9'],
            'dtmf_actions.*.action' => ['required', 'string', 'in:interested,optout,callback,hangup'],
            'dtmf_actions.*.label' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome da campanha é obrigatório.',
            'contact_list_id.required' => 'Selecione uma lista de contatos.',
            'contact_list_id.exists' => 'A lista de contatos selecionada não existe.',
            'voice_instance_id.required' => 'Selecione uma instância de voz.',
            'voice_instance_id.exists' => 'A instância de voz selecionada não existe.',
            'delay_between_calls_ms.min' => 'O atraso mínimo entre ligações é de 1000ms (1 segundo).',
            'delay_between_calls_ms.max' => 'O atraso máximo entre ligações é de 60000ms (1 minuto).',
        ];
    }
}
