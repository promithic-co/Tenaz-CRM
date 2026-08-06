<?php

namespace App\Services;

use App\Models\WhatsappTemplate;

final class WhatsappTemplateMediaPresenter
{
    /**
     * @return array{
     *     format: string|null,
     *     state: string,
     *     requires_image: bool,
     *     supported: bool,
     *     sendable: bool,
     *     unavailable_reason: string|null,
     *     preview_url: string|null,
     *     filename: string|null,
     *     size_bytes: int|null,
     *     uploaded_at: string|null,
     *     expires_at: string|null
     * }
     */
    public function present(WhatsappTemplate $template): array
    {
        $descriptor = $template->headerDescriptor();
        $state = $template->headerMediaState();
        $supported = $descriptor['supported_for_send'];
        $sendable = $supported && $state !== 'missing' && $state !== 'expired';

        return [
            'format' => $descriptor['format'],
            'state' => $state,
            'requires_image' => $descriptor['requires_configured_image'],
            'supported' => $supported,
            'sendable' => $sendable,
            'unavailable_reason' => $this->unavailableReason($state, $descriptor['format']),
            'preview_url' => $template->getRawOriginal('header_media_preview') !== null
                ? route('templates.media-preview', [
                    'template' => $template->getKey(),
                    'v' => $template->header_media_uploaded_at?->getTimestamp(),
                ])
                : null,
            'filename' => $template->header_media_filename,
            'size_bytes' => $template->header_media_size_bytes === null
                ? null
                : (int) $template->header_media_size_bytes,
            'uploaded_at' => $template->header_media_uploaded_at?->toIso8601String(),
            'expires_at' => $template->header_media_expires_at?->toIso8601String(),
        ];
    }

    private function unavailableReason(string $state, ?string $format): ?string
    {
        return match ($state) {
            'missing' => 'Configure a imagem deste template em Templates WhatsApp antes de enviar.',
            'expired' => 'A imagem deste template expirou. Faça um novo upload em Templates WhatsApp.',
            'unsupported' => "Cabeçalho {$format} ainda não é suportado pelo Tenaz.",
            default => null,
        };
    }
}
