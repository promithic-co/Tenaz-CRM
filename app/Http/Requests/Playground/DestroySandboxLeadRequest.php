<?php

namespace App\Http\Requests\Playground;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class DestroySandboxLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sandbox', $this->route('lead'));
    }

    /**
     * Preserve the custom Portuguese 403 message the legacy destroy guard
     * carried — the other playground guards remain bare 403.
     */
    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Apenas sessões sandbox do seu tenant podem ser deletadas aqui.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
