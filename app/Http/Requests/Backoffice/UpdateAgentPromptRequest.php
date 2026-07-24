<?php

namespace App\Http\Requests\Backoffice;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentPromptRequest extends FormRequest
{
    /** The route is already gated by the `super_admin` middleware. */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_super_admin;
    }

    /**
     * Only the middle of the prompt is editable — the platform head and the
     * FERRAMENTAS / SEGURANÇA / ENCERRAMENTO tail are re-attached by
     * AgentPromptComposer and are not part of this payload.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'editor_mode' => ['required', Rule::in(['structured', 'raw'])],

            'sections' => ['required_if:editor_mode,structured', 'array', 'max:40'],
            'sections.*.title' => ['required', 'string', 'max:120'],
            'sections.*.content' => ['required', 'string', 'max:20000'],

            'raw_content' => ['required_if:editor_mode,raw', 'nullable', 'string', 'regex:/\S/', 'max:60000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'editor_mode.in' => 'Modo de edição inválido.',
            'sections.required_if' => 'Adicione ao menos uma seção.',
            'sections.max' => 'Máximo de 40 seções.',
            'sections.*.title.required' => 'Toda seção precisa de um título.',
            'sections.*.content.required' => 'Toda seção precisa de conteúdo.',
            'raw_content.required_if' => 'Escreva o prompt.',
            'raw_content.regex' => 'O prompt não pode ser vazio.',
        ];
    }
}
