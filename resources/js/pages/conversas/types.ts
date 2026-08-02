import type { LeadCustomField } from '@/lib/custom-fields';
import type { CollectedInformationItem, FollowupState } from '@/types/models';

export type { FollowupState };
export type { LeadCustomField };

export type MediaAttachment = {
    type: 'audio' | 'image' | 'document' | 'video' | 'sticker' | 'unknown';
    mime_type: string;
    local_path: string;
    original_hash: string;
    caption: string | null;
    duration_secs: number | null;
    filename: string | null;
    size_bytes: number;
};

export type TemplateButton = {
    type: string;
    text: string;
};

export type TemplateSnapshot = {
    header: { format: string; text?: string } | null;
    body: string | null;
    footer: string | null;
    buttons: TemplateButton[];
};

export type Message = {
    id?: number;
    session_id?: number | null;
    role: 'user' | 'assistant' | 'operator';
    content: string;
    hora: string;
    media?: MediaAttachment | null;
    status?: string;
    template?: TemplateSnapshot | null;
};

export type ConversationSessionStatus = 'open' | 'closed';

export type ConversationSessionOutcome =
    | 'converted'
    | 'lost'
    | 'no_response'
    | 'abandoned'
    | 'manual_close';

export type ConversationSessionOpenReason =
    | 'first_contact'
    | 'reengagement_after_terminal'
    | 'reengagement_after_inactivity'
    | 'campaign'
    | 'manual';

export type ConversationSessionSummary = {
    id: number;
    number: number;
    status: ConversationSessionStatus;
    open_reason: ConversationSessionOpenReason;
    outcome: ConversationSessionOutcome | null;
    /** Negotiated amount in cents — historical only, no longer editable from the panel. */
    value_cents: number | null;
    /** Free-form entries scoped to this atendimento; frozen when the cycle closes. */
    collected_information: CollectedInformationItem[];
    opened_at: string | null;
    closed_at: string | null;
    last_message_at: string | null;
    is_returning: boolean;
};

export type InboxLead = {
    id: number;
    nome: string;
    whatsapp: string;
    status: string;
    followup_status: string;
    followup_count: number;
    ai_mode: string | null;
    effective_ai_mode: string;
    operational_stage: string;
    assigned_user_id?: number | null;
    assigned_user_name: string | null;
    ultima_interacao: string | null;
    pausado: boolean;
    evolution_instance: string | null;
    is_returning: boolean;
    last_message_body: string | null;
    last_message_direction: string | null;
    /** The customer spoke last — nobody has answered yet. */
    awaiting_reply: boolean;
};

export type InboxGroup = 'todas' | 'fila' | 'minhas' | 'ia' | 'envios';

export type InboxGroupCounts = Record<Exclude<InboxGroup, 'todas'>, number>;

export type ConversationLead = {
    id: number;
    agent_id?: number | null;
    contact_id: number | null;
    nome: string;
    whatsapp: string;
    cpf: string | null;
    idade: number | null;
    status: string;
    available_transitions: string[];
    ai_mode: string | null;
    effective_ai_mode: string;
    operational_stage: string;
    assigned_user_id: number | null;
    assigned_user_name: string | null;
    ai_paused_until: string | null;
    ai_paused_reason: string | null;
    followup_count: number;
    followup_status: string;
    agent_niche: string;
    resumo_credito: string | null;
    collected_information: CollectedInformationItem[];
    /** Free-text operator notes, stored on the linked Contact. */
    notes: string | null;
    tags?: Array<{
        id: number;
        name: string;
        slug: string;
        color?: string | null;
        source?: 'manual' | 'ai' | 'import' | 'system' | null;
        ai_confidence?: number | null;
    }>;
};

/**
 * One line of the merged conversation history. Labels and details are composed
 * server-side by ConversationHistoryBuilder, so the panel only has to render and
 * colour them — the whitelist of visible event types and its wording live in one
 * place instead of being split across PHP and Vue.
 */
export type HistoryEntry = {
    id: string;
    kind: 'event' | 'session' | 'ticket' | 'followup';
    type: string;
    label: string;
    detail: string | null;
    severity: 'info' | 'warning' | 'error' | 'success';
    at: string;
};

export type ConversationHistory = {
    entries: HistoryEntry[];
    truncated: boolean;
    /** Agent events are pruned after this many days; lifecycle entries are not. */
    event_retention_days: number;
};

export type ConversationWindowStatus = {
    service_window: {
        status: 'open' | 'closed' | 'unknown';
        remaining_seconds: number | null;
        expires_at: string | null;
    };
    template_required: boolean;
    free_entry_point: {
        status: 'active' | 'expired' | 'unknown';
        remaining_seconds: number | null;
        source: string | null;
        expires_at: string | null;
    };
    coexistence: {
        enabled: boolean;
        note: string | null;
    };
};

export type WhatsappTemplateField = {
    path: string;
    component: 'header' | 'body' | 'button';
    type: string;
    label: string;
    example: string | null;
    required: boolean;
    /** Value the server resolved from the lead; when set, the operator is not asked for it. */
    resolved: string | null;
};

export type WhatsappTemplateOption = {
    id: number;
    name: string;
    language: string | null;
    category: string | null;
    fields: WhatsappTemplateField[];
    preview: string;
    last_synced_at: string | null;
};

export type ActiveHandoff = {
    id: number;
    type: string;
    status: string;
    reason: string | null;
    summary: string | null;
    priority: string;
    sla_due_at: string | null;
    sla_overdue: boolean;
    assigned_user_id: number | null;
    assigned_user_name: string | null;
    claimed_at: string | null;
    first_response_at: string | null;
};

export type TransferTarget = {
    type: 'user';
    id: number;
    name: string;
};

/** A static contact list the conversation can be added to. Dynamic lists are excluded. */
export type ContactListOption = {
    id: number;
    name: string;
    entries_count: number;
};

export type ActiveConversation = {
    lead: ConversationLead;
    mensagens: Message[];
    sessions: ConversationSessionSummary[];
    pausado: boolean;
    followupStatus: string;
    followupState: FollowupState;
    history: ConversationHistory;
    conversationWindow?: ConversationWindowStatus | null;
    whatsappTemplatesEnabled?: boolean;
    whatsappTemplates?: WhatsappTemplateOption[];
    templateSync?: { count: number; synced_at: string | null } | null;
    active_handoff: ActiveHandoff | null;
    handoff_state:
        | 'waiting_human'
        | 'human_active'
        | 'waiting_customer'
        | 'ai_active'
        | 'closed';
    handoff_actions: string[];
    transfer_targets: TransferTarget[];
    contact_lists: ContactListOption[];
    /** The tenant's extra lead fields, each with this lead's current value. */
    custom_fields: LeadCustomField[];
};

export type ConversationFilters = {
    group: InboxGroup;
    status: string;
    instance: string;
    search: string;
    sort: string;
    direction: string;
    ai_mode: string;
    stage: string;
    assigned: string;
};

export type LeadPaginator = {
    data: InboxLead[];
    total: number;
    current_page: number;
    next_page_url: string | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};
