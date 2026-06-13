<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendConversationMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead && $this->user()->can('update', $lead);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'content' => ['required_without:file', 'nullable', 'string', 'max:4096'],
            'file' => ['required_without:content', 'nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'content.required_without' => 'Envie uma mensagem ou um arquivo.',
            'file.required_without' => 'Envie uma mensagem ou um arquivo.',
            'file.max' => 'O arquivo não pode exceder 10 MB.',
            'file.mimes' => 'Apenas JPG, PNG e PDF são suportados.',
        ];
    }
}
