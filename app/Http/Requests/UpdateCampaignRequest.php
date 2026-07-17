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

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = (string) $this->user()->tenantId;
        $campaign = $this->campaign();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_list_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('contact_lists', 'id')->where('tenant_id', $tenantId),
            ],
            'whatsapp_template_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('whatsapp_templates', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('tenant_id', $tenantId)
                        ->where('whatsapp_instance_id', $campaign?->whatsapp_instance_id ?? 0)
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
                ! $this->has('whatsapp_template_id')
                || $validator->errors()->has('whatsapp_template_id')
            ) {
                return;
            }

            $campaign = $this->campaign();
            $instance = $campaign?->whatsappInstance()->first();
            $template = WhatsappTemplate::query()->find($this->input('whatsapp_template_id'));

            if (
                ! $campaign instanceof Campaign
                || ! $instance instanceof WhatsappInstance
                || ! $template instanceof WhatsappTemplate
            ) {
                $validator->errors()->add('whatsapp_template_id', $this->incompatibleTemplateMessage());

                return;
            }

            $candidateCampaign = $campaign->replicate();
            $candidateCampaign->whatsapp_template_id = $template->getKey();

            if (app(CampaignTemplateCompatibility::class)->violations($candidateCampaign, $instance, $template) !== []) {
                $validator->errors()->add('whatsapp_template_id', $this->incompatibleTemplateMessage());
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome da campanha é obrigatório.',
            'contact_list_id.exists' => 'A lista de contatos selecionada não é válida.',
            'whatsapp_template_id.exists' => 'O template selecionado não é válido.',
            'scheduled_at.after' => 'O agendamento deve ser uma data futura.',
        ];
    }

    private function campaign(): ?Campaign
    {
        $campaign = $this->route('campanha');

        return $campaign instanceof Campaign ? $campaign : null;
    }

    private function incompatibleTemplateMessage(): string
    {
        return 'O template selecionado não é compatível com a instância WhatsApp.';
    }
}
