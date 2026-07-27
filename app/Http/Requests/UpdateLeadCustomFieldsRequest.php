<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Values for the tenant's extra lead fields, submitted from the conversation panel.
 *
 * Authorization runs against the lead, like notes: filling in what the customer
 * just told you is inbox work, while creating the field itself is administration.
 *
 * Only the payload shape is checked here. Which slugs exist and what each type
 * accepts depends on the tenant's own definitions, so that lives in
 * CustomFieldService::writeForLead(), which raises the same `values.<slug>` keys.
 */
class UpdateLeadCustomFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead && ($this->user()?->can('update', $lead) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'values' => ['present', 'array'],
            'values.*' => ['nullable'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function submittedValues(): array
    {
        $values = $this->validated('values');

        return is_array($values) ? $values : [];
    }
}
