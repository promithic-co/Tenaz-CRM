<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStressTestRunRequest extends FormRequest
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
        return [
            'label' => ['required', 'string', 'max:200'],
            'objective' => ['required', 'string', 'max:2000'],
            'cpf_dataset_id' => ['nullable', 'exists:cpf_datasets,id'],
            'config' => ['required', 'array'],
            'config.cycles' => ['required', 'integer', 'min:1', 'max:50'],
            'config.rounds_per_cycle' => ['required', 'integer', 'min:1', 'max:15'],
        ];
    }
}
