<?php

namespace App\Services\WhatsApp\Providers;

use App\Ai\DTOs\MediaContext;
use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\DTOs\WhatsApp\IncomingMessageDTO;
use App\Enums\MediaType;
use App\Exceptions\MetaAmbiguousSendException;
use App\Exceptions\MetaApiException;
use App\Exceptions\MetaNoWhatsAppException;
use App\Exceptions\MetaRateLimitException;
use Illuminate\Http\Client\ConnectionException;
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

    public function sendText(string $phone, string $text, ?string $opaqueId = null): string
    {
        return $this->postMessage([
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($phone),
            'type' => 'text',
            'text' => ['body' => $text],
        ], $opaqueId);
    }

    public function sendTemplate(string $phone, string $templateName, string $langCode, array $components = [], ?string $opaqueId = null): string
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
        ], $opaqueId);
    }

    public function sendMedia(string $phone, string $mediaContent, string $mimeType, string $mediaType, ?string $fileName = null, ?string $caption = null, ?string $opaqueId = null): string
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
        ], $opaqueId);
    }

    public function parseWebhook(Request $request): ?IncomingMessageDTO
    {
        $messages = $request->input('entry.0.changes.0.value.messages', []);
        if (! is_array($messages) || empty($messages)) {
            return null;
        }

        $msg = $messages[0];
        $contacts = $request->input('entry.0.changes.0.value.contacts', []);

        return $this->parseMessage(
            is_array($msg) ? $msg : [],
            is_array($contacts) ? $contacts : [],
            $request->all(),
        );
    }

    /**
     * @param  array<string, mixed>  $msg
     * @param  array<int, array<string, mixed>>  $contacts
     * @param  array<string, mixed>  $rawPayload
     */
    public function parseMessage(array $msg, array $contacts = [], array $rawPayload = []): ?IncomingMessageDTO
    {
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
        $normalizedText = ($text !== null && $text !== '') ? $text : null;

        // Nothing actionable: types like reaction, location, contacts, order, system, or an
        // unsupported message carry no text and no media. Dropping them here prevents a hollow
        // inbound from creating a Lead, opening the 24h window, and burning LLM tokens on nothing.
        if (! $hasMedia && $normalizedText === null) {
            return null;
        }

        $pushName = $this->resolvePushName($contacts, $phone);

        return new IncomingMessageDTO(
            phone: $phone,
            instanceName: $this->phoneNumberId,
            text: $normalizedText,
            fromMe: false,
            pushName: $pushName,
            hasMedia: $hasMedia,
            media: null,
            messageId: $msg['id'] ?? null,
            rawPayload: $rawPayload,
            referral: $this->extractReferral($msg),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     */
    private function resolvePushName(array $contacts, string $phone): ?string
    {
        foreach ($contacts as $contact) {
            if ((string) ($contact['wa_id'] ?? '') === $phone) {
                return isset($contact['profile']['name']) ? (string) $contact['profile']['name'] : null;
            }
        }

        $first = $contacts[0] ?? null;

        return is_array($first) && isset($first['profile']['name']) ? (string) $first['profile']['name'] : null;
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
        return self::isValidSignature(
            $request->getContent(),
            (string) $request->header('X-Hub-Signature-256', ''),
            $this->appSecret,
        );
    }

    /**
     * Shared X-Hub-Signature-256 HMAC check. Single source of truth so the
     * per-instance provider path and the controller's global pre-dispatch
     * check can never drift apart. Fails closed on an empty secret.
     */
    public static function isValidSignature(string $payload, string $signatureHeader, string $secret): bool
    {
        if ($secret === '' || ! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signatureHeader);
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postMessage(array $payload, ?string $opaqueId = null): string
    {
        $url = "https://graph.facebook.com/{$this->graphApiVersion}/{$this->phoneNumberId}/messages";

        if ($opaqueId !== null && $opaqueId !== '') {
            $payload['biz_opaque_callback_data'] = $opaqueId;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(15)
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            // Transport-level failure. A connect-refused / DNS failure means no bytes
            // reached Meta (safe to retry). A timeout/reset may have happened AFTER the
            // request was written, so the message MAY have been accepted — that is
            // ambiguous and must never be blindly re-sent.
            if ($this->isDefiniteConnectFailure($e->getMessage())) {
                throw $e;
            }

            throw new MetaAmbiguousSendException($e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            // 5xx: the request reached Meta but the outcome is undecidable — treat as
            // ambiguous rather than retrying into a possible duplicate.
            if ($response->status() >= 500) {
                throw new MetaAmbiguousSendException('Meta returned a 5xx response', $response->status());
            }

            $this->handleErrorResponse($response);
        }

        return (string) $response->json('messages.0.id', '');
    }

    /**
     * True only when the transport error proves the request never left the client
     * (connection refused, host unresolved). Timeouts are intentionally excluded:
     * a cURL-28 can fire after the request body was written.
     */
    private function isDefiniteConnectFailure(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'could not resolve host')
            || str_contains($message, 'failed to connect')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'curl error 6')
            || str_contains($message, 'curl error 7');
    }

    private function handleErrorResponse(Response $response): void
    {
        $code = (int) ($response->json('error.code') ?? 0);
        $message = (string) ($response->json('error.message') ?? 'Meta API error');
        $type = $response->json('error.type');
        $subcode = $response->json('error.error_subcode');
        $fbtraceId = $response->json('error.fbtrace_id');

        Log::error('meta.api_error', [
            'phone_number_id' => $this->phoneNumberId,
            'status' => $response->status(),
            'code' => $code,
            'type' => is_scalar($type) ? (string) $type : null,
            'error_subcode' => is_scalar($subcode) ? (int) $subcode : null,
            'fbtrace_id' => is_scalar($fbtraceId) ? (string) $fbtraceId : null,
            'message' => $message,
        ]);

        // Grouped by handling behavior, not by Meta's loose naming. See Meta Cloud API
        // error reference: 131047 is a re-engagement/24h-window error (NOT an invalid
        // number); 131049/130472 are per-user marketing delivery limits; 131026 is the
        // "undeliverable" bucket. All of these are permanent for a given send and must
        // not be retried. 130429/131048/80007 are throttle signals and are retriable.
        match (true) {
            in_array($code, [130429, 131048, 80007], true) => throw new MetaRateLimitException($message, $code),
            in_array($code, [131026, 131047, 131049, 130472], true) => throw new MetaNoWhatsAppException($message, $code),
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
