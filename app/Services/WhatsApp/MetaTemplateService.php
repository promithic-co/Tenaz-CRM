<?php

namespace App\Services\WhatsApp;

use App\Enums\TemplateKind;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class MetaTemplateService
{
    /**
     * Submit a BODY-only HSM template to Meta and persist the local record.
     *
     * @param  array<int|string, mixed>  $variableExamples  raw {{n}} => example map; filtered and ordered here
     *
     * @throws RequestException when Meta rejects the template
     */
    public function createAndStoreBodyTemplate(
        WhatsappInstance $instance,
        string $tenantId,
        string $internalName,
        string $metaName,
        string $category,
        string $language,
        string $body,
        array $variableExamples = [],
    ): WhatsappTemplate {
        $examples = array_filter($variableExamples, fn (mixed $value): bool => filled($value));
        ksort($examples, SORT_NUMERIC);

        $created = $this->createBodyTemplate($instance, $metaName, $category, $language, $body, $examples);
        $metaResponse = is_array($created['response']) ? $created['response'] : [];

        return WhatsappTemplate::create([
            'tenant_id' => $tenantId,
            'kind' => TemplateKind::MetaHsm->value,
            'whatsapp_instance_id' => $instance->id,
            'name' => $internalName,
            'meta_template_id' => $metaResponse['id'] ?? null,
            'meta_template_name' => $metaName,
            'meta_waba_id' => $instance->meta_waba_id,
            'status' => strtoupper((string) ($metaResponse['status'] ?? 'PENDING')),
            'category' => $category,
            'language' => $language,
            'body' => $body,
            'components_json' => $created['components'],
            'variables_count' => WhatsappTemplate::countVariablesIn($body),
        ]);
    }

    /**
     * @param  array<string, string>  $variableExamples
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function createBodyTemplate(
        WhatsappInstance $instance,
        string $name,
        string $category,
        string $language,
        string $body,
        array $variableExamples = [],
    ): array {
        $version = config('services.meta.graph_api_version', 'v23.0');
        $url = "https://graph.facebook.com/{$version}/{$instance->meta_waba_id}/message_templates";

        $components = [[
            'type' => 'BODY',
            'text' => $body,
        ]];

        if ($variableExamples !== []) {
            $components[0]['example'] = [
                'body_text' => [array_values($variableExamples)],
            ];
        }

        $payload = [
            'name' => $name,
            'category' => strtoupper($category),
            'language' => $language,
            'components' => $components,
        ];

        $response = Http::withToken((string) $instance->meta_access_token)
            ->timeout(60)
            ->post($url, $payload)
            ->throw();

        return [
            'response' => $response->json(),
            'components' => $components,
        ];
    }
}
