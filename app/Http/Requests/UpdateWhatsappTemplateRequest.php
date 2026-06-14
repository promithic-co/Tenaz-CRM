<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsappTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('template');

        return $template && (string) $template->tenant_id === (string) $this->user()->tenantId;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'whatsapp_instance_id' => ['prohibited'],
            'body' => ['prohibited'],
            'category' => ['prohibited'],
            'language' => ['prohibited'],
            'status' => ['prohibited'],
            'meta_template_id' => ['prohibited'],
            'meta_waba_id' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'whatsapp_instance_id.prohibited' => 'A instância do template é definida na criação e não pode ser alterada.',
            'body.prohibited' => 'O corpo sincronizado da Meta nao pode ser alterado neste fluxo.',
            'category.prohibited' => 'A categoria sincronizada da Meta nao pode ser alterada neste fluxo.',
            'language.prohibited' => 'O idioma sincronizado da Meta nao pode ser alterado neste fluxo.',
            'status.prohibited' => 'O status do template e definido pela Meta e nao pode ser informado manualmente.',
            'meta_template_id.prohibited' => 'O ID do template e gerado pela Meta e nao pode ser informado manualmente.',
            'meta_waba_id.prohibited' => 'O WABA ID vem da instancia selecionada e nao pode ser informado manualmente.',
        ];
    }
}
