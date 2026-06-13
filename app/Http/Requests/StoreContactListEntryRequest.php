<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactListEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^55\d{10,11}$/'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.required' => 'O telefone é obrigatório.',
            'phone.regex' => 'O telefone deve estar no formato 55XXXXXXXXXXX (com DDD).',
        ];
    }
}
