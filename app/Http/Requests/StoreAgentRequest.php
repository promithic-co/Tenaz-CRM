<?php

namespace App\Http\Requests;

use App\Services\AgentTemplateService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRequest extends FormRequest
{
    /**
     * Schema keys already captured by dedicated top-level form fields —
     * they never generate a dynamic `variables.*` rule.
     */
    private const BUILT_IN_KEYS = ['agent_name', 'company_name', 'description'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Dynamic rules come from the selected template's variables_schema:
     * each non-built-in entry becomes a `variables.{key}` rule, so only
     * schema-declared keys survive validated() (unknown keys are dropped).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $validSlugs = app(AgentTemplateService::class)->slugs($this->user()->tenantId);

        return array_merge([
            'template_slug' => ['required', 'string', Rule::in($validSlugs)],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('agents')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'company_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'whatsapp_instance_id' => [
                'nullable',
                'integer',
                Rule::exists('whatsapp_instances', 'id')->where(function ($query) {
                    // Explicit tenant + user scope: raw Rule::exists queries bypass the Eloquent tenant global scope
                    $query->where('user_id', $this->user()->id)
                        ->where('tenant_id', $this->user()->tenantId)
                        ->whereNull('agent_id');
                }),
            ],
        ], $this->variableRules());
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function variableRules(): array
    {
        $rules = [];

        foreach ($this->templateVariablesSchema() as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key === '' || in_array($key, self::BUILT_IN_KEYS, true)) {
                continue;
            }

            $fieldRules = [
                ($field['required'] ?? false) ? 'required' : 'nullable',
                'string',
                'max:'.(int) ($field['max'] ?? 1000),
            ];

            if (($field['type'] ?? 'text') === 'select' && ! empty($field['options'])) {
                $fieldRules[] = Rule::in(array_column($field['options'], 'value'));
            }

            $rules["variables.{$key}"] = $fieldRules;
        }

        return $rules;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templateVariablesSchema(): array
    {
        $template = app(AgentTemplateService::class)->find((string) $this->input('template_slug'));

        return $template['variables_schema'] ?? [];
    }

    /**
     * Human labels for dynamic variables so validation messages read naturally.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->templateVariablesSchema() as $field) {
            if (! empty($field['key']) && ! empty($field['label'])) {
                $attributes["variables.{$field['key']}"] = $field['label'];
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'template_slug.required' => 'Selecione um modelo para o agente.',
            'template_slug.in' => 'Modelo de agente inválido.',
            'company_name.required' => 'Informe o nome da empresa que este agente vai atender.',
            'name.unique' => 'Você já possui um agente com esse nome.',
            'whatsapp_instance_id.exists' => 'Selecione uma instância disponível que ainda não esteja vinculada a outro agente.',
        ];
    }
}
