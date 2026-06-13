<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lead_ids' => ['required', 'array', 'min:1', 'max:100'],
            'lead_ids.*' => ['required', 'integer', 'exists:leads,id'],
            'target_type' => ['required', 'string', 'in:user'],
            'target_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
