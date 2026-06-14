<?php

namespace App\Http\Requests;

use App\Enums\TemplateKind;
use App\Enums\WhatsAppProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWhatsappTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->user()->tenantId;

        return [
            'kind' => ['required', 'string', Rule::in(array_column(TemplateKind::cases(), 'value'))],
            'name' => ['required', 'string', 'max:255'],
            'meta_template_name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'body' => ['required', 'string', 'max:1024'],
            'category' => ['required', 'string', Rule::in(['MARKETING', 'UTILITY'])],
            'language' => ['required', 'string', 'max:20'],
            'variable_examples' => ['nullable', 'array'],
            'variable_examples.*' => ['nullable', 'string', 'max:200'],
            'whatsapp_instance_id' => [
                'required',
                'integer',
                Rule::exists('whatsapp_instances', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)
                        ->whereIn('provider', [WhatsAppProvider::MetaCloud->value]);
                }),
            ],
            'status' => ['prohibited'],
            'meta_template_id' => ['prohibited'],
            'meta_waba_id' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $body = (string) $this->input('body', '');
            preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);
            $numbers = array_values(array_unique(array_map('intval', $matches[1] ?? [])));

            if ($numbers === []) {
                return;
            }

            sort($numbers);
            $expected = range(1, max($numbers));
            if ($numbers !== $expected) {
                $validator->errors()->add('body', 'As variaveis devem ser sequenciais: {{1}}, {{2}}, {{3}}...');
            }

            if (preg_match('/^\s*\{\{\d+\}\}/', $body) || preg_match('/\{\{\d+\}\}\s*$/', $body)) {
                $validator->errors()->add('body', 'O corpo nao deve comecar ou terminar com uma variavel.');
            }

            if (preg_match('/\{\{\d+\}\}\s*\{\{\d+\}\}/', $body)) {
                $validator->errors()->add('body', 'Insira texto entre variaveis adjacentes.');
            }

            $examples = (array) $this->input('variable_examples', []);
            foreach ($expected as $number) {
                if (! filled($examples[(string) $number] ?? null)) {
                    $validator->errors()->add("variable_examples.{$number}", 'Informe um exemplo para {{'.$number.'}}.');
                }
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kind.required' => 'O tipo do template é obrigatório.',
            'kind.in' => 'Tipo de template inválido.',
            'whatsapp_instance_id.required' => 'A instância WhatsApp é obrigatória.',
            'whatsapp_instance_id.exists' => 'A instância selecionada não é válida ou não pertence à sua conta.',
            'meta_template_name.required' => 'O nome do template Meta é obrigatório.',
            'name.required' => 'O nome do template é obrigatório.',
            'status.prohibited' => 'O status do template e definido pela Meta e nao pode ser informado manualmente.',
            'meta_template_id.prohibited' => 'O ID do template e gerado pela Meta e nao pode ser informado manualmente.',
            'meta_waba_id.prohibited' => 'O WABA ID vem da instancia selecionada e nao pode ser informado manualmente.',
        ];
    }
}
