import type {
    ConversationSessionOpenReason,
    ConversationSessionOutcome,
} from '@/pages/conversas/types';

/** Why an atendimento was opened. Mirrors ConversationSession::OPEN_REASON_*. */
export const SESSION_REASON_LABELS: Record<
    ConversationSessionOpenReason,
    string
> = {
    first_contact: 'Primeiro contato',
    reengagement_after_terminal: 'Retorno após conclusão',
    reengagement_after_inactivity: 'Retorno após inatividade',
    campaign: 'Campanha',
    // Short on purpose: both call sites already prefix it with "Atendimento".
    manual: 'Manual',
};

/** How an atendimento ended. Mirrors ConversationSession::OUTCOME_*. */
export const SESSION_OUTCOME_LABELS: Record<
    ConversationSessionOutcome,
    string
> = {
    converted: 'Convertido',
    lost: 'Perdido',
    no_response: 'Sem resposta',
    abandoned: 'Abandonado',
    manual_close: 'Encerrado manual',
};

/**
 * Outcomes an operator may pick when closing by hand — mirrors
 * CloseConversationSessionRequest::SELECTABLE_OUTCOMES. `abandoned` is omitted
 * on purpose: it belongs to the auto-close scheduler, never to a person.
 */
export const SELECTABLE_SESSION_OUTCOMES: ConversationSessionOutcome[] = [
    'converted',
    'lost',
    'no_response',
    'manual_close',
];
