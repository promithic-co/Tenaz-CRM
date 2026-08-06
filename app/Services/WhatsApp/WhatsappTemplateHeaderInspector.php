<?php

namespace App\Services\WhatsApp;

final class WhatsappTemplateHeaderInspector
{
    /**
     * @param  array<int, mixed>  $components
     * @return array{has_header: bool, format: string|null, requires_configured_image: bool, supported_for_send: bool}
     */
    public static function inspect(array $components): array
    {
        foreach ($components as $component) {
            if (! is_array($component) || strtoupper((string) ($component['type'] ?? '')) !== 'HEADER') {
                continue;
            }

            $format = strtoupper(trim((string) ($component['format'] ?? 'TEXT')));

            return [
                'has_header' => true,
                'format' => $format,
                'requires_configured_image' => $format === 'IMAGE',
                'supported_for_send' => in_array($format, ['TEXT', 'IMAGE'], true),
            ];
        }

        return [
            'has_header' => false,
            'format' => null,
            'requires_configured_image' => false,
            'supported_for_send' => true,
        ];
    }
}
