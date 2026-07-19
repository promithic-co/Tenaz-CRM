const dateTimeFormatter = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

/**
 * Format an ISO-8601 timestamp (as serialized by Laravel) into a human-readable
 * `dd/mm/yyyy hh:mm` string in the viewer's local timezone. Returns null for empty
 * input and echoes the original string back if it can't be parsed.
 */
export function formatDateTime(value: string | null | undefined): string | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return dateTimeFormatter.format(date);
}

/**
 * Format an ISO-8601 timestamp into a `dd/mm/yyyy` date without the time portion.
 */
export function formatDate(value: string | null | undefined): string | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return dateFormatter.format(date);
}
