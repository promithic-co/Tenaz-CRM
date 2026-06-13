/**
 * Minimal relative-time helper for pt_BR.
 * Returns strings like "há 2 dias", "há 1 hora", "há 3 minutos".
 * No external library needed — @vueuse/core does not ship formatRelative.
 */
export function formatRelative(iso: string): string {
    const now = Date.now();
    const then = new Date(iso).getTime();
    const diffMs = now - then;
    const diffSec = Math.floor(diffMs / 1000);

    if (diffSec < 60) {
        return 'agora mesmo';
    }

    const diffMin = Math.floor(diffSec / 60);

    if (diffMin < 60) {
        return diffMin === 1 ? 'há 1 minuto' : `há ${diffMin} minutos`;
    }

    const diffHr = Math.floor(diffMin / 60);

    if (diffHr < 24) {
        return diffHr === 1 ? 'há 1 hora' : `há ${diffHr} horas`;
    }

    const diffDay = Math.floor(diffHr / 24);

    if (diffDay < 30) {
        return diffDay === 1 ? 'há 1 dia' : `há ${diffDay} dias`;
    }

    const diffMonth = Math.floor(diffDay / 30);

    if (diffMonth < 12) {
        return diffMonth === 1 ? 'há 1 mês' : `há ${diffMonth} meses`;
    }

    const diffYear = Math.floor(diffMonth / 12);

    return diffYear === 1 ? 'há 1 ano' : `há ${diffYear} anos`;
}
