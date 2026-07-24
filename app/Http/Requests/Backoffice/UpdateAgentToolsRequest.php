<?php

namespace App\Http\Requests\Backoffice;

use App\Enums\AgentToolCapability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentToolsRequest extends FormRequest
{
    /** The route is already gated by the `super_admin` middleware. */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_super_admin;
    }

    /**
     * `capabilities` is `present` rather than `required` because an empty
     * selection is a valid choice: it disables every native tool.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'capabilities' => ['present', 'array'],
            'capabilities.*' => ['string', Rule::enum(AgentToolCapability::class)],
            'webhooks' => ['sometimes', 'array'],
            'webhooks.*.id' => ['required', 'integer'],
            'webhooks.*.is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'capabilities.present' => 'Envie a lista de ferramentas habilitadas.',
            'capabilities.*.enum' => 'Ferramenta desconhecida.',
        ];
    }
}
