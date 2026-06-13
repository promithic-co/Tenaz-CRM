<?php

namespace App\Ai\DTOs;

use App\Enums\MediaType;

/**
 * Representa um arquivo de mídia já baixado e pronto para processamento pela IA.
 *
 * @phpstan-type MediaMeta array{
 *     type: string,
 *     mime_type: string,
 *     local_path: string,
 *     original_hash: string,
 *     caption: string|null,
 *     duration_secs: int|null,
 *     filename: string|null,
 *     size_bytes: int,
 * }
 */
readonly class MediaContext
{
    public function __construct(
        public MediaType $type,
        public string $localPath,
        public string $mimeType,
        public string $originalHash,
        public int $sizeBytes,
        public ?string $caption = null,
        public ?int $durationSecs = null,
        public ?string $filename = null,
    ) {}

    public function extension(): string
    {
        return match ($this->mimeType) {
            'audio/ogg', 'audio/ogg; codecs=opus' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4', 'audio/m4a' => 'm4a',
            'audio/webm' => 'webm',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    /**
     * Reidrata a partir do array retornado por toArray().
     *
     * @param  MediaMeta  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: MediaType::from($data['type']),
            localPath: $data['local_path'],
            mimeType: $data['mime_type'],
            originalHash: $data['original_hash'],
            sizeBytes: $data['size_bytes'],
            caption: $data['caption'] ?? null,
            durationSecs: $data['duration_secs'] ?? null,
            filename: $data['filename'] ?? null,
        );
    }

    /**
     * Dados serializados para armazenar no campo `attachments` da mensagem.
     *
     * @return MediaMeta
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'mime_type' => $this->mimeType,
            'local_path' => $this->localPath,
            'original_hash' => $this->originalHash,
            'caption' => $this->caption,
            'duration_secs' => $this->durationSecs,
            'filename' => $this->filename,
            'size_bytes' => $this->sizeBytes,
        ];
    }
}
