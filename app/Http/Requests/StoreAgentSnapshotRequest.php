<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentSnapshotRequest extends FormRequest
{
    /**
     * Only owners/administrators may snapshot an agent into a template. The
     * agent-level tenant check is enforced separately by the controller policy.
     */
    public function authorize(): bool
    {
        return $this->user()->isOwnerOrAdmin();
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'visibility' => ['nullable', Rule::in(['tenant', 'system'])],
        ];
    }

    /**
     * System-wide publication is a platform decision, so a non-super-admin can
     * never promote a snapshot beyond its own tenant regardless of input.
     */
    public function resolvedVisibility(): string
    {
        $requested = (string) ($this->validated('visibility') ?? 'tenant');

        if ($requested === 'system' && ! $this->user()->is_super_admin) {
            return 'tenant';
        }

        return $requested;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Dê um nome ao modelo.',
        ];
    }
}
