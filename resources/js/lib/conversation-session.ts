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

const BRL = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

/** Cents to "R$ 1.234,56". Null and 0 are different things: only null means "unpriced". */
export function formatCents(cents: number | null | undefined): string | null {
    if (cents === null || cents === undefined) {
        return null;
    }

    return BRL.format(cents / 100);
}

/**
 * Parse whatever an operator typed into cents.
 *
 * The server never sees this string. Locale parsing is ambiguous — "1.234" is a thousand
 * here and one-point-two-three-four elsewhere — so the conversion happens once, next to the
 * pt-BR input that produced it, and only the integer travels.
 *
 * Both separators are accepted because keypads and habits vary: the LAST one wins as the
 * decimal mark, every other is treated as a thousands separator. Returns null for empty
 * input (clears the value) and for anything that isn't a number.
 */
export function parseCents(input: string): number | null {
    const trimmed = input.trim();

    if (trimmed === '') {
        return null;
    }

    const digitsOnly = trimmed.replace(/[^\d.,]/g, '');
    const lastSeparator = Math.max(
        digitsOnly.lastIndexOf(','),
        digitsOnly.lastIndexOf('.'),
    );

    // A trailing group of exactly 1 or 2 digits is a decimal fraction; anything else
    // (1.234, 12.345) is a thousands separator that must not shrink the number 1000x.
    const fractionDigits =
        lastSeparator === -1 ? 0 : digitsOnly.length - lastSeparator - 1;
    const hasDecimal = fractionDigits >= 1 && fractionDigits <= 2;

    const whole = (
        hasDecimal ? digitsOnly.slice(0, lastSeparator) : digitsOnly
    ).replace(/\D/g, '');
    const fraction = hasDecimal
        ? digitsOnly.slice(lastSeparator + 1).padEnd(2, '0')
        : '00';

    if (whole === '' && !hasDecimal) {
        return null;
    }

    const cents = Number(`${whole || '0'}${fraction}`);

    return Number.isFinite(cents) ? cents : null;
}
