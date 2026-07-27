<?php

namespace App\Services;

use App\Models\AgentInteractionEvent;
use App\Models\ConversationSession;
use App\Models\FollowupMessage;
use App\Models\Lead;
use App\Models\ServiceTicket;
use Carbon\CarbonInterface;

/**
 * One chronological history for a conversation, merged from the four places the
 * system already records what happened to a lead.
 *
 * Before this, the panel showed three disconnected boxes — five whitelisted agent
 * events, ten follow-ups, and the atendimento list — so answering "what happened
 * to this person, in order?" meant reading three lists and interleaving them by
 * eye. The data was all there; the merge was not.
 *
 * Retention caveat: `agent_interaction_events` is pruned (see
 * AgentInteractionEvent::prunable), while sessions, tickets and follow-ups are
 * kept forever. On a lead older than the retention window the history therefore
 * thins out to its lifecycle entries — which is why the payload carries
 * `event_retention_days` for the panel to say so out loud.
 */
class ConversationHistoryBuilder
{
    /** How many merged entries the panel receives. */
    public const MAX_ENTRIES = 30;

    /**
     * Rows pulled from each source before the merge. Larger than MAX_ENTRIES so a
     * burst in one source can't crowd the others out of the final slice.
     */
    private const PER_SOURCE_LIMIT = 40;

    private const MAX_DETAIL_LENGTH = 140;

    /**
     * The agent events an operator should see, and how each one reads.
     *
     * This map is both the whitelist and the label table on purpose: the query
     * filters on `array_keys()`, so widening the history and forgetting to label
     * the new type is not a state this class can reach.
     *
     * Deliberately excluded, because they are noise on this surface rather than
     * history: the per-message plumbing already visible in the thread
     * (`inbound_received`, `outbound_queued`, `outbound_sent`,
     * `conversation_persisted`, `webhook_received`, `broadcast_sent`), the
     * per-turn agent internals that belong to the Laboratory
     * (`agent_started`, `agent_response_ready`, `model_called`, `context_synced`,
     * `fact_check_passed`, `laboratory_reprocess_*`), and `followup_started`,
     * which is superseded by the follow-up message row for the same moment.
     */
    private const EVENT_LABELS = [
        'ai_paused_manual' => 'IA pausada',
        'ai_resumed_manual' => 'IA retomada',
        'keep_manual' => 'Mantido em atendimento humano',
        'history_cleared_manual' => 'Histórico limpo',
        'lead_created_manual' => 'Lead criado manualmente',
        'lead_deleted_manual' => 'Lead removido',
        'lead_bulk_action' => 'Ação em lote',
        'handoff_created' => 'Escalado para humano',
        'handoff_claimed' => 'Atendimento assumido',
        'followup_skipped' => 'Follow-up ignorado',
        'followup_failed' => 'Follow-up falhou',
        'agent_skipped' => 'Agente não respondeu (regra)',
        'agent_no_reply' => 'Agente optou por não responder',
        'agent_failed' => 'Agente falhou',
        'automation_skipped_no_agent' => 'Sem agente configurado',
        'fact_check_failed' => 'Verificação de fatos reprovada',
        'tool_called' => 'Ferramenta executada',
        'tool_loop_blocked' => 'Loop de ferramenta bloqueado',
        'outbound_failed' => 'Envio falhou',
        'outbound_retrying' => 'Reenvio agendado',
        'outbound_throttled' => 'Envio limitado',
        'outbound_in_doubt' => 'Envio em dúvida',
        'outbound_skipped_optout' => 'Envio cancelado (opt-out)',
        'campaign_dispatch_queued' => 'Enfileirado em campanha',
        'campaign_dispatch_started' => 'Disparo de campanha iniciado',
        'campaign_dispatch_failed' => 'Disparo de campanha falhou',
        'ura_inbound_received' => 'Retorno da URA',
    ];

    /**
     * Payload keys worth surfacing as the entry's one-line detail, most specific
     * first. Events carry free-form payloads, so this reads the first key that is
     * present rather than branching per event type.
     */
    private const DETAIL_KEYS = [
        'reason',
        'motivo',
        'error',
        'message',
        'tool',
        'tool_name',
        'detail',
        'description',
        'status',
    ];

