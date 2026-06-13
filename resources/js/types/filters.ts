export type FilterField =
    | 'tags'
    | 'tag_is_hot'
    | 'tag_source'
    | 'status'
    | 'agent_id'
    | 'whatsapp_instance_id'
    | 'last_interaction_at'
    | 'created_at'
    | 'has_open_ticket';

export type FilterOp =
    | 'includes_all'
    | 'includes_any'
    | 'excludes'
    | 'eq'
    | 'ne'
    | 'in'
    | 'not_in'
    | 'older_than_days'
    | 'within_last_days'
    | 'contains';

export interface FilterTag {
    id: number;
    name: string;
    slug?: string;
    color?: string | null;
    is_hot?: boolean;
}

export type FilterValue =
    | string
    | number
    | boolean
    | null
    | string[]
    | number[]
    | FilterTag[];

export interface FilterRule {
    field: FilterField;
    op: FilterOp;
    value: FilterValue;
}

export interface FiltersJson {
    version: 1;
    match: 'all' | 'any';
    rules: FilterRule[];
}
