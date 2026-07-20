<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConversationSessionRequest extends FormRequest
{
    /**
     * Only a member who can update the parent lead may open a manual atendimento.
     */
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead && ($this->user()?->can('update', $lead) ?? false);
    }

    /**
     * No client input drives the open — the tenant, lead and reason are derived
     * server-side, so tenant_id/lead_id can never be mass-assigned from the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