    private const SESSION_REASON_LABELS = [
        ConversationSession::OPEN_REASON_FIRST_CONTACT => 'primeiro contato',
        ConversationSession::OPEN_REASON_REENGAGEMENT_AFTER_TERMINAL => 'retorno após fechamento',
        ConversationSession::OPEN_REASON_REENGAGEMENT_AFTER_INACTIVITY => 'retorno após inatividade',
        ConversationSession::OPEN_REASON_CAMPAIGN => 'campanha',
        ConversationSession::OPEN_REASON_MANUAL => 'aberto manualmente',
    ];

    private const SESSION_OUTCOME_LABELS = [
        ConversationSession::OUTCOME_CONVERTED => 'convertido',
        ConversationSession::OUTCOME_LOST => 'perdido',
        ConversationSession::OUTCOME_NO_RESPONSE => 'sem resposta',
        ConversationSession::OUTCOME_ABANDONED => 'abandonado',
        ConversationSession::OUTCOME_MANUAL_CLOSE => 'encerrado manualmente',
    ];

    private const TICKET_RESOLUTION_LABELS = [
        ServiceTicket::RESOLUTION_CONVERTED => 'convertido',
        ServiceTicket::RESOLUTION_LOST => 'perdido',
        ServiceTicket::RESOLUTION_RETURNED_TO_AI => 'devolvido para a IA',
        ServiceTicket::RESOLUTION_MANUAL_KEEP => 'mantido em atendimento humano',
        ServiceTicket::RESOLUTION_DUPLICATE => 'duplicado',
        ServiceTicket::RESOLUTION_NO_RESPONSE => 'sem resposta',
    ];

