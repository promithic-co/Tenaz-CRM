<?php

namespace App\Services;

use App\Enums\OperatorAction;
use App\Events\ConversationUpdated;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;

class OperatorCommandService
{
    public function __construct(
        private readonly PauseService $pause,
        private readonly ConversationTimelineService $timeline,
    ) {}

    /**
     * Processa mensagem enviada pelo operador (fromMe) via WhatsApp.
     * Detecta comandos (#retomar) ou faz auto-pause + log da mensagem.
     */
    public function handleOutgoingMessage(
        string $phone,
        string $tenantId,
        ?int $agentId,
        string $instanceName,
        string $message,
        ?array $mediaContext = null,
        ?string $providerMessageId = null,
    ): OperatorAction {
        $trimmed = trim($message);
        $normalized = mb_strtolower($trimmed);

        // Comando: #retomar — resume AI agent
        if ($normalized === '#retomar') {
            $this->pause->resume($phone, $tenantId);

            Log::info('whatsapp.operator_resume', [
                'phone' => $phone,
                'tenant_id' => $tenantId,
                'instance' => $instanceName,
            ]);

            return OperatorAction::Command;
        }

        // Encontrar lead existente — se não existe, ignorar
        $lead = Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('whatsapp', $phone)
            ->first();

        if (! $lead) {
            return OperatorAction::Ignored;
        }

        // Ignorar se não tem texto nem mídia
        if ($trimmed === '' && $mediaContext === null) {
            return OperatorAction::Ignored;
        }

        // Auto-pause: só ativa se ainda não está pausado (preserva TTL)
        if (! $this->pause->isPaused($phone, $tenantId)) {
            $this->pause->pause($phone, $tenantId);

            Log::info('whatsapp.operator_takeover', [
                'phone' => $phone,
                'tenant_id' => $tenantId,
                'lead_id' => $lead->id,
                'instance' => $instanceName,
            ]);
        }

        // Logar mensagem do operador no histórico da conversa
        $lead->updateQuietly([
            'last_interaction_at' => now(),
            'operational_stage' => Lead::STAGE_HUMAN_ACTIVE,
        ]);

        if ($lead->followup_status === 'active') {
            $lead->update(['followup_status' => 'paused']);
        }

        if ($providerMessageId) {
            $existingTimelineMessage = ConversationTimelineMessage::query()
                ->where('lead_id', $lead->id)
                ->where('provider_message_id', $providerMessageId)
                ->first();

            if ($existingTimelineMessage) {
                $existingTimelineMessage->update(['status' => 'sent']);
                $this->timeline->broadcast($existingTimelineMessage->fresh());
                ConversationUpdated::dispatch($lead->id, (string) $tenantId, (string) $lead->status);

                return OperatorAction::Takeover;
            }
        }

        $timelineMessage = $this->timeline->record(
            lead: $lead,
            direction: 'outbound',
            senderType: 'human',
            body: $trimmed,
            media: $mediaContext,
            status: 'sent',
            source: 'from_me',
            providerMessageId: $providerMessageId,
        );
        $this->timeline->broadcast($timelineMessage);
        ConversationUpdated::dispatch($lead->id, (string) $tenantId, (string) $lead->status);

        return OperatorAction::Takeover;
    }
}
