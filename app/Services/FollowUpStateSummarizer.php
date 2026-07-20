<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Collection;

/**
 * Turns a lead's follow-up state into a compact, presentation-ready summary for
 * the conversation and contact panels. The heavy `evaluate()` (which touches
 * followup_messages) only runs for `active` leads; paused/inactive states are
 * summarised from cheap columns + an inferred reason, so no schema change is
 * needed to explain *why* a follow-up stopped.
 */
class FollowUpStateSummarizer
{
    /**
     * pt-BR label for every reason the summary can surface.
     */
    private const REASON_LABELS = [
        'eligible' => 'pronto para enviar',
        'first_delay_not_reached' => 'aguardando intervalo',
        'interval_not_reached' => 'aguardando intervalo',
        'outside_business_hours' => 'fora do horário de envio',
        'max_reached' => 'máximo de tentativas atingido',
        'window_expired' => 'janela WhatsApp expirada',
        'window_expired_requires_hsm' => 'janela WhatsApp expirada',
        'disabled' => 'desativado nas configurações do agente',
        'manual' => 'desativado manualmente',
        'paused' => 'pausado',
        'human_paused' => 'pausado por atendimento humano',
        'terminal_status' => 'status final do lead',
        'no_inbound_window' => 'sem janela de conversa',
        'sandbox' => 'ambiente de testes',
    ];

    public function __construct(
        private readonly FollowUpSettingsResolver $settings,
        private readonly FollowUpWindowService $window,
        private readonly PauseService $pause,
    ) {}

    /**
     * @return array{status: string, count: int, max: int, next_due_at: ?string, reason: ?string, reason_label: ?string}
     */
    public function forLead(Lead $lead): array
    {
        $settings = $this->settings->forLead($lead);
        $max = max(1, (int) ($settings['max_attempts_within_window'] ?? 2));
        $count = (int) $lead->followup_count;
        $status = (string) $lead->followup_status;

        [$reason, $nextDueAt] = match ($status) {
            'active' => $this->activeReason($lead, $settings),
            'paused' => ['paused', null],
            default => [$this->inactiveReason($lead, $settings, $count, $max), null],
        };

        return [
            'status' => $status,
            'count' => $count,
            'max' => $max,
            'next_due_at' => $nextDueAt,
            'reason' => $reason,
            'reason_label' => $reason === null ? null : (self::REASON_LABELS[$reason] ?? null),
        ];
    }

    /**
     * Lightweight batch summary for lists — status/count/max only, no evaluate().
     * Settings are resolved once per distinct agent_id to avoid N+1.
     *
     * @param  Collection<int, Lead>  $leads
     * @return array<int, array{status: string, count: int, max: int}>
     */
    public function forLeads(Collection $leads): array
    {
        $maxByAgent = [];
        $out = [];

        foreach ($leads as $lead) {
            $key = $lead->agent_id ?? 'tenant:'.$lead->tenant_id;

            if (! array_key_exists($key, $maxByAgent)) {
                $settings = $this->settings->forLead($lead);
                $maxByAgent[$key] = max(1, (int) ($settings['max_attempts_within_window'] ?? 2));
            }

            $out[$lead->id] = [
                'status' => (string) $lead->followup_status,
                'count' => (int) $lead->followup_count,
                'max' => $maxByAgent[$key],
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{0: ?string, 1: ?string}
     */
    private function activeReason(Lead $lead, array $settings): array
    {
        $evaluation = $this->window->evaluate($lead, $settings, null, $this->pause);

        return [$evaluation['reason'], $evaluation['due_at']];
    }

    /**
     * Infer why a follow-up is inactive — the engine does not persist the cause,
     * so we reconstruct it cheaply from the lead's current state.
     *
     * @param  array<string, mixed>  $settings
     */
    private function inactiveReason(Lead $lead, array $settings, int $count, int $max): string
    {
        return match (true) {
            $count >= $max => 'max_reached',
            ! $this->window->canSendFreeFormMessage($lead) => 'window_expired',
            ! (bool) ($settings['enabled'] ?? true) => 'disabled',
            default => 'manual',
        };
    }
}
