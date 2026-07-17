<?php

namespace App\Http\Requests;

use App\Enums\TemplateKind;
use App\Models\Campaign;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\CampaignTemplateCompatibility;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = (string) $this->user()->tenantId;

        return [
            'name' => ['required', 'string', 'max:255'],
            'whatsapp_instance_id' => [
                'required',
                'integer',
                Rule::exists('whatsapp_instances', 'id')->where('tenant_id', $tenantId),
            ],
            'contact_list_id' => [
                'required',
                'integer',
                Rule::exists('contact_lists', 'id')->where('tenant_id', $tenantId),
            ],
            'whatsapp_template_id' => [
                'required',
                'integer',
                Rule::exists('whatsapp_templates', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('tenant_id', $tenantId)
                        ->where('whatsapp_instance_id', $this->input('whatsapp_instance_id'))
                        ->where('kind', TemplateKind::MetaHsm->value)
                        ->where('status', 'APPROVED')
                        ->whereNotNull('meta_template_name')
                        ->where('meta_template_name', '!=', '')
                        ->whereNotNull('language')
                        ->where('language', '!=', '')
                        ->whereNotNull('meta_waba_id')
                        ->where('meta_waba_id', '!=', ''),
                ),
            ],
            'template_params_mapping' => ['nullable', 'array'],
            'template_params_mapping.*' => ['string'],
            'daily_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'delay_between_ms' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'error_threshold_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (
                $validator->errors()->has('whatsapp_instance_id')
                || $validator->errors()->has('whatsapp_template_id')
            ) {
                return;
            }

            $instance = WhatsappInstance::query()->find($this->input('whatsapp_instance_id'));
            $template = WhatsappTemplate::query()->find($this->input('whatsapp_template_id'));

            if (! $instance instanceof WhatsappInstance || ! $template instanceof WhatsappTemplate) {
                $validator->errors()->add('whatsapp_template_id', $this->incompatibleTemplateMessage());

                return;
            }

            $campaign = new Campaign([
                'tenant_id' => (string) $this->user()->tenantId,
                'whatsapp_instance_id' => $instance->getKey(),
                'whatsapp_template_id' => $template->getKey(),
            ]);

            if (app(CampaignTemplateCompatibility::class)->violations($campaign, $instance, $template) !== []) {
                $validator->errors()->add('whatsapp_template_id', $this->incompatibleTemplateMessage());
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome da campanha é obrigatório.',
            'whatsapp_instance_id.required' => 'A instância WhatsApp é obrigatória.',
            'whatsapp_instance_id.exists' => 'A instância selecionada não é válida.',
            'contact_list_id.required' => 'A lista de contatos é obrigatória.',
            'contact_list_id.exists' => 'A lista de contatos selecionada não é válida.',
            'whatsapp_template_id.required' => 'O template é obrigatório.',
            'whatsapp_template_id.exists' => 'O template selecionado não é válido.',
            'scheduled_at.after' => 'O agendamento deve ser uma data futura.',
        ];
    }

    private function incompatibleTemplateMessage(): string
    {
        return 'O template selecionado não é compatível com a instância WhatsApp.';
    }
}
