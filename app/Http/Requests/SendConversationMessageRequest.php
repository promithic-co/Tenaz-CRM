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
            'content' => ['required_without_all:file,template_id', 'nullable', 'string', 'max:4096'],
            'file' => ['required_without_all:content,template_id', 'nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
            'template_id' => ['nullable', 'integer', 'prohibits:file,content'],
            'template_parameters' => ['nullable', 'array'],
            'template_parameters.header' => ['nullable', 'array'],
            'template_parameters.body' => ['nullable', 'array'],
            'template_parameters.buttons' => ['nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'content.required_without_all' => 'Envie uma mensagem, um arquivo ou um template.',
            'file.required_without_all' => 'Envie uma mensagem, um arquivo ou um template.',
            'file.max' => 'O arquivo não pode exceder 10 MB.',
            'file.mimes' => 'Apenas JPG, PNG e PDF são suportados.',
            'template_id.prohibits' => 'Não é possível enviar um template junto com texto ou arquivo.',
        ];
    }
}
