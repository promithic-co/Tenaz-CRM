<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentInstanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $agentId = $this->route('agent')?->id;

        return [
            'whatsapp_instance_id' => [
                'nullable',
                'integer',
                Rule::exists('whatsapp_instances', 'id')->where(function ($query) use ($agentId) {
                    // Explicit user_id + tenant_id: raw Rule::exists queries bypass the Eloquent tenant global scope
                    $query->where('user_id', $this->user()->id)
                        ->where('tenant_id', $this->user()->tenantId)
                        ->where(function ($q) use ($agentId) {
                            $q->whereNull('agent_id')->orWhere('agent_id', $agentId);
                        });
                }),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'whatsapp_instance_id.exists' => 'Selecione uma instância disponível que pertença à sua conta.',
        ];
    }
}
