<?php

namespace App\Services\WhatsApp\Providers;

use App\Ai\DTOs\MediaContext;
use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\DTOs\WhatsApp\IncomingMessageDTO;
use App\Enums\MediaType;
use App\Exceptions\MetaApiException;
use App\Exceptions\MetaInvalidNumberException;
use App\Exceptions\MetaNoWhatsAppException;
use App\Exceptions\MetaRateLimitException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MetaCloudProvider implements WhatsAppProviderInterface
{
    public function __construct(
        private readonly string $phoneNumberId,
        private readonly string $accessToken,
        private readonly string $appSecret = '',
        private readonly string $graphApiVersion = 'v23.0',
    ) {}

    public function sendText(string $phone, string $text): string
    {
        return $this->postMessage([
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($phone),
            'type' => 'text',
            'text' => ['body' => $text],
        ]);
    }

    public function sendTemplate(string $phone, string $templateName, string $langCode, array $components = []): string
    {
        return $this->postMessage([
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($phone),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $langCode],
                'components' => $components,
            ],
        ]);
    }

    public function sendMedia(string $phone, string $mediaContent, string $mimeType, string $mediaType, ?string $fileName = null, ?string $caption = null): string
    {
        $metaType = $this->resolveMetaMediaType($mimeType, $mediaType);
        $mediaBlock = [];

        if (str_starts_with($mediaContent, 'http://') || str_starts_with($mediaContent, 'https://')) {
            $mediaBlock['link'] = $mediaContent;
        } else {
            $mediaBlock['id'] = $mediaContent;
        }

        if ($caption && in_array($metaType, ['image', 'video', 'document'], true)) {
            $mediaBlock['caption'] = $caption;
        }
        if ($fileName && $metaType === 'document') {
            $mediaBlock['filename'] = $fileName;
        }

        return $this->postMessage([
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($phone),
            'type' => $metaType,
            $metaType => $mediaBlock,
        ]);
    }

    public function parseWebhook(Request $request): ?IncomingMessageDTO
    {
        $messages = $request->input('entry.0.changes.0.value.messages', []);
        if (! is_array($messages) || empty($messages)) {
            return null;
        }

        $msg = $messages[0];
        $phone = preg_replace('/\D/', '', (string) ($msg['from'] ?? ''));
        if (! $phone) {
            return null;
        }

        $msgType = (string) ($msg['type'] ?? 'text');
        $text = match ($msgType) {
            'text' => $msg['text']['body'] ?? null,
            'interactive' => $msg['interactive']['button_reply']['title']
                ?? $msg['interactive']['list_reply']['title']
                ?? null,
            'button' => $msg['button']['text'] ?? null,
            'image', 'video', 'document' => $msg[$msgType]['caption'] ?? null,
            default => null,
        };

        $hasMedia = in_array($msgType, ['image', 'audio', 'video', 'document', 'sticker'], true);
        $pushName = $request->input('entry.0.changes.0.value.contacts.0.profile.name');

        return new IncomingMessageDTO(
            phone: $phone,
            instanceName: $this->phoneNumberId,
            text: ($text !== null && $text !== '') ? $text : null,
            fromMe: false,
            pushName: $pushName,
            hasMedia: $hasMedia,
            media: null,
            messageId: $msg['id'] ?? null,
            rawPayload: $request->all(),
            referral: $this->extractReferral($msg),
        );
    }

    /**
     * Extract CTWA / Page CTA entry-point data from a Meta Cloud inbound message.
     *
     * Meta payload places `referral` on the message object when the conversation was
     * started by a Click-to-WhatsApp ad, Facebook Page CTA, or post share. We only
     * keep fields needed to qualify for the 72h free-entry-point window; instance-level
     * `meta_coexistence` is never used to infer it.
     *
     * @param  array<string, mixed>  $msg
     * @return array<string, mixed>|null
     */
    private function extractReferral(array $msg): ?array
    {
        $referral = $msg['referral'] ?? null;
        if (! is_array($referral)) {
            return null;
        }

        $sourceType = (string) ($referral['source_type'] ?? '');
        if ($sourceType === '') {
            return null;
        }

        return [
            'source_type' => $sourceType,
            'source_id' => $referral['source_id'] ?? null,
            'source_url' => $referral['source_url'] ?? null,
            'headline' => $referral['headline'] ?? null,
            'body' => $referral['body'] ?? null,
            'ctwa_clid' => $referral['ctwa_clid'] ?? null,
            'media_type' => $referral['media_type'] ?? null,
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        if (empty($this->appSecret)) {
            return false;
        }

        $received = (string) $request->header('X-Hub-Signature-256', '');
        if (! str_starts_with($received, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $this->appSecret);

        return hash_equals($expected, $received);
    }

    public function downloadMedia(Request $request, array $messageData): ?MediaContext
    {
        $mediaType = $messageData['type'] ?? null;
        if (! $mediaType) {
            return null;
        }

        $mediaNode = $messageData[$mediaType] ?? null;
        $mediaId = $mediaNode['id'] ?? null;
        if (! $mediaId) {
            return null;
        }

        try {
            $urlResponse = Http::withToken($this->accessToken)
                ->timeout(15)
                ->get("https://graph.facebook.com/{$this->graphApiVersion}/{$mediaId}");

            if (! $urlResponse->successful()) {
                Log::warning('meta.media_url_lookup_failed', ['media_id' => $mediaId, 'status' => $urlResponse->status()]);

                return null;
            }

            $downloadUrl = (string) $urlResponse->json('url', '');
            if (! $downloadUrl) {
                return null;
            }

            $binaryResponse = Http::withToken($this->accessToken)
                ->timeout(45)
                ->get($downloadUrl);

            if (! $binaryResponse->successful()) {
                Log::warning('meta.media_download_failed', ['media_id' => $mediaId, 'status' => $binaryResponse->status()]);

                return null;
            }

            $mimeType = (string) ($mediaNode['mime_type'] ?? $urlResponse->json('mime_type') ?? 'application/octet-stream');
            $mimeTypeClean = explode(';', $mimeType)[0];
            $binaryData = $binaryResponse->body();
            $hash = hash('sha256', $binaryData);
            $hashPrefix = substr($hash, 0, 2);
            $sizeBytes = strlen($binaryData);
            $type = $this->mapMimeToMediaType($mimeTypeClean);
            $extension = $this->guessExtension($mimeTypeClean);
            $filename = "{$hashPrefix}/{$hash}.{$extension}";
            $diskPath = "media/{$filename}";

            Storage::disk('local')->put($diskPath, $binaryData);
            $localPath = Storage::disk('local')->path($diskPath);

            return new MediaContext(
                type: $type,
                localPath: $localPath,
                mimeType: $mimeTypeClean,
                originalHash: $hash,
                sizeBytes: $sizeBytes,
                caption: $mediaNode['caption'] ?? null,
                filename: $mediaNode['filename'] ?? null,
            );
        } catch (Throwable $e) {
            Log::error('meta.media_download_exception', ['error' => $e->getMessage(), 'media_id' => $mediaId]);

            return null;
        }
    }

    private function postMessage(array $payload): string
    {
        $url = "https://graph.facebook.com/{$this->graphApiVersion}/{$this->phoneNumberId}/messages";

        $response = Http::withToken($this->accessToken)
            ->timeout(15)
            ->post($url, $payload);

        if (! $response->successful()) {
            $this->handleErrorResponse($response);
        }

        return (string) $response->json('messages.0.id', '');
    }

    private function handleErrorResponse(Response $response): void
    {
        $code = (int) ($response->json('error.code') ?? 0);
        $message = (string) ($response->json('error.message') ?? 'Meta API error');

        Log::error('meta.api_error', [
            'phone_number_id' => $this->phoneNumberId,
            'status' => $response->status(),
            'code' => $code,
            'message' => $message,
        ]);

        match ($code) {
            130429 => throw new MetaRateLimitException($message, $code),
            131047 => throw new MetaInvalidNumberException($message, $code),
            131026 => throw new MetaNoWhatsAppException($message, $code),
            default => throw new MetaApiException($message, $code),
        };
    }

    private function normalizePhone(string $phone): string
    {
        return (string) preg_replace('/\D/', '', $phone);
    }

    private function resolveMetaMediaType(string $mimeType, string $fallback): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'audio/') => 'audio',
            str_starts_with($mimeType, 'video/') => 'video',
            default => in_array($fallback, ['image', 'audio', 'video', 'document', 'sticker'], true) ? $fallback : 'document',
        };
    }

    private function mapMimeToMediaType(string $mimeType): MediaType
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => MediaType::Image,
            str_starts_with($mimeType, 'audio/') => MediaType::Audio,
            str_starts_with($mimeType, 'video/') => MediaType::Video,
            default => MediaType::Document,
        };
    }

    private function guessExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'audio/ogg', 'audio/ogg; codecs=opus' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4', 'audio/m4a' => 'm4a',
            'video/mp4' => 'mp4',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