    /**
     * @return array{entries: list<array{id: string, kind: string, type: string, label: string, detail: string|null, severity: string, at: string}>, truncated: bool, event_retention_days: int}
     */
    public function forLead(Lead $lead): array
    {
        $entries = [
            ...$this->eventEntries($lead),
            ...$this->sessionEntries($lead),
            ...$this->ticketEntries($lead),
            ...$this->followupEntries($lead),
        ];

        usort($entries, fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return [
            'entries' => array_slice($entries, 0, self::MAX_ENTRIES),
            'truncated' => count($entries) > self::MAX_ENTRIES,
            'event_retention_days' => (int) config('laboratory.retention.interaction_events_days', 90),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function eventEntries(Lead $lead): array
    {
        return AgentInteractionEvent::query()
            ->where('lead_id', $lead->id)
            ->whereIn('event_type', array_keys(self::EVENT_LABELS))
            ->orderByDesc('created_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get(['id', 'event_type', 'created_at', 'severity', 'payload_json'])
            ->map(fn (AgentInteractionEvent $event): array => $this->entry(
                id: 'event:'.$event->id,
                kind: 'event',
                type: (string) $event->event_type,
                label: self::EVENT_LABELS[$event->event_type],
                detail: $this->detailFromPayload($event->payload_json),
                severity: $this->normalizeSeverity((string) $event->severity),
                at: $event->created_at,
            ))
            ->all();
    }

    /**
     * Atendimentos contribute two entries each — opened and closed — so the cycle
     * reads as a span in the timeline rather than a single dot.
     *
     * @return list<array<string, mixed>>
     */
    private function sessionEntries(Lead $lead): array
    {
        $entries = [];

        $sessions = $lead->sessions()
            ->orderByDesc('number')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get();

        foreach ($sessions as $session) {
            if ($session->opened_at !== null) {
                $entries[] = $this->entry(
                    id: 'session_opened:'.$session->id,
                    kind: 'session',
                    type: 'session_opened',
                    label: 'Atendimento #'.$session->number.' aberto',
                    detail: self::SESSION_REASON_LABELS[$session->open_reason] ?? $session->open_reason,
                    severity: 'info',
                    at: $session->opened_at,
                );
            }

            if ($session->closed_at !== null) {
                $entries[] = $this->entry(
                    id: 'session_closed:'.$session->id,
                    kind: 'session',
                    type: 'session_closed',
                    label: 'Atendimento #'.$session->number.' encerrado',
                    detail: $session->outcome === null
                        ? null
                        : (self::SESSION_OUTCOME_LABELS[$session->outcome] ?? $session->outcome),
                    severity: $this->outcomeSeverity($session->outcome),
                    at: $session->closed_at,
                );
            }
        }

        return $entries;
    }

    /**
     * Only the end of a ticket's life is derived here. Its creation and claim
     * already arrive as `handoff_created` / `handoff_claimed` events, and a
     * duplicated line reads worse than a single source of truth.
     *
     * @return list<array<string, mixed>>
     */
    private function ticketEntries(Lead $lead): array
    {
        $entries = [];

        $tickets = ServiceTicket::query()
            ->where('lead_id', $lead->id)
            ->where(function ($query): void {
                $query->whereNotNull('resolved_at')->orWhereNotNull('closed_at');
            })
            ->orderByDesc('id')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get();

        foreach ($tickets as $ticket) {
            $resolution = $ticket->resolution_reason;
            $detail = $resolution === null
                ? null
                : (self::TICKET_RESOLUTION_LABELS[$resolution] ?? $resolution);

            if ($ticket->resolved_at !== null) {
                $entries[] = $this->entry(
                    id: 'ticket_resolved:'.$ticket->id,
                    kind: 'ticket',
                    type: 'ticket_resolved',
                    label: 'Atendimento humano resolvido',
                    detail: $detail,
                    severity: $resolution === ServiceTicket::RESOLUTION_CONVERTED ? 'success' : 'info',
                    at: $ticket->resolved_at,
                );

                continue;
            }

            // Closed without ever being resolved: worth its own line, because it is
            // the one case where a ticket ends with nothing decided.
            $entries[] = $this->entry(
                id: 'ticket_closed:'.$ticket->id,
                kind: 'ticket',
                type: 'ticket_closed',
                label: 'Atendimento humano encerrado sem resolução',
                detail: $detail,
                severity: 'warning',
                at: $ticket->closed_at,
            );
        }

        return $entries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function followupEntries(Lead $lead): array
    {
        return $lead->followupMessages()
            ->whereNotNull('sent_at')
            ->orderByDesc('sent_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get(['id', 'attempt', 'message_text', 'tone', 'sent_at'])
            ->map(fn (FollowupMessage $message): array => $this->entry(
                id: 'followup:'.$message->id,
                kind: 'followup',
                type: 'followup_sent',
                label: 'Follow-up #'.$message->attempt.' enviado',
                detail: $message->message_text,
                severity: 'info',
                at: $message->sent_at,
            ))
            ->all();
    }

    /**
     * @return array{id: string, kind: string, type: string, label: string, detail: string|null, severity: string, at: string}
     */
    private function entry(
        string $id,
        string $kind,
        string $type,
        string $label,
        ?string $detail,
        string $severity,
        CarbonInterface $at,
    ): array {
        return [
            'id' => $id,
            'kind' => $kind,
            'type' => $type,
            'label' => $label,
            'detail' => $this->truncate($detail),
            'severity' => $severity,
            'at' => $at->toIso8601String(),
        ];
    }

    private function outcomeSeverity(?string $outcome): string
    {
        return match ($outcome) {
            ConversationSession::OUTCOME_CONVERTED => 'success',
            ConversationSession::OUTCOME_LOST,
            ConversationSession::OUTCOME_NO_RESPONSE,
            ConversationSession::OUTCOME_ABANDONED => 'warning',
            default => 'info',
        };
    }

    /**
     * `critical` is folded into `error`: the panel is not a pager, and a second
     * shade of red buys the operator nothing.
     */
    private function normalizeSeverity(string $severity): string
    {
        return match ($severity) {
            'critical', 'error' => 'error',
            'warning' => 'warning',
            default => 'info',
        };
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function detailFromPayload(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        foreach (self::DETAIL_KEYS as $key) {
            $value = $payload[$key] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function truncate(?string $detail): ?string
    {
        if ($detail === null || trim($detail) === '') {
            return null;
        }

        $detail = trim($detail);

        return mb_strlen($detail) > self::MAX_DETAIL_LENGTH
            ? mb_substr($detail, 0, self::MAX_DETAIL_LENGTH - 1).'…'
            : $detail;
    }
}
