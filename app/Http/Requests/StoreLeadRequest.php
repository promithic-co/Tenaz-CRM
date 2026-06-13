<?php

namespace App\Http\Requests;

use App\Models\WhatsappInstance;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'whatsapp' => $this->normalizeDigits((string) $this->input('whatsapp', '')),
            'cpf' => $this->input('cpf') !== null
                ? $this->normalizeDigits((string) $this->input('cpf'))
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) ($this->user()?->tenantId ?? '');

        return [
            'nome' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'regex:/^55\d{10,11}$/'],
            'cpf' => ['nullable', 'string', 'regex:/^\d{11}$/'],
            'evolution_instance' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($tenantId): void {
                    $exists = WhatsappInstance::query()
                        ->where('tenant_id', $tenantId)
                        ->where('name', $value)
                        ->exists();

                    if (! $exists) {
                        $fail('Instância de WhatsApp inválida.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'O nome é obrigatório.',
            'whatsapp.required' => 'O WhatsApp é obrigatório.',
            'whatsapp.regex' => 'O WhatsApp deve estar no formato 55DDDNNNNNNNN (10 ou 11 dígitos após o 55).',
            'cpf.regex' => 'O CPF deve conter 11 dígitos.',
            'evolution_instance.required' => 'Selecione uma instância de WhatsApp.',
        ];
    }

    private function normalizeDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
