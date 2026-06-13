<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReevaluateLeadAutoTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
