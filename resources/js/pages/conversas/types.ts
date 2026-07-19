import type { CollectedInformationItem } from '@/types/models';

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

export type Message = {
    id?: number;
    role: 'user' | 'assistant' | 'operator';
    content: string;
    hora: string;
    media?: MediaAttachment | null;
    status?: string;
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
};

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
    tags?: Array<{
        id: number;
        name: string;
        slug: string;
        color?: string | null;
        source?: 'manual' | 'ai' | 'import' | 'system' | null;
        ai_confidence?: number | null;
    }>;
};

export type FollowupHistoryItem = {
    attempt: number;
    message_text: string;
    tone: string | null;
    sent_at: string;
};

export type AuditEvent = {
    event_type: string;
    created_at: string;
    severity: string;
    payload_json: Record<string, unknown> | null;
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

export type ActiveConversation = {
    lead: ConversationLead;
    mensagens: Message[];
    pausado: boolean;
    followupStatus: string;
    followupHistory: FollowupHistoryItem[];
    recentEvents: AuditEvent[];
    canStartCampaign: boolean;
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
};

export type ConversationFilters = {
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
    links: Array<{ url: string | null; label: string; active: boolean }>;
};
