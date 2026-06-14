<?php

namespace App\Http\Requests;

use App\Enums\TemplateKind;
use App\Enums\WhatsAppProvider;
use App\Services\WhatsApp\MetaTemplateService;
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
            'language' => ['required', 'string', Rule::in(['pt_BR'])],
            'variable_examples' => ['nullable', 'array'],
            'variable_examples.*' => ['nullable', 'string', 'max:200'],
            'header_text' => ['nullable', 'string', 'max:60'],
            'header_example' => ['nullable', 'string', 'max:200'],
            'footer_text' => ['nullable', 'string', 'max:60'],
            'buttons' => ['nullable', 'array', 'max:10'],
            'buttons.*.type' => ['required_with:buttons', 'string', Rule::in(MetaTemplateService::BUTTON_TYPES)],
            'buttons.*.text' => ['required_with:buttons', 'string', 'max:25'],
            'buttons.*.url' => ['nullable', 'required_if:buttons.*.type,URL', 'string', 'url', 'max:2000'],
            'buttons.*.phone_number' => ['nullable', 'required_if:buttons.*.type,PHONE_NUMBER', 'string', 'max:20'],
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

        $validator->after(function (Validator $validator): void {
            $header = (string) $this->input('header_text', '');
            if ($header === '') {
                return;
            }

            preg_match_all('/\{\{(\d+)\}\}/', $header, $matches);
            $numbers = array_values(array_unique(array_map('intval', $matches[1] ?? [])));

            if ($numbers === []) {
                return;
            }

            if ($numbers !== [1]) {
                $validator->errors()->add('header_text', 'O cabecalho aceita no maximo uma variavel, e ela deve ser {{1}}.');

                return;
            }

            if (! filled($this->input('header_example'))) {
                $validator->errors()->add('header_example', 'Informe um exemplo para a variavel {{1}} do cabecalho.');
            }
        });

        $validator->after(function (Validator $validator): void {
            $footer = (string) $this->input('footer_text', '');

            if (preg_match('/\{\{\d+\}\}/', $footer)) {
                $validator->errors()->add('footer_text', 'O rodape nao aceita variaveis.');
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
            'language.in' => 'Idioma nao suportado. Utilize pt_BR.',
            'name.required' => 'O nome do template é obrigatório.',
            'buttons.max' => 'Um template aceita no maximo 10 botoes.',
            'buttons.*.type.required_with' => 'Selecione o tipo do botao.',
            'buttons.*.type.in' => 'Tipo de botao invalido.',
            'buttons.*.text.required_with' => 'Informe o texto do botao.',
            'buttons.*.url.required_if' => 'Informe a URL do botao.',
            'buttons.*.url.url' => 'A URL do botao e invalida.',
            'buttons.*.phone_number.required_if' => 'Informe o telefone do botao.',
            'status.prohibited' => 'O status do template e definido pela Meta e nao pode ser informado manualmente.',
            'meta_template_id.prohibited' => 'O ID do template e gerado pela Meta e nao pode ser informado manualmente.',
            'meta_waba_id.prohibited' => 'O WABA ID vem da instancia selecionada e nao pode ser informado manualmente.',
        ];
    }
}
