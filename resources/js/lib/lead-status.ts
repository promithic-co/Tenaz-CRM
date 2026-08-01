// Each status carries a light-mode pair and a dark-mode pair. The -400 text
// stops are tuned for dark surfaces only; on the white card backgrounds they
// land around 1.9:1, so light mode uses the -700 stop over a lighter /10 fill.
export const LEAD_STATUSES = {
    novo: {
        label: 'Novo',
        classes:
            'border-blue-500/20 bg-blue-500/10 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400',
    },
    qualificado: {
        label: 'Qualificado',
        classes:
            'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
    },
    sem_credito: {
        label: 'Sem Crédito',
        classes:
            'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
    },
    desqualificado: {
        label: 'Desqualificado',
        classes:
            'border-red-500/20 bg-red-500/10 text-red-700 dark:bg-red-500/15 dark:text-red-400',
    },
    escalado: {
        label: 'Escalado',
        classes:
            'border-purple-500/20 bg-purple-500/10 text-purple-700 dark:bg-purple-500/15 dark:text-purple-400',
    },
    convertido: {
        label: 'Convertido',
        classes:
            'border-green-500/20 bg-green-500/10 text-green-700 dark:bg-green-500/15 dark:text-green-400',
    },
    optou_sair: {
        label: 'Optou por Sair',
        classes:
            'border-neutral-500/20 bg-neutral-500/10 text-neutral-700 dark:bg-neutral-500/15 dark:text-neutral-400',
    },
} as const;

const FALLBACK_CLASSES =
    'border-neutral-500/20 bg-neutral-500/10 text-neutral-700 dark:bg-neutral-500/15 dark:text-neutral-400';

export type LeadStatus = keyof typeof LEAD_STATUSES;

export function statusLabel(status: string): string {
    return LEAD_STATUSES[status as LeadStatus]?.label ?? status;
}

export function statusClasses(status: string): string {
    return LEAD_STATUSES[status as LeadStatus]?.classes ?? FALLBACK_CLASSES;
}
