import type {
    ActiveConversation,
    ConversationSessionSummary,
    Message,
} from '../resources/js/pages/conversas/types';

const session = (
    id: number,
    number: number,
): ConversationSessionSummary => ({
    id,
    number,
    status: number === 2 ? 'open' : 'closed',
    open_reason:
        number === 1 ? 'first_contact' : 'reengagement_after_inactivity',
    outcome: null,
    value_cents: null,
    collected_information: [],
    opened_at: null,
    closed_at: null,
    last_message_at: null,
    is_returning: number === 2,
});

/**
 * Every shape the thread has to survive: runs of the same author, both sides, all six
 * delivery statuses, a template with buttons, a media chip, and the unbroken wa.me URL
 * that shows up in real campaign sends.
 */
const mensagens: Message[] = [
    {
        id: 1,
        session_id: 1,
        role: 'user',
        content: 'Boa tarde, quero saber da renovação',
        hora: '16:01',
    },
    { id: 2, session_id: 1, role: 'user', content: 'ta', hora: '16:01' },
    {
        id: 3,
        session_id: 1,
        role: 'assistant',
        content: 'Oi! Claro, já verifico aqui pra você.',
        hora: '16:02',
        status: 'read',
    },
    {
        id: 4,
        session_id: 1,
        role: 'assistant',
        content: 'Só um instante.',
        hora: '16:02',
        status: 'delivered',
    },
    {
        id: 5,
        session_id: 2,
        role: 'operator',
        content: 'ta',
        hora: '16:02',
        status: 'read',
    },
    {
        id: 6,
        session_id: 2,
        role: 'operator',
        content:
            'Oii, tudo bem?\nVi que sua renovação ainda está em aberto e consegui uma condição especial para você!\n\nPara pagamento realizado hoje, consigo liberar 10% de desconto no valor da renovação.\n\nSe tiver interesse, clique nesse link para falar com o nosso atendimento:\nhttps://wa.me/5511936186246',
        hora: '19:23',
        status: 'delivered',
    },
    {
        id: 7,
        session_id: 2,
        role: 'operator',
        content: 'Falhou aqui',
        hora: '19:24',
        status: 'failed',
    },
    {
        id: 8,
        session_id: 2,
        role: 'operator',
        content: 'Na fila',
        hora: '19:24',
        status: 'queued',
    },
    {
        id: 9,
        session_id: 2,
        role: 'operator',
        content: 'Template',
        hora: '19:25',
        status: 'sent',
        template: {
            header: { format: 'TEXT', text: 'Renovação em aberto' },
            body: 'Olá! Sua renovação segue disponível com condição especial até amanhã.',
            footer: 'CX PROMOSYS',
            buttons: [
                { type: 'URL', text: 'Falar com atendimento' },
                { type: 'QUICK_REPLY', text: 'Não tenho interesse' },
            ],
        },
    },
    {
        id: 10,
        session_id: 2,
        role: 'user',
        content: 'recebi',
        hora: '19:30',
        media: {
            type: 'audio',
            mime_type: 'audio/ogg',
            local_path: '',
            original_hash: '',
            caption: null,
            duration_secs: 7,
            filename: null,
            size_bytes: 12000,
        },
    },
    { id: 11, session_id: 2, role: 'user', content: 'ok, vou ver', hora: '19:31' },
];

export const conversation: ActiveConversation = {
    lead: {
        id: 1,
        contact_id: null,
        nome: 'Lucas',
        whatsapp: '5511999999999',
        cpf: null,
        idade: null,
        status: 'novo',
        available_transitions: [],
        ai_mode: null,
        effective_ai_mode: 'automatic',
        operational_stage: 'prospect',
        assigned_user_id: 7,
        assigned_user_name: 'Isabele',
        ai_paused_until: null,
        ai_paused_reason: null,
        followup_count: 0,
        followup_status: 'idle',
        agent_niche: 'generic',
        resumo_credito: null,
        collected_information: [],
        notes: null,
    },
    mensagens,
    sessions: [session(1, 1), session(2, 2)],
    pausado: true,
    followupStatus: 'idle',
    followupState: { status: 'idle' } as ActiveConversation['followupState'],
    history: { entries: [], truncated: false, event_retention_days: 30 },
    conversationWindow: null,
    whatsappTemplatesEnabled: false,
    whatsappTemplates: [],
    active_handoff: null,
    handoff_state: 'waiting_customer',
    handoff_actions: [],
    transfer_targets: [],
    contact_lists: [],
    custom_fields: [],
};
