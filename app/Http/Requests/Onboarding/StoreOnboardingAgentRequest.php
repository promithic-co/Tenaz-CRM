<?php

namespace App\Http\Requests\Onboarding;

use App\Services\AgentTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingAgentRequest extends FormRequest
{
    /**
     * Only incomplete non-super-admin tenant owners may submit this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && ! $user->is_super_admin
            && $user->isOwner()
            && $user->onboarded_at === null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'template_slug' => [
                'required',
                'string',
                Rule::in(app(AgentTemplateService::class)->slugs()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'template_slug.required' => 'Selecione um template para continuar.',
            'template_slug.in' => 'O template selecionado não é válido.',
        ];
    }
}
