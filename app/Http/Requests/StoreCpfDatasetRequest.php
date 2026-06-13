<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCpfDatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,json,txt', 'max:5120'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
