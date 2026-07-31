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

        laboratory: () => `${base()}/laboratory`,
        laboratoryDatasetsPage: () => `${base()}/laboratory/datasets-page`,
        laboratoryStressTest: () => `${base()}/laboratory/stress-test`,
        laboratoryStressTestResults: (runId: number | string) =>
            `${base()}/laboratory/stress-test/${runId}`,
        laboratoryAiUsage: () => `${base()}/laboratory/ai-usage`,
        laboratoryHealth: () => `${base()}/laboratory/health`,

        datasets: () => `${base()}/laboratory/datasets`,
        dataset: (datasetId: number | string) =>
            `${base()}/laboratory/datasets/${datasetId}`,
        datasetPrefetch: (datasetId: number | string) =>
            `${base()}/laboratory/datasets/${datasetId}/prefetch`,
        stressTests: () => `${base()}/laboratory/stress-tests`,
        stressTestCancel: (runId: number | string) =>
            `${base()}/laboratory/stress-tests/${runId}/cancel`,

        playground: () => `${base()}/playground`,
        playgroundSession: (leadId: number | string) =>
            `${base()}/playground/${leadId}`,
        playgroundMessages: (leadId: number | string) =>
            `${base()}/playground/${leadId}/messages`,
        playgroundReset: (leadId: number | string) =>
            `${base()}/playground/${leadId}/reset`,
        playgroundPrompt: (leadId: number | string) =>
            `${base()}/playground/${leadId}/prompt`,
        playgroundChat: (leadId: number | string) =>
            `${base()}/playground/${leadId}/chat`,
        playgroundTesterChat: (leadId: number | string) =>
            `${base()}/playground/${leadId}/tester-chat`,
        playgroundEvaluate: (leadId: number | string) =>
            `${base()}/playground/${leadId}/evaluate`,
        playgroundGenerateScenario: () =>
            `${base()}/playground/generate-scenario`,
        playgroundScanBlindspots: () => `${base()}/playground/scan-blindspots`,
    };
}
