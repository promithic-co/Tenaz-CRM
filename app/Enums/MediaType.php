<?php

namespace App\Enums;

enum MediaType: string
{
    case Audio    = 'audio';
    case Image    = 'image';
    case Document = 'document';
    case Video    = 'video';
    case Sticker  = 'sticker';
    case Unknown  = 'unknown';

    public function isProcessable(): bool
    {
        return match ($this) {
            self::Audio, self::Image, self::Document => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Audio    => 'Áudio',
            self::Image    => 'Imagem',
            self::Document => 'Documento',
            self::Video    => 'Vídeo',
            self::Sticker  => 'Figurinha',
            self::Unknown  => 'Mídia',
        };
    }
}
