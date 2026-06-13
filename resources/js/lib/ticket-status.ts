/** Reason codes for escalation tickets (EscalarParaHumano). */
export const TICKET_REASON_LABELS: Record<string, string> = {
    proposta_aceita: 'Proposta aceita',
    solicitacao_cliente: 'Solicitação do cliente',
    problema_tecnico: 'Problema técnico',
    outro: 'Outro',
};

export function ticketReasonLabel(reason: string | null): string {
    if (!reason) return '-';
    return TICKET_REASON_LABELS[reason] ?? reason;
}

export const TICKET_STATUSES = {
    open: {
        label: 'Aberto',
        classes: 'bg-orange-500/15 text-orange-400 border-orange-500/20',
    },
    assigned: {
        label: 'Assumido',
        classes: 'bg-blue-500/15 text-blue-400 border-blue-500/20',
    },
    waiting_customer: {
        label: 'Aguardando cliente',
        classes: 'bg-amber-500/15 text-amber-400 border-amber-500/20',
    },
    waiting_internal: {
        label: 'Aguardando interno',
        classes: 'bg-sky-500/15 text-sky-400 border-sky-500/20',
    },
    closed: {
        label: 'Fechado',
        classes: 'bg-neutral-500/15 text-neutral-400 border-neutral-500/20',
    },
    resolved: {
        label: 'Resolvido',
        classes: 'bg-green-500/15 text-green-400 border-green-500/20',
    },
} as const;

export const TICKET_PRIORITIES = {
    low: {
        label: 'Baixa',
        classes: 'bg-neutral-500/15 text-neutral-400 border-neutral-500/20',
    },
    normal: {
        label: 'Normal',
        classes: 'bg-blue-500/15 text-blue-400 border-blue-500/20',
    },
    high: {
        label: 'Alta',
        classes: 'bg-orange-500/15 text-orange-400 border-orange-500/20',
    },
    urgent: {
        label: 'Urgente',
        classes: 'bg-red-500/15 text-red-400 border-red-500/20',
    },
} as const;

export type TicketStatus = keyof typeof TICKET_STATUSES;
export type TicketPriority = keyof typeof TICKET_PRIORITIES;

export function ticketStatusLabel(status: string): string {
    return TICKET_STATUSES[status as TicketStatus]?.label ?? status;
}

export function ticketStatusClasses(status: string): string {
    return (
        TICKET_STATUSES[status as TicketStatus]?.classes ??
        'bg-neutral-500/15 text-neutral-400 border-neutral-500/20'
    );
}

export function ticketPriorityLabel(priority: string): string {
    return TICKET_PRIORITIES[priority as TicketPriority]?.label ?? priority;
}

export function ticketPriorityClasses(priority: string): string {
    return (
        TICKET_PRIORITIES[priority as TicketPriority]?.classes ??
        'bg-neutral-500/15 text-neutral-400 border-neutral-500/20'
    );
}
