<?php

namespace App\Http\Requests\SmartList;

use App\Exceptions\SmartList\InvalidFiltersException;
use App\Services\SmartList\FilterSchema;
use Illuminate\Foundation\Http\FormRequest;

class StoreDynamicContactListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_dynamic' => ['boolean'],
            'filters_json' => ['nullable', 'array'],
        ];

        if ($this->boolean('is_dynamic')) {
            $rules['filters_json'] = ['required', 'array'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome da lista é obrigatório.',
            'filters_json.required' => 'Os filtros são obrigatórios para listas dinâmicas.',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            if (! $this->boolean('is_dynamic')) {
                return;
            }

            $filtersJson = $this->input('filters_json', []);

            if (empty($filtersJson)) {
                return;
            }

            try {
                FilterSchema::validate($filtersJson);
            } catch (InvalidFiltersException $e) {
                $v->errors()->add(
                    'filters_json',
                    $e->getMessage(),
                );
            }
        });
    }
}
