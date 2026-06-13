<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappInstance;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class MetaTemplateService
{
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
