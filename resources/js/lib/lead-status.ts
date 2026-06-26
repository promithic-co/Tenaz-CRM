export const LEAD_STATUSES = {
    novo:           { label: 'Novo',           classes: 'bg-blue-500/15 text-blue-400 border-blue-500/20' },
    qualificado:    { label: 'Qualificado',    classes: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/20' },
    sem_credito:    { label: 'Sem Crédito',    classes: 'bg-amber-500/15 text-amber-400 border-amber-500/20' },
    desqualificado: { label: 'Desqualificado', classes: 'bg-red-500/15 text-red-400 border-red-500/20' },
    escalado:       { label: 'Escalado',       classes: 'bg-purple-500/15 text-purple-400 border-purple-500/20' },
    convertido:     { label: 'Convertido',     classes: 'bg-green-500/15 text-green-400 border-green-500/20' },
    optou_sair:     { label: 'Optou por Sair', classes: 'bg-neutral-500/15 text-neutral-400 border-neutral-500/20' },
} as const;

export type LeadStatus = keyof typeof LEAD_STATUSES;

export function statusLabel(status: string): string {
    return LEAD_STATUSES[status as LeadStatus]?.label ?? status;
}

export function statusClasses(status: string): string {
    return LEAD_STATUSES[status as LeadStatus]?.classes ?? 'bg-neutral-500/15 text-neutral-400 border-neutral-500/20';
}
