<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadCollectedInformationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead && ($this->user()?->can('update', $lead) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'operation' => ['required', 'string', Rule::in(['upsert', 'delete'])],
            'key' => ['nullable', 'required_if:operation,delete', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'label' => ['nullable', 'required_if:operation,upsert', 'string', 'max:60'],
            'value' => ['nullable', 'required_if:operation,upsert', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'operation.required' => 'Informe a operação desejada.',
            'operation.in' => 'A operação informada é inválida.',
            'key.required_if' => 'Informe qual informação deve ser removida.',
            'key.regex' => 'A chave da informação é inválida.',
            'label.required_if' => 'Informe um rótulo.',
            'label.max' => 'O rótulo deve ter no máximo 60 caracteres.',
            'value.required_if' => 'Informe um valor.',
            'value.max' => 'O valor deve ter no máximo 500 caracteres.',
        ];
    }
}
