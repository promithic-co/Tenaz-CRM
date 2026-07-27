/**
 * Shared vocabulary for the tenant's extra lead fields ("Campos adicionais").
 *
 * Both the settings CRUD (configuracoes/campos) and the conversation panel render
 * these, so the type labels and shapes live here instead of in whichever page
 * needed them first.
 */

export type CustomFieldType =
    | 'text'
    | 'number'
    | 'date'
    | 'boolean'
    | 'select'
    | 'json';

export type CustomFieldOption = {
    value: string;
    label: string;
};

export type CustomFieldDefinition = {
    id: number;
    slug: string;
    label: string;
    type: CustomFieldType;
    options: CustomFieldOption[];
    is_required: boolean;
    /** False for `json`: those are written by agent tools, never typed by hand. */
    editable: boolean;
};

/** A definition annotated with the current lead's value, as the panel receives it. */
export type LeadCustomField = CustomFieldDefinition & {
    value: string | number | boolean | Record<string, unknown> | null;
};

export const CUSTOM_FIELD_TYPE_LABELS: Record<CustomFieldType, string> = {
    text: 'Texto',
    number: 'Número',
    date: 'Data',
    boolean: 'Sim / Não',
    select: 'Seleção',
    json: 'JSON (sistema)',
};

export const CUSTOM_FIELD_TYPE_HINTS: Record<CustomFieldType, string> = {
    text: 'Uma linha de texto livre.',
    number: 'Valores numéricos, com ou sem decimais.',
    date: 'Uma data no calendário.',
    boolean: 'Marcar ou desmarcar.',
    select: 'Escolha entre opções que você define.',
    json: 'Preenchido pelas ferramentas do agente.',
};

export function customFieldTypeLabel(type: string): string {
    return CUSTOM_FIELD_TYPE_LABELS[type as CustomFieldType] ?? type;
}
