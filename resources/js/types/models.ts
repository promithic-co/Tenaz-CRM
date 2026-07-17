import type { LeadStatus } from '@/lib/lead-status';

export type CollectedInformationItem = {
    key: string;
    label: string;
    value: string;
    source: 'manual' | 'ai';
};

export interface CreditoJson {
    status?: string;
    margem_disponivel?: number;
    produtos?: CreditoProduto[];
    [key: string]: unknown;
}

export interface CreditoProduto {
    banco?: string;
    tipo?: string;
    valor?: number;
    parcelas?: number;
    parcela?: number;
    [key: string]: unknown;
}

export interface Lead {
    id: number;
    nome: string | null;
    whatsapp: string;
    cpf: string | null;
    idade: number | null;
    status: LeadStatus;
    ai_mode:
        | 'automatic'
        | 'manual'
        | 'assisted'
        | 'qualify_then_handoff'
        | null;
    effective_ai_mode?:
        | 'automatic'
        | 'manual'
        | 'assisted'
        | 'qualify_then_handoff';
    operational_stage?: string;
    assigned_user_id?: number | null;
    assigned_user_name?: string | null;
    ai_paused_until?: string | null;
    ai_paused_reason?: string | null;
    credito_json: CreditoJson | null;
    documentos_coletados: string[] | null;
    followup_count: number;
    followup_status: 'active' | 'completed' | 'paused' | null;
    last_interaction_at: string | null;
    agent_id: number;
    agent?: Agent;
    is_paused?: boolean;
    created_at: string;
    updated_at: string;
}

export interface Agent {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    is_default: boolean;
    config?: AgentConfig;
    whatsapp_instance?: WhatsappInstance;
    leads_count?: number;
    active_conversations?: number;
    converted_count?: number;
    conversion_rate?: number;
}

export interface AgentConfig {
    id: number;
    agent_id: number;
    agent_name: string;
    company_name: string;
    agent_personality: string | null;
    max_chars: number;
    agent_greeting: string | null;
    required_docs: string | null;
    extra_rules: string | null;
    agent_provider: string | null;
    agent_model: string | null;
    temperature: number;
}

export interface WhatsappInstance {
    id: number;
    name: string;
    display_name: string;
    phone_number: string | null;
    agent_id: number | null;
    default_ai_mode?:
        | 'automatic'
        | 'manual'
        | 'assisted'
        | 'qualify_then_handoff';
    api_url: string | null;
}

export interface ServiceTicket {
    id: number;
    lead_id: number;
    type: string;
    status: string;
    reason: string | null;
    summary: string | null;
    credit_available: number | null;
    chosen_product: string | null;
    total_value: number | null;
    installment_value: number | null;
    observations: string | null;
    lead?: Lead;
    hours_open?: number;
    urgency?: 'low' | 'medium' | 'high';
    created_at: string;
    updated_at: string;
}
