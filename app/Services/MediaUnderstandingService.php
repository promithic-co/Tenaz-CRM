<?php

namespace App\Services;

use App\Ai\DTOs\MediaContext;
use App\Enums\MediaType;
use Illuminate\Support\Facades\Log;

/**
 * Orquestra a compreensão de diferentes tipos de mídia.
 * Retorna uma string pronta para ser injetada no prompt do agente.
 */
class MediaUnderstandingService
{
    public function __construct(
        private readonly AudioTranscriptionService $transcription,
        private readonly ImageVisionService $vision,
    ) {}

    /**
     * Processa a mídia e retorna texto contextualizado para o agente.
     * Retorna null se o tipo não for suportado ou o processamento falhar.
     */
    public function process(MediaContext $media): ?string
    {
        if (!$media->type->isProcessable()) {
            Log::info('media_understanding.skipped', ['type' => $media->type->value]);
            return null;
        }

        return match ($media->type) {
            MediaType::Audio    => $this->processAudio($media),
            MediaType::Image    => $this->processImage($media),
            MediaType::Document => $this->processDocument($media),
            default             => null,
        };
    }

    /**
     * Mensagem de fallback quando a mídia foi recebida mas não pôde ser processada.
     */
    public function fallbackMessage(MediaContext $media): string
    {
        return match ($media->type) {
            MediaType::Audio    => '[O cliente enviou um áudio, mas não foi possível transcrever. Peça para repetir por texto.]',
            MediaType::Image    => '[O cliente enviou uma imagem, mas não foi possível analisar. Peça para descrever o que enviou.]',
            MediaType::Document => '[O cliente enviou um documento, mas não foi possível processar. Peça para informar o tipo do documento.]',
            default             => '[O cliente enviou uma mídia não suportada.]',
        };
    }

    private function processAudio(MediaContext $media): ?string
    {
        $transcription = $this->transcription->transcribe($media);

        if (!$transcription) {
            return null;
        }

        $duration = $media->durationSecs ? " ({$media->durationSecs}s)" : '';

        return "[Áudio{$duration} transcrito]: \"{$transcription}\"";
    }

    private function processImage(MediaContext $media): ?string
    {
        $description = $this->vision->describe($media);

        if (!$description) {
            return null;
        }

        $result = "[Imagem recebida]: {$description}";

        if ($media->caption) {
            $result .= " | Legenda do cliente: \"{$media->caption}\"";
        }

        return $result;
    }

    private function processDocument(MediaContext $media): ?string
    {
        $description = $this->vision->describe($media);

        if (!$description) {
            return null;
        }

        $name   = $media->filename ?? 'documento';
        $result = "[Documento recebido — {$name}]: {$description}";

        if ($media->caption) {
            $result .= " | Observação do cliente: \"{$media->caption}\"";
        }

        return $result;
    }
}
