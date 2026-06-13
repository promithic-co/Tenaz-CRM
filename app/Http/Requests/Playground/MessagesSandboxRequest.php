<?php

namespace App\Http\Requests\Playground;

use Illuminate\Foundation\Http\FormRequest;

class MessagesSandboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sandbox', $this->route('lead'));
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
