<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        return $contact instanceof Contact && $this->user()?->can('update', $contact);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->input('phone') !== null
                ? $this->normalizeDigits((string) $this->input('phone'))
                : null,
            'cpf' => $this->input('cpf') !== null
                ? $this->normalizeDigits((string) $this->input('cpf'))
                : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Contact $contact */
        $contact = $this->route('contact');
        $tenantId = (string) ($this->user()?->tenantId ?? '');

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => [
                'sometimes',
                'string',
                'regex:/^55\d{10,11}$/',
                Rule::unique('contacts', 'phone')
                    ->ignore($contact->id)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'cpf' => ['nullable', 'string', 'regex:/^\d{11}$/'],
            'opt_in_status' => ['sometimes', 'string', Rule::in([
                Contact::OPT_PENDING,
                Contact::OPT_IN,
                Contact::OPT_OUT,
            ])],
            'extra_data' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function normalizeDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
