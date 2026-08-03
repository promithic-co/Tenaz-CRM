<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsappConnectionRequest extends FormRequest
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
            'display_name' => ['required', 'string', 'max:100'],
            // Optional: the same form renames the connection, and renaming must
            // not demand a secret. Only a filled PIN triggers the registration.
            'pin' => ['nullable', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'display_name.required' => 'Dê um nome para esta conexão.',
            'display_name.max' => 'O nome da conexão deve ter no máximo 100 caracteres.',
            'pin.size' => 'O PIN deve ter exatamente 6 dígitos.',
            'pin.regex' => 'O PIN deve conter apenas números.',
        ];
    }
}
