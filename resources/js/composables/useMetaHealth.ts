/**
 * Presentation for Meta's messaging health status.
 *
 * `AVAILABLE`, `LIMITED` and `BLOCKED` come straight from the Graph API's
 * `health_status.can_send_message`. `UNKNOWN` is ours: it means the probe
 * failed or has not run yet, and must never be styled as healthy.
 */
export type MetaHealthStatus = 'AVAILABLE' | 'LIMITED' | 'BLOCKED' | 'UNKNOWN';

export type MetaHealthEntity = {
    type: string;
    id: string;
    status: MetaHealthStatus;
    reasons: string[];
};

export type MetaAccountAlert = {
    entity_type: string | null;
    entity_id: string | null;
    type: string | null;
    severity: string | null;
    status: string | null;
    description: string | null;
    received_at: string | null;
};

export type MetaAccountRestriction = {
    type: string;
    expires_at: string | null;
};

/** Health props every instance payload carries, from WhatsAppInstanceController. */
export type MetaHealthProps = {
    health_status: MetaHealthStatus;
    health_reasons: string[];
    health_entities: MetaHealthEntity[];
    health_checked_at: string | null;
    meta_name_status: string | null;
    meta_code_verification_status: string | null;
    meta_portfolio_messaging_limit: string | null;
    meta_throughput_level: string | null;
    meta_number_status: string | null;
    meta_ban_state: string | null;
    meta_account_review_status: string | null;
    meta_account_alerts: MetaAccountAlert[];
    meta_account_restrictions: MetaAccountRestriction[];
};

type Chip = {
    label: string;
    /** Border + background + text, for bordered chips. */
    class: string;
    /** Solid colour for the status dot on the card. */
    dot: string;
};

const CHIPS: Record<MetaHealthStatus, Chip> = {
    AVAILABLE: {
        label: 'Conectado',
        class: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400',
        dot: 'bg-emerald-400',
    },
    LIMITED: {
        label: 'Limitado',
        class: 'border-amber-500/30 bg-amber-500/10 text-amber-400',
        dot: 'bg-amber-400',
    },
    BLOCKED: {
        label: 'Bloqueado',
        class: 'border-red-500/30 bg-red-500/10 text-red-400',
        dot: 'bg-red-400',
    },
    UNKNOWN: {
        label: 'Sem dados',
        class: 'border-border bg-muted text-muted-foreground',
        dot: 'bg-muted-foreground',
    },
};

const ENTITY_LABELS: Record<string, string> = {
    PHONE_NUMBER: 'Número',
    WABA: 'Conta WhatsApp (WABA)',
    BUSINESS: 'Portfólio empresarial',
    APP: 'Aplicativo',
    MESSAGE_TEMPLATE: 'Template',
};

const NAME_STATUS_LABELS: Record<string, string> = {
    APPROVED: 'Aprovado',
    AVAILABLE_WITHOUT_REVIEW: 'Disponível sem revisão',
    DECLINED: 'Recusado',
    REJECTED: 'Recusado',
    EXPIRED: 'Expirado',
    PENDING_REVIEW: 'Em revisão',
    DEFERRED: 'Adiado',
    NONE: 'Não enviado',
};

const RESTRICTION_LABELS: Record<string, string> = {
    RESTRICTED_BIZ_INITIATED_MESSAGING:
        'Não pode iniciar conversas com clientes',
    RESTRICTED_CUSTOMER_INITIATED_MESSAGING:
        'Não pode responder mensagens de clientes',
    RESTRICTED_ADD_PHONE_NUMBER_ACTION: 'Não pode adicionar novos números',
    RESTRICTED_UTILITY_TEMPLATES: 'Não pode criar templates utilitários',
    RESTRICTED_DIRECT_SEND_UTILITY_TEMPLATES:
        'Não pode enviar templates utilitários via Direct Send',
};

export function healthStatusOf(
    value: string | null | undefined,
): MetaHealthStatus {
    const normalized = (value ?? '').toUpperCase();

    return normalized === 'AVAILABLE' ||
        normalized === 'LIMITED' ||
        normalized === 'BLOCKED'
        ? normalized
        : 'UNKNOWN';
}

export function healthChip(value: string | null | undefined): Chip {
    return CHIPS[healthStatusOf(value)];
}

export function entityLabel(type: string): string {
    return ENTITY_LABELS[type.toUpperCase()] ?? type;
}

export function nameStatusLabel(
    value: string | null | undefined,
): string | null {
    if (!value) {
        return null;
    }

    return NAME_STATUS_LABELS[value.toUpperCase()] ?? value;
}

export function restrictionLabel(type: string): string {
    return RESTRICTION_LABELS[type.toUpperCase()] ?? type;
}

/**
 * `TIER_2K` reads as "2.000 conversas/24h".
 *
 * "Conversas", not "mensagens": the limit counts unique WhatsApp users the
 * business starts a conversation with in a rolling 24 hours, not messages sent.
 * Since 2025-10-07 the ceiling belongs to the business portfolio and is shared
 * by every number in it — the callers say so next to this number.
 */
export function portfolioLimitLabel(
    value: string | null | undefined,
): string | null {
    if (!value) {
        return null;
    }

    const tier = value.toUpperCase().replace(/^TIER_/, '');

    if (tier === 'UNLIMITED') {
        return 'Ilimitado';
    }

    const match = tier.match(/^(\d+)(K|M)?$/);

    if (!match) {
        return `${tier} conversas/24h`;
    }

    const multiplier =
        match[2] === 'M' ? 1_000_000 : match[2] === 'K' ? 1_000 : 1;

    return `${(Number(match[1]) * multiplier).toLocaleString('pt-BR')} conversas/24h`;
}

/** Relative "há X" for the last successful probe. */
export function checkedAtLabel(
    value: string | null | undefined,
): string | null {
    if (!value) {
        return null;
    }

    const checkedAt = new Date(value);

    if (Number.isNaN(checkedAt.getTime())) {
        return null;
    }

    const minutes = Math.floor((Date.now() - checkedAt.getTime()) / 60000);

    if (minutes < 1) {
        return 'agora mesmo';
    }

    if (minutes < 60) {
        return `há ${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `há ${hours} h`;
    }

    return `há ${Math.floor(hours / 24)} d`;
}
