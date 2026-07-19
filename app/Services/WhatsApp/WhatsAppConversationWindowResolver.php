<?php

namespace App\Services\WhatsApp;

use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Pure read-side resolver for the WhatsApp conversation windows on a Lead.
 *
 * Two independent windows can apply per Meta's policy:
 *   - 24h customer service window — opens on inbound customer message; templates not
 *     required while open. Tracked via `service_window_expires_at` set on inbound.
 *   - 72h free-entry-point window — opens only when the conversation was initiated
 *     by a CTWA ad / Page CTA / post share. Tracked via `free_entry_point_*` and
 *     `conversation_window_source` set only when webhook referral proves origin.
 *
 * Coexistence (`meta_coexistence` on the instance) does NOT extend or imply 72h —
 * it is exposed as a separate UI note so operators understand why a session may
 * behave differently.
 */
class WhatsAppConversationWindowResolver
{
    private const SERVICE_WINDOW_HOURS = 24;

    /**
     * Enforce Meta's 24h customer-service window server-side before a free-form (text/media)
     * operator message. Template sends bypass this guard (they are always allowed). Only
     * meta_cloud instances have the window — other providers return early.
     *
     * @throws ValidationException when the window is not open (closed, or no inbound yet).
     */
    public function ensureFreeFormAllowed(Lead $lead, string $providerKey): void
    {
        if ($providerKey !== 'meta_cloud') {
            return;
        }

        $resolved = $this->resolve($lead);

        if ($resolved['service_window']['status'] === 'open' || $resolved['free_entry_point']['status'] === 'active') {
            return;
        }

        throw ValidationException::withMessages([
            'content' => 'A janela de 24 horas encerrou. Selecione um template aprovado para falar com o cliente.',
        ]);
    }

    /**
     * @return array{
     *   service_window: array{status: 'open'|'closed'|'unknown', remaining_seconds: int|null, expires_at: string|null},
     *   template_required: bool,
     *   free_entry_point: array{status: 'active'|'expired'|'unknown', remaining_seconds: int|null, source: string|null, expires_at: string|null},
     *   coexistence: array{enabled: bool, note: string|null},
     * }
     */
    public function resolve(Lead $lead): array
    {
        $now = now();

        // Service window (24h). The stored column is authoritative; when it is null (legacy
        // lead), derive the deadline from the last inbound timeline message so the window still
        // resolves correctly for the guard, the props, and the side panel.
        $expiresAt = $this->effectiveServiceWindowExpiry($lead);
        if ($expiresAt === null) {
            $serviceWindow = [
                'status' => 'unknown',
                'remaining_seconds' => null,
                'expires_at' => null,
            ];
        } elseif ($expiresAt->isFuture()) {
            $serviceWindow = [
                'status' => 'open',
                'remaining_seconds' => $now->diffInSeconds($expiresAt, false),
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        } else {
            $serviceWindow = [
                'status' => 'closed',
                'remaining_seconds' => 0,
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        }

        // Free-entry-point window (72h)
        $feExpires = $lead->free_entry_point_expires_at;
        if ($feExpires === null) {
            $freeEntry = [
                'status' => 'unknown',
                'remaining_seconds' => null,
                'source' => $lead->conversation_window_source,
                'expires_at' => null,
            ];
        } elseif ($feExpires->isFuture()) {
            $freeEntry = [
                'status' => 'active',
                'remaining_seconds' => $now->diffInSeconds($feExpires, false),
                'source' => $lead->conversation_window_source,
                'expires_at' => $feExpires->toIso8601String(),
            ];
        } else {
            $freeEntry = [
                'status' => 'expired',
                'remaining_seconds' => 0,
                'source' => $lead->conversation_window_source,
                'expires_at' => $feExpires->toIso8601String(),
            ];
        }

        // Template requirement: free entry window OR service window keeps a session open.
        $templateRequired = $serviceWindow['status'] !== 'open' && $freeEntry['status'] !== 'active';

        // Coexistence note from instance, if available. Lookup via the new relationship,
        // but tolerate missing relationships (e.g., orphaned legacy leads).
        $coexistence = ['enabled' => false, 'note' => null];
        $instance = $lead->whatsappInstance;
        if ($instance !== null && (bool) $instance->meta_coexistence === true) {
            $coexistence = [
                'enabled' => true,
                'note' => 'Algumas mensagens podem aparecer apenas no WhatsApp. Confira o aplicativo para acompanhar toda a conversa.',
            ];
        }

        return [
            'service_window' => $serviceWindow,
            'template_required' => $templateRequired,
            'free_entry_point' => $freeEntry,
            'coexistence' => $coexistence,
        ];
    }

    /**
     * The stored `service_window_expires_at` when present; otherwise the last inbound
     * timeline message's time + 24h. Null when the column is unset and the lead has never
     * sent an inbound (no window has ever opened).
     */
    private function effectiveServiceWindowExpiry(Lead $lead): ?CarbonInterface
    {
        if ($lead->service_window_expires_at !== null) {
            return $lead->service_window_expires_at;
        }

        $lastInboundAt = ConversationTimelineMessage::query()
            ->where('lead_id', $lead->id)
            ->where('direction', 'inbound')
            ->max('created_at');

        if ($lastInboundAt === null) {
            return null;
        }

        return Carbon::parse($lastInboundAt)->addHours(self::SERVICE_WINDOW_HOURS);
    }
}
