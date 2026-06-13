<?php

namespace App\Services;

use App\Ai\DTOs\MediaContext;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Transcreve áudios para texto usando Whisper (OpenAI ou Groq).
 *
 * Providers suportados: openai, groq
 */
class AudioTranscriptionService
{
    /** @var array<string, array{url: string, key_env: string, default_model: string}> */
    private const PROVIDERS = [
        'openai' => [
            'url'           => 'https://api.openai.com/v1/audio/transcriptions',
            'key_config'    => 'ai.providers.openai.key',
            'default_model' => 'whisper-1',
        ],
        'groq' => [
            'url'           => 'https://api.groq.com/openai/v1/audio/transcriptions',
            'key_config'    => 'ai.providers.groq.key',
            'default_model' => 'whisper-large-v3-turbo',
        ],
    ];

    public function transcribe(MediaContext $media): ?string
    {
        $provider = AppSetting::get('transcription_provider', 'openai');
        $model    = AppSetting::get('transcription_model', 'whisper-1');

        if (!isset(self::PROVIDERS[$provider])) {
            Log::warning('audio_transcription.unsupported_provider', ['provider' => $provider]);
            $provider = 'openai';
        }

        $config = self::PROVIDERS[$provider];
        $apiKey = config($config['key_config']);

        if (!$apiKey) {
            Log::error('audio_transcription.missing_api_key', ['provider' => $provider]);
            return null;
        }

        if (!file_exists($media->localPath)) {
            Log::error('audio_transcription.file_not_found', ['path' => $media->localPath]);
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->attach('file', fopen($media->localPath, 'r'), 'audio.' . $media->extension())
                ->post($config['url'], [
                    'model'    => $model ?: $config['default_model'],
                    'language' => 'pt',
                    'response_format' => 'text',
                ]);

            if (!$response->successful()) {
                Log::warning('audio_transcription.api_error', [
                    'provider' => $provider,
                    'status'   => $response->status(),
                    'body'     => $response->body(),
                ]);
                return null;
            }

            $text = trim($response->body());

            Log::info('audio_transcription.success', [
                'provider'   => $provider,
                'model'      => $model,
                'chars'      => strlen($text),
                'duration_s' => $media->durationSecs,
            ]);

            return $text ?: null;

        } catch (\Throwable $e) {
            Log::error('audio_transcription.exception', [
                'provider' => $provider,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }
}
