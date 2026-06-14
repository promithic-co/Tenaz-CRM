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
     * Allowed interactive button types for an in-app authored template.
     * Media-header and dynamic/OTP/catalog buttons need extra infra and are
     * intentionally out of scope here.
     */
    public const BUTTON_TYPES = ['QUICK_REPLY', 'URL', 'PHONE_NUMBER'];

    /**
     * Submit an HSM template (text header, body, footer, buttons) to Meta and
     * persist the local record.
     *
     * @param  array{
     *     header_text?: ?string,
     *     header_example?: ?string,
     *     body: string,
     *     variable_examples?: array<int|string, mixed>,
     *     footer_text?: ?string,
     *     buttons?: array<int, array<string, mixed>>,
     * }  $spec
     *
     * @throws RequestException when Meta rejects the template
     */
    public function createAndStoreTemplate(
        WhatsappInstance $instance,
        string $tenantId,
        string $internalName,
        string $metaName,
        string $category,
        string $language,
        array $spec,
    ): WhatsappTemplate {
        $components = $this->buildComponents($spec);
        $metaResponse = $this->postTemplate($instance, $metaName, $category, $language, $components);

        $body = (string) ($spec['body'] ?? '');
        $headerText = $this->cleanText($spec['header_text'] ?? null);
        $footerText = $this->cleanText($spec['footer_text'] ?? null);
        $buttons = $this->normalizeButtons((array) ($spec['buttons'] ?? []));

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
            'header' => $headerText,
            'footer' => $footerText,
            'buttons_json' => $buttons !== [] ? $buttons : null,
            'components_json' => $components,
            'variables_count' => WhatsappTemplate::countVariablesIn($body),
        ]);
    }

    /**
     * Build the Meta `components` array from an authoring spec. Order matters
     * to Meta: HEADER, BODY, FOOTER, BUTTONS.
     *
     * @param  array<string, mixed>  $spec
     * @return array<int, array<string, mixed>>
     */
    public function buildComponents(array $spec): array
    {
        $components = [];

        $headerText = $this->cleanText($spec['header_text'] ?? null);
        if ($headerText !== null) {
            $header = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $headerText];
            $headerExample = $this->cleanText($spec['header_example'] ?? null);
            if (str_contains($headerText, '{{1}}') && $headerExample !== null) {
                $header['example'] = ['header_text' => [$headerExample]];
            }
            $components[] = $header;
        }

        $body = ['type' => 'BODY', 'text' => (string) ($spec['body'] ?? '')];
        $examples = array_filter((array) ($spec['variable_examples'] ?? []), fn (mixed $value): bool => filled($value));
        ksort($examples, SORT_NUMERIC);
        if ($examples !== []) {
            $body['example'] = ['body_text' => [array_values($examples)]];
        }
        $components[] = $body;

        $footerText = $this->cleanText($spec['footer_text'] ?? null);
        if ($footerText !== null) {
            $components[] = ['type' => 'FOOTER', 'text' => $footerText];
        }

        $buttons = $this->normalizeButtons((array) ($spec['buttons'] ?? []));
        if ($buttons !== []) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
        }

        return $components;
    }

    /**
     * Coerce a raw button list into valid Meta button objects, dropping any
     * that are missing their required fields.
     *
     * @param  array<int, mixed>  $buttons
     * @return array<int, array<string, string>>
     */
    public function normalizeButtons(array $buttons): array
    {
        $normalized = [];

        foreach ($buttons as $button) {
            if (! is_array($button)) {
                continue;
            }

            $type = strtoupper((string) ($button['type'] ?? ''));
            $text = trim((string) ($button['text'] ?? ''));

            if ($text === '' || ! in_array($type, self::BUTTON_TYPES, true)) {
                continue;
            }

            $entry = ['type' => $type, 'text' => $text];

            if ($type === 'URL') {
                $url = trim((string) ($button['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $entry['url'] = $url;
            }

            if ($type === 'PHONE_NUMBER') {
                $phone = trim((string) ($button['phone_number'] ?? ''));
                if ($phone === '') {
                    continue;
                }
                $entry['phone_number'] = $phone;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * POST a fully built components array to the Meta message_templates endpoint.
     *
     * @param  array<int, array<string, mixed>>  $components
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    private function postTemplate(
        WhatsappInstance $instance,
        string $name,
        string $category,
        string $language,
        array $components,
    ): array {
        $version = config('services.meta.graph_api_version', 'v23.0');
        $url = "https://graph.facebook.com/{$version}/{$instance->meta_waba_id}/message_templates";

        $response = Http::withToken((string) $instance->meta_access_token)
            ->timeout(60)
            ->post($url, [
                'name' => $name,
                'category' => strtoupper($category),
                'language' => $language,
                'components' => $components,
            ])
            ->throw();

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function cleanText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }
}
