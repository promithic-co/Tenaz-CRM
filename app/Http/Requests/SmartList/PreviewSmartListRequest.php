<?php

namespace App\Http\Requests\SmartList;

use App\Exceptions\SmartList\InvalidFiltersException;
use App\Services\SmartList\FilterSchema;
use Illuminate\Foundation\Http\FormRequest;

class PreviewSmartListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filters_json' => ['required', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'filters_json.required' => 'Os filtros são obrigatórios para pré-visualizar a lista.',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            try {
                FilterSchema::validate($this->input('filters_json', []));
            } catch (InvalidFiltersException $e) {
                $v->errors()->add(
                    'filters_json',
                    $e->getMessage(),
                );
            }
        });
    }
}
