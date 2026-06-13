<?php

namespace App\Http\Requests\SmartList;

use App\Exceptions\SmartList\InvalidFiltersException;
use App\Services\SmartList\FilterSchema;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\ContactList $list */
        $list = $this->route('list');

        // D-14: block filter edits when a campaign is actively sending to this list.
        if ($list->campaigns()->where('status', 'sending')->exists()) {
            return false;
        }

        return $this->user()?->can('update', $list) ?? true;
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
            'filters_json.required' => 'Os filtros são obrigatórios.',
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
