<?php

namespace App\Http\Requests;

use App\Enums\WhatsAppProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsappInstanceRequest extends FormRequest
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
        $isMetaCloud = $this->input('provider') === WhatsAppProvider::MetaCloud->value;

        return [
            'display_name' => ['nullable', 'string', 'max:100'],
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_\-]+$/',
                Rule::unique('whatsapp_instances')->where('user_id', $this->user()->id),
            ],
            'provider' => ['sometimes', 'string', Rule::enum(WhatsAppProvider::class)],
            // Meta Cloud uses the signup_token (short-lived cache key) instead of raw credentials
            'meta_signup_token' => [$isMetaCloud ? 'required' : 'nullable', 'string', 'size:64'],
            'meta_pin' => ['nullable', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'O nome da instância só pode conter letras, números, hífens e underscores.',
            'name.unique' => 'Você já possui uma instância com este nome.',
            'meta_signup_token.required' => 'Complete o processo de Embedded Signup para vincular sua conta Meta.',
            'meta_signup_token.size' => 'Token de signup inválido.',
            'meta_pin.size' => 'O PIN deve ter exatamente 6 dígitos.',
            'meta_pin.regex' => 'O PIN deve conter apenas números.',
        ];
    }
}
