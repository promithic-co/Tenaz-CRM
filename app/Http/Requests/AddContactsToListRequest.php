<?php

namespace App\Http\Requests;

use App\Models\ContactList;
use Illuminate\Foundation\Http\FormRequest;

class AddContactsToListRequest extends FormRequest
{
    public function authorize(): bool
    {
        $list = $this->route('list');

        return $list instanceof ContactList && $this->user()?->can('update', $list);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contact_ids' => ['required', 'array', 'min:1'],
            'contact_ids.*' => ['integer', 'exists:contacts,id'],
        ];
    }
}
