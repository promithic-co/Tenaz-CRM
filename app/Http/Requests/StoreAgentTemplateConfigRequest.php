<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentTemplateConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Super-admin gate is enforced by EnsureSuperAdmin middleware (Phase 57); this request validates shape only.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'template_slug' => ['required', 'string', 'max:100'],
            'agent_provider' => ['required', 'string', Rule::in(config('credflow.agent.provider_whitelist'))],
            'agent_model' => ['required', 'string', 'regex:/\S/', 'max:150'],
            'transcription_provider' => ['required', 'string', Rule::in(config('credflow.agent.provider_whitelist'))],
            'transcription_model' => ['required', 'string', 'regex:/\S/', 'max:150'],
            'vision_provider' => ['required', 'string', Rule::in(config('credflow.agent.provider_whitelist'))],
            'vision_model' => ['required', 'string', 'regex:/\S/', 'max:150'],
            'temperature' => ['required', 'numeric', 'between:0,2'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:65535'],
            'max_conversation_messages' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agent_provider.in' => 'Provedor inválido.',
            'agent_model.regex' => 'O modelo não pode ser vazio.',
            'transcription_provider.in' => 'Provedor de transcrição inválido.',
            'transcription_model.regex' => 'O modelo de transcrição não pode ser vazio.',
            'vision_provider.in' => 'Provedor de visão inválido.',
            'vision_model.regex' => 'O modelo de visão não pode ser vazio.',
        ];
    }
}
