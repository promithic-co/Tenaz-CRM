import { usePage } from '@inertiajs/vue3';

/**
 * Backoffice URLs are built from a prefix the server shares at runtime
 * (config('backoffice.path')), not from Wayfinder. The prefix is configurable
 * per environment via BACKOFFICE_PATH and must never be baked into the JS
 * bundle at build time — Wayfinder resolves absolute paths at build time, which
 * would leak and freeze the secret prefix.
 */
export function useBackofficeRoutes() {
    const page = usePage();

    function base(): string {
        return `/${page.props.backoffice?.path ?? 'backoffice'}`;
    }

    return {
        base,
        index: () => base(),
        agents: () => `${base()}/agentes`,
        agent: (agentId: number | string) => `${base()}/agentes/${agentId}`,
        agentModel: (agentId: number | string) =>
            `${base()}/agentes/${agentId}/modelo`,
        agentTools: (agentId: number | string) =>
            `${base()}/agentes/${agentId}/ferramentas`,
        agentPrompt: (agentId: number | string) =>
            `${base()}/agentes/${agentId}/prompt`,
        templates: () => `${base()}/templates`,
        nicheTemplates: () => `${base()}/modelos`,
        tenants: () => `${base()}/tenants`,
        activeTenant: () => `${base()}/empresa-ativa`,
    };
}
