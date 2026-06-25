<?php

namespace App\Services;

use App\Ai\DTOs\MediaContext;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Analisa imagens e documentos (PDF) usando visão computacional via LLM.
 *
 * Providers suportados: openai, anthropic, gemini
 */
class ImageVisionService
{
    private const VISION_PROMPT = 'Descreva objetivamente o que está nesta imagem. '
        .'Se for um documento (RG, CNH, comprovante de residência, extrato bancário, carteira de trabalho), '
        .'identifique o tipo de documento, os dados principais visíveis e se está legível. '
        .'Se for uma foto de pessoa, descreva brevemente o contexto. '
        .'Seja objetivo e conciso (máximo 3 frases).';

    /** Hard ceiling on media size before base64-encoding into the request (MEM-8 memory guard). */
    private const MAX_MEDIA_BYTES = 20 * 1024 * 1024;

    /** @var array<string, array{key_config: string, default_model: string}> */
    private const PROVIDERS = [
        'openai' => ['key_config' => 'ai.providers.openai.key',    'default_model' => 'gpt-4o'],
        'anthropic' => ['key_config' => 'ai.providers.anthropic.key', 'default_model' => 'claude-3-5-haiku-20241022'],
        'gemini' => ['key_config' => 'ai.providers.gemini.key',    'default_model' => 'gemini-2.0-flash'],
    ];

    public function describe(MediaContext $media): ?string
    {
        $provider = AppSetting::get('vision_provider', 'openai');
        $model = AppSetting::get('vision_model', 'gpt-4o');

        if (! isset(self::PROVIDERS[$provider])) {
            Log::warning('image_vision.unsupported_provider', ['provider' => $provider]);
            $provider = 'openai';
        }

        $config = self::PROVIDERS[$provider];
        $apiKey = config($config['key_config']);

        if (! $apiKey) {
            Log::error('image_vision.missing_api_key', ['provider' => $provider]);

            return null;
        }

        if (! file_exists($media->localPath)) {
            Log::error('image_vision.file_not_found', ['path' => $media->localPath]);

            return null;
        }

        $bytes = filesize($media->localPath);

        if ($bytes === false || $bytes > self::MAX_MEDIA_BYTES) {
            Log::warning('image_vision.file_too_large', [
                'path' => $media->localPath,
                'bytes' => $bytes,
                'limit' => self::MAX_MEDIA_BYTES,
            ]);

            return null;
        }

        $model = $model ?: $config['default_model'];

        try {
            $base64 = base64_encode(file_get_contents($media->localPath));
            $mimeType = $media->mimeType;

            $description = match ($provider) {
                'openai' => $this->describeWithOpenAI($apiKey, $model, $base64, $mimeType),
                'anthropic' => $this->describeWithAnthropic($apiKey, $model, $base64, $mimeType),
                'gemini' => $this->describeWithGemini($apiKey, $model, $base64, $mimeType),
                default => null,
            };

            Log::info('image_vision.success', [
                'provider' => $provider,
                'model' => $model,
                'chars' => $description ? strlen($description) : 0,
            ]);

            return $description;

        } catch (\Throwable $e) {
            Log::error('image_vision.exception', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function describeWithOpenAI(string $apiKey, string $model, string $base64, string $mimeType): ?string
    {
        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'max_tokens' => 300,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$base64}",
                                    'detail' => 'low',
                                ],
                            ],
                            ['type' => 'text', 'text' => self::VISION_PROMPT],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('image_vision.openai_error', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        return trim($response->json('choices.0.message.content') ?? '');
    }

    private function describeWithAnthropic(string $apiKey, string $model, string $base64, string $mimeType): ?string
    {
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(30)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 300,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $mimeType,
                                    'data' => $base64,
                                ],
                            ],
                            ['type' => 'text', 'text' => self::VISION_PROMPT],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('image_vision.anthropic_error', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        return trim($response->json('content.0.text') ?? '');
    }

    private function describeWithGemini(string $apiKey, string $model, string $base64, string $mimeType): ?string
    {
        $response = Http::timeout(30)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64,
                                ],
                            ],
                            ['text' => self::VISION_PROMPT],
                        ],
                    ],
                ],
                'generationConfig' => ['maxOutputTokens' => 300],
            ]);

        if (! $response->successful()) {
            Log::warning('image_vision.gemini_error', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        return trim($response->json('candidates.0.content.parts.0.text') ?? '');
    }
}
