<?php

namespace App\Contracts\WhatsApp;

use App\Ai\DTOs\MediaContext;
use App\DTOs\WhatsApp\IncomingMessageDTO;
use Illuminate\Http\Request;

interface WhatsAppProviderInterface
{
    public function sendText(string $phone, string $text, ?string $opaqueId = null): string;

    public function sendTemplate(string $phone, string $templateName, string $langCode, array $components = [], ?string $opaqueId = null): string;

    public function sendMedia(string $phone, string $mediaContent, string $mimeType, string $mediaType, ?string $fileName = null, ?string $caption = null, ?string $opaqueId = null): string;

    public function parseWebhook(Request $request): ?IncomingMessageDTO;

    public function verifyWebhook(Request $request): bool;

    public function downloadMedia(Request $request, array $messageData): ?MediaContext;
}
