<?php

namespace App\Services\WhatsApp;

use App\Models\Contact;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Services\ContactExtraDataService;
use App\Services\ContactSyncService;
use App\Services\ConversationTimelineService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MetaCoexistenceWebhookService
{
    public function __construct(
        private readonly ContactSyncService $contacts,
        private readonly ContactExtraDataService $extraData,
        private readonly ConversationTimelineService $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public function process(WhatsappInstance $instance, string $field, array $value): void
    {
        if ($field === 'smb_app_state_sync') {
            $this->processContactState($instance, $value);

            return;
        }

        if ($field === 'smb_message_echoes') {
            $this->processMessageEchoes($instance, $value);

            return;
        }

        if ($field === 'history') {
            $this->processHistory($instance, $value);
        }
    }

    /** @param array<string, mixed> $value */
    private function processContactState(WhatsappInstance $instance, array $value): void
    {
        foreach ((array) ($value['state_sync'] ?? []) as $state) {
            if (! is_array($state) || ($state['type'] ?? null) !== 'contact') {
                continue;
            }

            $contactData = is_array($state['contact'] ?? null) ? $state['contact'] : [];
            $phone = (string) ($contactData['phone_number'] ?? '');
            $action = (string) ($state['action'] ?? '');
            $timestamp = (string) ($state['metadata']['timestamp'] ?? '');

            if ($phone === '') {
                continue;
            }

            if ($action === 'remove') {
                $this->markContactRemoved($instance, $phone, $timestamp);

                continue;
            }

            if ($action !== 'add') {
                continue;
            }

            $contact = $this->contacts->resolveContact(
                tenantId: (string) $instance->tenant_id,
                rawPhone: $phone,
                attrs: [
                    'name' => $contactData['full_name'] ?? $contactData['first_name'] ?? null,
                    'extra_data' => [
                        'whatsapp_app_sync_action' => 'add',
                        'whatsapp_app_synced_at' => $timestamp,
                        'whatsapp_app_removed_at' => null,
                    ],
                ],
                source: Contact::SOURCE_WHATSAPP_APP_SYNC,
            );

            if ($contact && filled($contact->name)) {
                Lead::withoutGlobalScopes()
                    ->where('tenant_id', (string) $instance->tenant_id)
                    ->where('whatsapp', $contact->phone)
                    ->whereNull('deleted_at')
                    ->where(fn ($query) => $query->whereNull('nome')->orWhere('nome', ''))
                    ->update([
                        'nome' => $contact->name,
                        'contact_id' => $contact->id,
                    ]);
            }
        }
    }

    private function markContactRemoved(WhatsappInstance $instance, string $rawPhone, string $timestamp): void
    {
        $phone = $this->contacts->normalizePhone($rawPhone);
        if ($phone === null) {
            return;
        }

        $contact = Contact::withoutGlobalScopes()
            ->where('tenant_id', (string) $instance->tenant_id)
            ->where('phone', $phone)
            ->first();

        if (! $contact) {
            return;
        }

        $this->extraData->merge($contact, [
            'whatsapp_app_sync_action' => 'remove',
            'whatsapp_app_removed_at' => $timestamp,
        ]);
    }

    /** @param array<string, mixed> $value */
    private function processMessageEchoes(WhatsappInstance $instance, array $value): void
    {
        foreach ((array) ($value['message_echoes'] ?? []) as $message) {
            if (! is_array($message)) {
                continue;
            }

            $this->persistMessage(
                instance: $instance,
                contactPhone: (string) ($message['to'] ?? ''),
                message: $message,
                direction: 'outbound',
                source: 'whatsapp_business_app',
                broadcast: true,
            );
        }
    }

    /** @param array<string, mixed> $value */
    private function processHistory(WhatsappInstance $instance, array $value): void
    {
        $businessPhone = $this->contacts->normalizePhone(
            (string) ($value['metadata']['display_phone_number'] ?? '')
        );

        foreach ((array) ($value['history'] ?? []) as $historyChunk) {
            if (! is_array($historyChunk)) {
                continue;
            }

            if (! empty($historyChunk['errors'])) {
                Log::info('meta.coexistence.history_not_shared', [
                    'instance_id' => $instance->id,
                    'errors' => $historyChunk['errors'],
                ]);

                continue;
            }

            foreach ((array) ($historyChunk['threads'] ?? []) as $thread) {
                if (! is_array($thread)) {
                    continue;
                }

                $threadPhone = (string) ($thread['id'] ?? '');

                foreach ((array) ($thread['messages'] ?? []) as $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    $from = $this->contacts->normalizePhone((string) ($message['from'] ?? ''));
                    $direction = $businessPhone !== null && $from === $businessPhone
                        ? 'outbound'
                        : 'inbound';
                    $contactPhone = $direction === 'outbound'
                        ? (string) ($message['to'] ?? $threadPhone)
                        : (string) ($message['from'] ?? $threadPhone);

                    $this->persistMessage(
                        instance: $instance,
                        contactPhone: $contactPhone,
                        message: $message,
                        direction: $direction,
                        source: 'whatsapp_app_history',
                        broadcast: false,
                    );
                }
            }
        }
    }

    /** @param array<string, mixed> $message */
    private function persistMessage(
        WhatsappInstance $instance,
        string $contactPhone,
        array $message,
        string $direction,
        string $source,
        bool $broadcast,
    ): void {
        $phone = $this->contacts->normalizePhone($contactPhone);
        $providerMessageId = (string) ($message['id'] ?? '');

        if ($phone === null || $providerMessageId === '') {
            return;
        }

        $lockKey = 'meta_coexistence_message:'.sha1(
            (string) $instance->tenant_id.'|'.$providerMessageId
        );

        Cache::lock($lockKey, 10)->block(5, function () use (
            $instance,
            $phone,
            $message,
            $direction,
            $source,
            $broadcast,
            $providerMessageId,
        ): void {
            $existing = ConversationTimelineMessage::withoutGlobalScopes()
                ->where('tenant_id', (string) $instance->tenant_id)
                ->where('provider_message_id', $providerMessageId)
                ->first();

            if ($existing) {
                $existingMediaType = is_array($existing->media)
                    ? ($existing->media['type'] ?? null)
                    : null;
                $incomingType = (string) ($message['type'] ?? '');

                if ($existingMediaType === 'media_placeholder' && $incomingType !== 'media_placeholder') {
                    $existing->update([
                        'body' => $this->messageBody($message),
                        'media' => $this->messageMedia($message),
                    ]);
                }

                return;
            }

            if ($this->applyMutationToOriginalMessage($instance, $message, $source)) {
                return;
            }

            $lead = $this->resolveLead($instance, $phone);
            $type = (string) ($message['type'] ?? 'unknown');
            $timelineMessage = $this->timeline->record(
                lead: $lead,
                direction: $direction,
                senderType: $direction === 'outbound' ? 'human' : 'lead',
                body: $this->messageBody($message),
                media: $this->messageMedia($message),
                status: strtolower((string) ($message['history_context']['status'] ?? ($direction === 'outbound' ? 'sent' : 'received'))),
                source: $source,
                providerMessageId: $providerMessageId,
            );

            $timestamp = (string) ($message['timestamp'] ?? '');
            if (ctype_digit($timestamp)) {
                $occurredAt = Carbon::createFromTimestampUTC((int) $timestamp);
                $timelineMessage->timestamps = false;
                $timelineMessage->forceFill([
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ])->saveQuietly();
            }

            if ($broadcast && $type !== 'media_placeholder') {
                $this->timeline->broadcast($timelineMessage);
            }
        });
    }

    /** @param array<string, mixed> $message */
    private function applyMutationToOriginalMessage(WhatsappInstance $instance, array $message, string $source): bool
    {
        $type = (string) ($message['type'] ?? '');
        if (! in_array($type, ['edit', 'revoke'], true)) {
            return false;
        }

        $mutation = is_array($message[$type] ?? null) ? $message[$type] : [];
        $originalId = (string) ($mutation['original_message_id'] ?? '');
        if ($originalId === '') {
            return false;
        }

        $original = ConversationTimelineMessage::withoutGlobalScopes()
            ->where('tenant_id', (string) $instance->tenant_id)
            ->where('provider_message_id', $originalId)
            ->first();

        if (! $original) {
            return false;
        }

        $original->update([
            'body' => $type === 'revoke'
                ? '[Mensagem removida no WhatsApp Business]'
                : $this->messageBody(is_array($mutation['message'] ?? null) ? $mutation['message'] : []),
            'status' => $type === 'revoke' ? 'revoked' : $original->status,
            'source' => $source,
        ]);

        return true;
    }

    private function resolveLead(WhatsappInstance $instance, string $phone): Lead
    {
        $lockKey = 'meta_coexistence_lead:'.sha1((string) $instance->tenant_id.'|'.$phone);

        return Cache::lock($lockKey, 10)->block(5, function () use ($instance, $phone): Lead {
            $lead = Lead::withoutGlobalScopes()
                ->withTrashed()
                ->where('tenant_id', (string) $instance->tenant_id)
                ->where('whatsapp', $phone)
                ->first();

            if (! $lead) {
                $lead = Lead::withoutGlobalScopes()->create([
                    'tenant_id' => (string) $instance->tenant_id,
                    'agent_id' => $instance->agent_id,
                    'whatsapp' => $phone,
                    'status' => 'novo',
                    'modo' => 'receptivo',
                    'ai_mode' => Lead::AI_MODE_MANUAL,
                    'operational_stage' => Lead::STAGE_NEW_INBOUND,
                    'evolution_instance' => $instance->name,
                    'whatsapp_instance_id' => $instance->id,
                ]);
            } elseif ($lead->trashed()) {
                $lead->restore();
            }

            $this->contacts->syncFromLead($lead, Contact::SOURCE_WHATSAPP_APP_SYNC);

            return $lead;
        });
    }

    /** @param array<string, mixed> $message */
    private function messageBody(array $message): string
    {
        $type = (string) ($message['type'] ?? 'unknown');

        return match ($type) {
            'text' => (string) ($message['text']['body'] ?? ''),
            'image' => (string) ($message['image']['caption'] ?? '[Imagem]'),
            'video' => (string) ($message['video']['caption'] ?? '[Vídeo]'),
            'document' => (string) ($message['document']['caption'] ?? $message['document']['filename'] ?? '[Documento]'),
            'audio' => '[Áudio]',
            'sticker' => '[Figurinha]',
            'media_placeholder' => '[Mídia do histórico]',
            'reaction' => (string) ($message['reaction']['emoji'] ?? '[Reação]'),
            'button' => (string) ($message['button']['text'] ?? '[Botão]'),
            'interactive' => (string) ($message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? '[Interação]'),
            'location' => sprintf('[Localização: %s, %s]', $message['location']['latitude'] ?? '?', $message['location']['longitude'] ?? '?'),
            'contacts' => '[Contato compartilhado]',
            default => "[Mensagem {$type}]",
        };
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>|null
     */
    private function messageMedia(array $message): ?array
    {
        $type = (string) ($message['type'] ?? '');
        if (! in_array($type, ['image', 'video', 'audio', 'document', 'sticker', 'media_placeholder'], true)) {
            return null;
        }

        $contents = is_array($message[$type] ?? null) ? $message[$type] : [];

        return [
            'type' => $type,
            'mime_type' => $contents['mime_type'] ?? null,
            'filename' => $contents['filename'] ?? null,
            'caption' => $contents['caption'] ?? null,
        ];
    }
}
