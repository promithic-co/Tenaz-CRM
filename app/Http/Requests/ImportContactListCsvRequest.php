<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportContactListCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'O arquivo CSV é obrigatório.',
            'file.mimes' => 'O arquivo deve ser CSV ou TXT.',
            'file.max' => 'O arquivo não pode exceder 5 MB.',
        ];
    }
}
