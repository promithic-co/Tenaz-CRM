<?php

namespace App\Services;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\WhatsappInstance;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function __construct(
        private readonly WhatsAppProviderFactory $factory,
    ) {}

    /**
     * Provider-agnostic text send via WhatsappInstance model.
     */
    public function sendTextViaInstance(WhatsappInstance $instance, string $phone, string $text, ?string $opaqueId = null): string
    {
        $provider = $this->factory->makeProvider($instance);

        return $provider->sendText($phone, $text, $opaqueId);
    }

    public function sendTemplateViaInstance(WhatsappInstance $instance, string $phone, string $templateName, string $langCode = 'pt_BR', array $components = [], ?string $opaqueId = null): string
    {
        $provider = $this->factory->makeProvider($instance);

        return $provider->sendTemplate($phone, $templateName, $langCode, $components, $opaqueId);
    }

    /**
     * Provider-agnostic media send via WhatsappInstance model.
     * $mediaContent is raw base64 (no data URI prefix).
     */
    public function sendMediaViaInstance(
        WhatsappInstance $instance,
        string $phone,
        string $mediaContent,
        string $mimeType,
        string $mediaType,
        ?string $fileName = null,
        ?string $caption = null,
        ?string $opaqueId = null,
    ): string {
        $provider = $this->factory->makeProvider($instance);

        return $provider->sendMedia($phone, $mediaContent, $mimeType, $mediaType, $fileName, $caption, $opaqueId);
    }

    /**
     * Split messages and send with delay via instance model.
     */
    public function sendSplitMessagesViaInstance(WhatsappInstance $instance, string $phone, string $text): void
    {
        $parts = array_filter(
            array_map('trim', explode("\n\n", $text)),
            fn ($p) => strlen($p) > 0
        );

        if (empty($parts)) {
            return;
        }

        if (count($parts) === 1) {
            $this->sendTextViaInstance($instance, $phone, reset($parts));

            return;
        }

        foreach (array_values($parts) as $index => $part) {
            SendWhatsAppMessageJob::dispatch(
                instance: $instance->name,
                number: $phone,
                text: trim($part),
                instanceId: $instance->id,
            )->delay(now()->addSeconds($index * 2));
        }
    }

    /**
     * Legacy: keep existing string-based signature working.
     * Delegates to provider if instance found in DB.
     */
    public function sendText(string $instance, string $number, string $text): void
    {
        $this->sendTextNow($instance, $number, $text);
    }

    public function sendTextNow(string $instance, string $number, string $text): ?string
    {
        $instanceModel = WhatsappInstance::where('name', $instance)->first();

        if ($instanceModel) {
            return $this->sendTextViaInstance($instanceModel, $number, $text);
        }

        return $this->sendTextLegacy($instance, $number, $text);
    }

    /**
     * Legacy: keep existing string-based signature working.
     * $base64 is raw base64 (no data URI prefix).
     */
    public function sendMedia(
        string $instance,
        string $number,
        string $base64,
        string $mimeType,
        string $mediaType,
        ?string $fileName = null,
        ?string $caption = null,
    ): void {
        $this->sendMediaNow($instance, $number, $base64, $mimeType, $mediaType, $fileName, $caption);
    }

    public function sendMediaNow(
        string $instance,
        string $number,
        string $base64,
        string $mimeType,
        string $mediaType,
        ?string $fileName = null,
        ?string $caption = null,
    ): ?string {
        $instanceModel = WhatsappInstance::where('name', $instance)->first();

        if ($instanceModel) {
            return $this->sendMediaViaInstance($instanceModel, $number, $base64, $mimeType, $mediaType, $fileName, $caption);
        }

        return $this->sendMediaLegacy($instance, $number, $base64, $mimeType, $mediaType, $fileName, $caption);
    }

    /**
     * Legacy: keep existing string-based signature working.
     */
    public function sendSplitMessages(string $instance, string $number, string $text): void
    {
        $instanceModel = WhatsappInstance::where('name', $instance)->first();

        if ($instanceModel) {
            $this->sendSplitMessagesViaInstance($instanceModel, $number, $text);

            return;
        }

        $parts = array_filter(
            array_map('trim', explode("\n\n", $text)),
            fn ($p) => strlen($p) > 0
        );

        if (empty($parts)) {
            return;
        }

        if (count($parts) === 1) {
            $this->sendText($instance, $number, reset($parts));

            return;
        }

        foreach (array_values($parts) as $index => $part) {
            SendWhatsAppMessageJob::dispatch($instance, $number, trim($part))
                ->delay(now()->addSeconds($index * 2));
        }
    }

    private function sendTextLegacy(string $instance, string $number, string $text): ?string
    {
        Log::warning('whatsapp.send_legacy_fallback', [
            'instance' => $instance,
            'number' => $number,
            'reason' => 'no_instance_model_found',
        ]);

        return null;
    }

    private function sendMediaLegacy(
        string $instance,
        string $number,
        string $base64,
        string $mimeType,
        string $mediaType,
        ?string $fileName,
        ?string $caption,
    ): ?string {
        Log::warning('whatsapp.send_media_legacy_fallback', [
            'instance' => $instance,
            'number' => $number,
            'reason' => 'no_instance_model_found',
        ]);

        return null;
    }
}
