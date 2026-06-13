<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwnerOrAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->normalizeDigits((string) $this->input('phone', '')),
            'cpf' => $this->input('cpf') !== null
                ? $this->normalizeDigits((string) $this->input('cpf'))
                : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = (string) ($this->user()?->tenantId ?? '');

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'regex:/^55\d{10,11}$/',
                Rule::unique('contacts', 'phone')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'cpf' => ['nullable', 'string', 'regex:/^\d{11}$/'],
            'source' => ['nullable', 'string', Rule::in([
                Contact::SOURCE_MANUAL,
                Contact::SOURCE_LEAD_SYNC,
                Contact::SOURCE_CSV_IMPORT,
                Contact::SOURCE_WHATSAPP_INBOUND,
                Contact::SOURCE_URA,
                Contact::SOURCE_AGENT_API,
            ])],
            'opt_in_status' => ['nullable', 'string', Rule::in([
                Contact::OPT_PENDING,
                Contact::OPT_IN,
                Contact::OPT_OUT,
            ])],
            'extra_data' => ['nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.required' => 'O telefone é obrigatório.',
            'phone.regex' => 'O telefone deve estar no formato 55DDDNNNNNNNN.',
            'phone.unique' => 'Já existe um contato com este telefone neste tenant.',
            'cpf.regex' => 'O CPF deve conter 11 dígitos.',
        ];
    }

    private function normalizeDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
