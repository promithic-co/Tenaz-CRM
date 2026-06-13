<?php

namespace App\DTOs\WhatsApp;

use App\Ai\DTOs\MediaContext;

readonly class IncomingMessageDTO
{
    /**
     * @param  array<string, mixed>|null  $referral  CTWA/Page CTA entry-point payload.
     *                                               Expected keys: source_type, source_id, source_url,
     *                                               ctwa_clid, headline, body, entry_point.
     */
    public function __construct(
        public string $phone,
        public string $instanceName,
        public ?string $text,
        public bool $fromMe,
        public ?string $pushName,
        public bool $hasMedia,
        public ?MediaContext $media,
        public ?string $messageId,
        public array $rawPayload = [],
        public ?array $referral = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            phone: $data['phone'],
            instanceName: $data['instance_name'],
            text: $data['text'] ?? null,
            fromMe: (bool) ($data['from_me'] ?? false),
            pushName: $data['push_name'] ?? null,
            hasMedia: (bool) ($data['has_media'] ?? false),
            media: isset($data['media']) ? MediaContext::fromArray($data['media']) : null,
            messageId: $data['message_id'] ?? null,
            rawPayload: $data['raw_payload'] ?? [],
            referral: $data['referral'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'phone' => $this->phone,
            'instance_name' => $this->instanceName,
            'text' => $this->text,
            'from_me' => $this->fromMe,
            'push_name' => $this->pushName,
            'has_media' => $this->hasMedia,
            'media' => $this->media?->toArray(),
            'message_id' => $this->messageId,
            'raw_payload' => $this->rawPayload,
            'referral' => $this->referral,
        ];
    }
}
