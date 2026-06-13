<?php

namespace App\Services\WhatsApp;

use App\Models\Lead;

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

        // Service window (24h)
        $expiresAt = $lead->service_window_expires_at;
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
                'note' => 'Instância em modo coexistência Meta: mensagens fora da janela podem aparecer no app oficial sem passar pela API.',
            ];
        }

        return [
            'service_window' => $serviceWindow,
            'template_required' => $templateRequired,
            'free_entry_point' => $freeEntry,
            'coexistence' => $coexistence,
        ];
    }
}
