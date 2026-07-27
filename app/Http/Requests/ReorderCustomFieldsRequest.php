<?php

namespace App\Http\Requests;

use App\Services\CustomFieldService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderCustomFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwnerOrAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['present', 'array', 'max:'.CustomFieldService::MAX_FIELDS],
            'ids.*' => ['integer'],
        ];
    }
}
