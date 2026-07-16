<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MetaEmbeddedSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:1024'],
            'waba_id' => ['required', 'string', 'max:64'],
            'phone_number_id' => ['nullable', 'string', 'max:64'],
            'business_id' => ['nullable', 'string', 'max:64'],
            'finish_type' => ['required', 'string', 'in:FINISH,FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING'],
        ];
    }
}
