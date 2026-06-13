<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUraTriggerRequest extends FormRequest
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
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            'name' => ['nullable', 'string', 'max:255'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['nullable', 'string', 'max:1024'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'O telefone é obrigatório.',
            'phone.regex' => 'Formato E.164 obrigatório (ex: +5511999999999).',
        ];
    }
}
