<?php

namespace App\Services\WhatsApp;

use InvalidArgumentException;

/**
 * Pure, framework-free renderer for Meta WhatsApp message templates. Ported from
 * the Estalo reference implementation (production-tested).
 *
 * Four responsibilities:
 *   - describe(): field manifest driving the dynamic parameter form in the UI, and
 *     a `supported` flag flagging components that cannot be sent.
 *   - preview(): render with Meta example / placeholder fallback (never throws on
 *     a missing parameter — used for live previews and campaign timeline snapshots).
 *   - render(): strict immutable snapshot of the exact text a customer receives.
 *   - payload(): the Cloud API `components` array for the send call.
 *
 * Parameters are shaped as `['header' => ['1' => '…', 'media' => '…'], 'body' => ['1' => '…'],
 * 'buttons' => ['0' => '…']]`.
 */
class WhatsappTemplateRenderer
{
    /**
     * @param  array<int, mixed>  $components
     * @return array{supported: bool, unsupported_reason: ?string, fields: list<array<string, mixed>>}
     */
    public function describe(array $components): array
    {
        $fields = [];
        $unsupported = [];

        foreach ($components as $component) {
            if (! is_array($component)) {
                $unsupported[] = 'Componente inválido.';

                continue;
            }

            $type = strtoupper((string) ($component['type'] ?? ''));

            if ($type === 'HEADER') {
                $this->describeHeader($component, $fields, $unsupported);

                continue;
            }

            if ($type === 'BODY') {
                $this->describeTextFields($component, 'body', 'Corpo', $fields, $unsupported);

                continue;
            }

            if ($type === 'FOOTER') {
                if ($this->placeholderKeys((string) ($component['text'] ?? '')) !== []) {
                    $unsupported[] = 'FOOTER com parâmetros não é suportado.';
                }

                continue;
            }

            if ($type === 'BUTTONS') {
                $this->describeButtons($component, $fields, $unsupported);

                continue;
            }

            $unsupported[] = "Componente {$type} não é suportado para envio.";
        }

        return [
            'supported' => $unsupported === [],
            'unsupported_reason' => $unsupported === [] ? null : implode(' ', array_unique($unsupported)),
            'fields' => $fields,
        ];
    }

    /**
     * Render a preview, falling back to Meta examples and then placeholder labels.
     *
     * @param  array<int, mixed>  $components
     * @param  array<string, mixed>  $parameters
     * @return array{header: ?array<string, mixed>, body: ?string, footer: ?string, buttons: list<array<string, mixed>>, text: string}
     */
    public function preview(array $components, array $parameters = []): array
    {
        return $this->renderComponents($components, $parameters, true);
    }

    /**
     * Render the immutable snapshot for a message that is about to be sent.
     *
     * @param  array<int, mixed>  $components
     * @param  array<string, mixed>  $parameters
     * @return array{header: ?array<string, mixed>, body: ?string, footer: ?string, buttons: list<array<string, mixed>>, text: string}
     */
    public function render(array $components, array $parameters): array
    {
        return $this->renderComponents($components, $parameters, false);
    }

