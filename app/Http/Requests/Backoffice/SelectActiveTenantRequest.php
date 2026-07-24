<?php

namespace App\Http\Requests\Backoffice;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SelectActiveTenantRequest extends FormRequest
{
    /** The route is already gated by the `super_admin` middleware. */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_super_admin;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'exists:tenants,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tenant_id.required' => 'Selecione uma empresa.',
            'tenant_id.exists' => 'Empresa não encontrada.',
        ];
    }
}
