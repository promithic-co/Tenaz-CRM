import type { MetaHealthProps } from '@/composables/useMetaHealth';

/**
 * One row of WhatsAppInstanceController@index / @health.
 *
 * Shared by the WhatsApp page, the card and the details drawer — the three used
 * to carry three hand-maintained copies of this shape.
 */
export type WhatsappInstanceSummary = MetaHealthProps & {
    id: number;
    name: string;
    display_name: string | null;
    label: string;
    api_url: string;
    phone_number: string | null;
    provider: 'meta_cloud';

    meta_waba_id: string | null;
    meta_phone_number_id: string | null;
    meta_quality_rating: string | null;
    meta_token_permanent: boolean;
    meta_token_expires_at: string | null;
    meta_coexistence: boolean;
    /** Whether a two-step PIN is on file. The PIN itself never leaves the server. */
    has_registration_pin: boolean;

    agent_id: number | null;
    agent_name: string | null;
    default_ai_mode: string | null;

    leads_count: number;

    has_proxy: boolean;
    proxy_host: string | null;
    proxy_port: number | null;
};