    /**
     * @param  array<int, mixed>  $components
     * @param  array<string, mixed>  $parameters
     * @return list<array<string, mixed>>
     */
    public function payload(array $components, array $parameters, string $templateId): array
    {
        $this->assertSupported($components);
        $payload = [];

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $type = strtoupper((string) ($component['type'] ?? ''));

            if ($type === 'HEADER') {
                $header = $this->headerPayload($component, $parameters);
                if ($header !== null) {
                    $payload[] = $header;
                }

                continue;
            }

            if ($type === 'BODY') {
                $positions = $this->numberedPositions((string) ($component['text'] ?? ''));
                if ($positions !== []) {
                    $payload[] = [
                        'type' => 'body',
                        'parameters' => array_map(fn (int $position): array => [
                            'type' => 'text',
                            'text' => $this->requiredValue($parameters, 'body', (string) $position),
                        ], $positions),
                    ];
                }

                continue;
            }

            if ($type === 'BUTTONS') {
                foreach ($component['buttons'] ?? [] as $index => $button) {
                    if (! is_array($button)) {
                        continue;
                    }

                    $buttonType = strtoupper((string) ($button['type'] ?? ''));

                    if ($buttonType === 'QUICK_REPLY') {
                        $payload[] = [
                            'type' => 'button',
                            'sub_type' => 'quick_reply',
                            'index' => (string) $index,
                            'parameters' => [[
                                'type' => 'payload',
                                'payload' => "template:{$templateId}:button:{$index}",
                            ]],
                        ];

                        continue;
                    }

                    if ($buttonType === 'URL' && $this->numberedPositions((string) ($button['url'] ?? '')) !== []) {
                        $payload[] = [
                            'type' => 'button',
                            'sub_type' => 'url',
                            'index' => (string) $index,
                            'parameters' => [[
                                'type' => 'text',
                                'text' => $this->requiredValue($parameters, 'buttons', (string) $index),
                            ]],
                        ];
                    }
                }
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $component
     * @param  list<array<string, mixed>>  $fields
     * @param  list<string>  $unsupported
     */
    private function describeHeader(array $component, array &$fields, array &$unsupported): void
    {
        $format = strtoupper((string) ($component['format'] ?? 'TEXT'));

        if ($format === 'TEXT') {
            $this->describeTextFields($component, 'header', 'Cabeçalho', $fields, $unsupported);

            return;
        }

        if (in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            $labels = [
                'IMAGE' => 'Imagem do cabeçalho',
                'VIDEO' => 'Vídeo do cabeçalho',
                'DOCUMENT' => 'Documento do cabeçalho',
            ];

            $fields[] = [
                'path' => 'header.media',
                'component' => 'header',
                'type' => strtolower($format),
                'label' => $labels[$format],
                'example' => $this->firstString($component['example']['header_handle'] ?? null),
                'required' => true,
            ];

            return;
        }

        $unsupported[] = "HEADER {$format} não é suportado para envio.";
    }

    /**
     * @param  array<string, mixed>  $component
     * @param  list<array<string, mixed>>  $fields
     * @param  list<string>  $unsupported
     */
    private function describeTextFields(
        array $component,
        string $section,
        string $label,
        array &$fields,
        array &$unsupported,
    ): void {
        $text = (string) ($component['text'] ?? '');
        $keys = $this->placeholderKeys($text);

        foreach ($keys as $key) {
            if (! ctype_digit($key)) {
                $unsupported[] = "{$label} com parâmetro nomeado {{$key}} não é suportado.";

                continue;
            }

            $position = (int) $key;
            $fields[] = [
                'path' => "{$section}.{$position}",
                'component' => $section,
                'type' => 'text',
                'label' => "{$label} — variável {{$position}}",
                'example' => $this->textExample($component, $section, $position),
                'required' => true,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $component
     * @param  list<array<string, mixed>>  $fields
     * @param  list<string>  $unsupported
     */
    private function describeButtons(array $component, array &$fields, array &$unsupported): void
    {
        foreach ($component['buttons'] ?? [] as $index => $button) {
            if (! is_array($button)) {
                $unsupported[] = 'BUTTON inválido.';

                continue;
            }

            $type = strtoupper((string) ($button['type'] ?? ''));

            if (in_array($type, ['QUICK_REPLY', 'PHONE_NUMBER'], true)) {
                continue;
            }

            if ($type === 'URL') {
                $positions = $this->numberedPositions((string) ($button['url'] ?? ''));

                if (count($positions) > 1) {
                    $unsupported[] = "BUTTON URL {$index} possui mais de um parâmetro.";

                    continue;
                }

                if ($positions !== []) {
                    $fields[] = [
                        'path' => "buttons.{$index}",
                        'component' => 'button',
                        'type' => 'text',
                        'label' => 'Complemento do link — '.(string) ($button['text'] ?? $index),
                        'example' => $this->firstString($button['example'] ?? null),
                        'required' => true,
                    ];
                }

                continue;
            }

            $unsupported[] = "BUTTON {$type} não é suportado para envio.";
        }
    }

    /**
     * @param  array<int, mixed>  $components
     * @param  array<string, mixed>  $parameters
     * @return array{header: ?array<string, mixed>, body: ?string, footer: ?string, buttons: list<array<string, mixed>>, text: string}
     */
    private function renderComponents(array $components, array $parameters, bool $useExamples): array
    {
        $this->assertSupported($components);

        $header = null;
        $body = null;
        $footer = null;
        $buttons = [];

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $type = strtoupper((string) ($component['type'] ?? ''));

            if ($type === 'HEADER') {
                $format = strtoupper((string) ($component['format'] ?? 'TEXT'));

                if ($format === 'TEXT') {
                    $header = [
                        'format' => 'TEXT',
                        'text' => $this->renderText((string) ($component['text'] ?? ''), $component, 'header', $parameters, $useExamples),
                    ];
                } else {
                    $mediaUrl = $this->value($parameters, 'header', 'media');

                    if ($mediaUrl === null && $useExamples) {
                        $mediaUrl = $this->firstString($component['example']['header_handle'] ?? null);
                    }

                    if ($mediaUrl === null) {
                        throw new InvalidArgumentException('Parâmetro obrigatório ausente: header.media.');
                    }

                    $header = ['format' => $format, 'media_url' => $mediaUrl];
                }

                continue;
            }

            if ($type === 'BODY') {
                $body = $this->renderText((string) ($component['text'] ?? ''), $component, 'body', $parameters, $useExamples);

                continue;
            }

            if ($type === 'FOOTER') {
                $footer = (string) ($component['text'] ?? '');

                continue;
            }

            if ($type === 'BUTTONS') {
                foreach ($component['buttons'] ?? [] as $index => $button) {
                    if (! is_array($button)) {
                        continue;
                    }

                    $buttonType = strtoupper((string) ($button['type'] ?? ''));
                    $rendered = [
                        'type' => $buttonType,
                        'text' => (string) ($button['text'] ?? ''),
                    ];

                    if ($buttonType === 'URL') {
                        $url = (string) ($button['url'] ?? '');
                        $positions = $this->numberedPositions($url);

                        if ($positions !== []) {
                            $value = $this->value($parameters, 'buttons', (string) $index);

                            if ($value === null && $useExamples) {
                                $value = $this->firstString($button['example'] ?? null) ?? '{{1}}';
                            }

                            if ($value === null) {
                                throw new InvalidArgumentException("Parâmetro obrigatório ausente: buttons.{$index}.");
                            }

                            $url = $this->replacePositions($url, fn (): string => $value);
                        }

                        $rendered['url'] = $url;
                    }

                    if ($buttonType === 'PHONE_NUMBER') {
                        $rendered['phone_number'] = (string) ($button['phone_number'] ?? '');
                    }

                    $buttons[] = $rendered;
                }
            }
        }

        $textParts = [];
        if (($header['format'] ?? null) === 'TEXT' && filled($header['text'] ?? null)) {
            $textParts[] = $header['text'];
        }
        if (filled($body)) {
            $textParts[] = $body;
        }
        if (filled($footer)) {
            $textParts[] = $footer;
        }
        foreach ($buttons as $button) {
            if (filled($button['text'] ?? null)) {
                $textParts[] = '[Botão] '.$button['text'];
            }
        }

        return [
            'header' => $header,
            'body' => $body,
            'footer' => $footer,
            'buttons' => $buttons,
            'text' => implode("\n", $textParts),
        ];
    }

    /**
     * @param  array<string, mixed>  $component
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>|null
     */
    private function headerPayload(array $component, array $parameters): ?array
    {
        $format = strtoupper((string) ($component['format'] ?? 'TEXT'));

        if ($format === 'TEXT') {
            $positions = $this->numberedPositions((string) ($component['text'] ?? ''));

            return $positions === [] ? null : [
                'type' => 'header',
                'parameters' => array_map(fn (int $position): array => [
                    'type' => 'text',
                    'text' => $this->requiredValue($parameters, 'header', (string) $position),
                ], $positions),
            ];
        }

        $type = strtolower($format);
        $url = $this->requiredValue($parameters, 'header', 'media');

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new InvalidArgumentException('O parâmetro header.media deve ser uma URL HTTP válida.');
        }

        return [
            'type' => 'header',
            'parameters' => [[
                'type' => $type,
                $type => ['link' => $url],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $component
     * @param  array<string, mixed>  $parameters
     */
    private function renderText(
        string $text,
        array $component,
        string $section,
        array $parameters,
        bool $useExamples,
    ): string {
        return $this->replacePositions($text, function (int $position) use ($component, $section, $parameters, $useExamples): string {
            $value = $this->value($parameters, $section, (string) $position);

            if ($value !== null) {
                return $value;
            }

            if ($useExamples) {
                return $this->textExample($component, $section, $position) ?? "{{$position}}";
            }

            throw new InvalidArgumentException("Parâmetro obrigatório ausente: {$section}.{$position}.");
        });
    }

    /**
     * @param  array<int, mixed>  $components
     */
    private function assertSupported(array $components): void
    {
        $description = $this->describe($components);

        if (! $description['supported']) {
            throw new InvalidArgumentException((string) $description['unsupported_reason']);
        }
    }

    /**
     * @return list<string>
     */
    private function placeholderKeys(string $text): array
    {
        preg_match_all('/{{\s*([^{}]+?)\s*}}/', $text, $matches);

        return array_values(array_unique(array_map('trim', $matches[1] ?? [])));
    }

    /**
     * @return list<int>
     */
    private function numberedPositions(string $text): array
    {
        $positions = array_map('intval', array_filter(
            $this->placeholderKeys($text),
            fn (string $key): bool => ctype_digit($key),
        ));
        sort($positions);

        return array_values(array_unique($positions));
    }

    private function replacePositions(string $text, callable $value): string
    {
        return (string) preg_replace_callback(
            '/{{\s*(\d+)\s*}}/',
            fn (array $match): string => $value((int) $match[1]),
            $text,
        );
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function requiredValue(array $parameters, string $section, string $key): string
    {
        $value = $this->value($parameters, $section, $key);

        if ($value === null) {
            throw new InvalidArgumentException("Parâmetro obrigatório ausente: {$section}.{$key}.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function value(array $parameters, string $section, string $key): ?string
    {
        $sectionValues = $parameters[$section] ?? null;
        $value = is_array($sectionValues) ? ($sectionValues[$key] ?? null) : null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $component
     */
    private function textExample(array $component, string $section, int $position): ?string
    {
        $examples = $section === 'body'
            ? ($component['example']['body_text'][0] ?? null)
            : ($component['example']['header_text'] ?? null);

        if (! is_array($examples)) {
            return null;
        }

        $example = $examples[$position - 1] ?? null;

        return is_string($example) && trim($example) !== '' ? $example : null;
    }

    private function firstString(mixed $value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach ($value as $item) {
            $string = $this->firstString($item);

            if ($string !== null) {
                return $string;
            }
        }

        return null;
    }
}
