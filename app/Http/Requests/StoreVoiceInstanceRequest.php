<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoiceInstanceRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:100'],
            'display_name' => ['nullable', 'string', 'max:150'],
            'whatsapp_instance_id' => ['nullable', 'integer', 'exists:whatsapp_instances,id'],
            'greeting_template' => ['nullable', 'string', 'max:500'],
            'post_call_message' => ['nullable', 'string', 'max:1000'],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'whatsapp_instance_id.exists' => 'A instância WhatsApp selecionada não existe.',
        ];
    }
}
