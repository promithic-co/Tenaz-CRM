<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class MetaTemplateMediaService
{
    public function __construct(private readonly WhatsAppProviderFactory $providers) {}

    public function uploadImage(WhatsappTemplate $template, UploadedFile $image, UploadedFile $preview): void
    {
        $descriptor = $template->headerDescriptor();

        if (! $descriptor['requires_configured_image']) {
            throw ValidationException::withMessages([
                'header_image' => 'Este template não possui cabeçalho de imagem.',
            ]);
        }

        $instance = WhatsappInstance::query()
            ->where('tenant_id', (string) $template->tenant_id)
            ->whereKey($template->whatsapp_instance_id)
            ->first();

        if (! $instance) {
            throw ValidationException::withMessages([
                'header_image' => 'A instância WhatsApp vinculada ao template não foi encontrada.',
            ]);
        }

        $contents = $image->get();
        $mimeType = (string) $image->getMimeType();
        $previewContents = $preview->get();
        $previewMimeType = (string) $preview->getMimeType();

        if (! in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            throw ValidationException::withMessages([
                'header_image' => 'Envie uma imagem JPEG ou PNG.',
            ]);
        }

        if ($previewMimeType !== 'image/jpeg' || strlen($previewContents) > 300 * 1024) {
            throw ValidationException::withMessages([
                'header_image' => 'Não foi possível validar a pré-visualização da imagem.',
            ]);
        }

        $previewStream = $this->binaryStream($previewContents);

        try {
            $filename = Str::limit(basename($image->getClientOriginalName()), 255, '');
            $filename = $filename !== '' ? $filename : 'imagem-template.'.($image->guessExtension() ?: 'jpg');
            $mediaId = $this->providers
                ->makeProvider($instance)
                ->uploadMedia($contents, $filename, $mimeType);
            $uploadedAt = now();

            $template->update([
                'header_media_id' => $mediaId,
                'header_media_mime_type' => $mimeType,
                'header_media_filename' => $filename,
                'header_media_size_bytes' => strlen($contents),
                'header_media_uploaded_at' => $uploadedAt,
                'header_media_expires_at' => $uploadedAt->copy()->addDays(WhatsappTemplate::HEADER_MEDIA_TTL_DAYS),
                'header_media_preview' => $previewStream,
                'header_media_preview_mime_type' => $previewMimeType,
            ]);
        } finally {
            fclose($previewStream);
        }
    }

    /** @return resource */
    private function binaryStream(string $contents)
    {
        $stream = fopen('php://memory', 'r+b');

        if ($stream === false) {
            throw new RuntimeException('Could not open the template preview stream.');
        }

        $writtenBytes = fwrite($stream, $contents);

        if ($writtenBytes !== strlen($contents) || ! rewind($stream)) {
            fclose($stream);

            throw new RuntimeException('Could not prepare the template preview stream.');
        }

        return $stream;
    }
}
