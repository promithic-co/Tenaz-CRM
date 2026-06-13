import { ref, watch, type Ref } from 'vue';
import { preview as previewAction } from '@/actions/App/Http/Controllers/ContactListController';
import type { FiltersJson } from '@/types/filters';

export type { FiltersJson };

export interface PreviewSampleLead {
    id: number;
    nome: string;
    status: string;
    tags: Array<{ name: string; color: string; is_hot: boolean }>;
}

export interface PreviewState {
    loading: Ref<boolean>;
    count: Ref<number | null>;
    capped: Ref<boolean>;
    sample: Ref<PreviewSampleLead[]>;
    error: Ref<string | null>;
    refresh: () => void;
}

const DEBOUNCE_MS = 500;

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export function useFilterPreview(filters: Ref<FiltersJson>): PreviewState {
    const loading = ref(false);
    const count = ref<number | null>(null);
    const capped = ref(false);
    const sample = ref<PreviewSampleLead[]>([]);
    const error = ref<string | null>(null);
    let timer: ReturnType<typeof setTimeout> | null = null;

    const fetchPreview = async () => {
        if (filters.value.rules.length === 0) {
            count.value = null;
            capped.value = false;
            sample.value = [];
            error.value = null;
            return;
        }

        loading.value = true;
        error.value = null;

        try {
            const response = await fetch(previewAction.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ filters_json: filters.value }),
            });

            if (!response.ok) {
                error.value = 'Não foi possível carregar a prévia. Tente atualizar.';
                return;
            }

            const p = await response.json() as {
                count: number;
                capped: boolean;
                sample: PreviewSampleLead[];
            };

            count.value = p.count;
            capped.value = Boolean(p.capped);
            sample.value = p.sample ?? [];
        } catch {
            error.value = 'Não foi possível carregar a prévia. Tente atualizar.';
        } finally {
            loading.value = false;
        }
    };

    const refresh = () => {
        if (timer) {
            clearTimeout(timer);
        }

        void fetchPreview();
    };

    watch(
        filters,
        () => {
            if (timer) {
                clearTimeout(timer);
            }

            timer = setTimeout(() => void fetchPreview(), DEBOUNCE_MS);
        },
        { deep: true },
    );

    return { loading, count, capped, sample, error, refresh };
}
