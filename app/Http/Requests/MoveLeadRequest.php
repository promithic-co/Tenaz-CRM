<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'lead_id' => ['required', 'integer'],
            'from_status' => ['required', 'string', 'max:40'],
            'to_status' => ['required', 'string', 'max:40', 'different:from_status'],
        ];
    }
}
