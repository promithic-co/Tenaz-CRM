<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import TagChip from '@/components/TagChip.vue';

type Tag = {
    id: number;
    name: string;
    slug?: string;
    color?: string | null;
    is_hot?: boolean;
    usage_count?: number;
};

type Props = {
    modelValue: Tag[];
    placeholder?: string;
    disabled?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Adicionar tag…',
    disabled: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: Tag[]): void;
    (e: 'create', name: string): void;
}>();

const input = ref('');
const suggestions = ref<Tag[]>([]);
const showDropdown = ref(false);
const activeIndex = ref<number>(-1);
const debounceTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const containerRef = ref<HTMLElement | null>(null);

const selectedIds = computed(() => new Set(props.modelValue.map((t) => t.id)));

async function fetchSuggestions(term: string): Promise<void> {
    try {
        const url = term.trim().length > 0
            ? `/tags?q=${encodeURIComponent(term)}`
            : '/tags?popular=1';
        const res = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            suggestions.value = [];
            return;
        }
        const payload = await res.json();
        const list: Tag[] = Array.isArray(payload?.data) ? payload.data : [];
        suggestions.value = list.filter((t) => !selectedIds.value.has(t.id)).slice(0, 8);
    } catch {
        suggestions.value = [];
    }
}

function onInput(): void {
    showDropdown.value = true;
    activeIndex.value = -1;
    if (debounceTimer.value) {
        clearTimeout(debounceTimer.value);
    }
    debounceTimer.value = setTimeout(() => {
        void fetchSuggestions(input.value);
    }, 200);
}

function openDropdown(): void {
    showDropdown.value = true;
    void fetchSuggestions(input.value);
}

function closeDropdown(): void {
    showDropdown.value = false;
    activeIndex.value = -1;
}

function selectTag(tag: Tag): void {
    if (selectedIds.value.has(tag.id)) {
        return;
    }
    emit('update:modelValue', [...props.modelValue, tag]);
    input.value = '';
    suggestions.value = [];
    closeDropdown();
}

function removeTag(tag: Tag): void {
    emit('update:modelValue', props.modelValue.filter((t) => t.id !== tag.id));
}

function createFromInput(): void {
    const name = input.value.trim();
    if (!name) {
        return;
    }
    emit('create', name);
    input.value = '';
    closeDropdown();
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (!showDropdown.value) {
            openDropdown();
        }
        if (suggestions.value.length > 0) {
            activeIndex.value = (activeIndex.value + 1) % suggestions.value.length;
        }
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (suggestions.value.length > 0) {
            activeIndex.value = activeIndex.value <= 0 ? suggestions.value.length - 1 : activeIndex.value - 1;
        }
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        if (activeIndex.value >= 0 && activeIndex.value < suggestions.value.length) {
            selectTag(suggestions.value[activeIndex.value]);
        } else if (input.value.trim().length > 0) {
            createFromInput();
        }
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        closeDropdown();
        return;
    }

    if (event.key === 'Backspace' && input.value === '' && props.modelValue.length > 0) {
        const last = props.modelValue[props.modelValue.length - 1];
        removeTag(last);
    }
}

function onDocumentClick(event: MouseEvent): void {
    if (!containerRef.value) {
        return;
    }
    if (!containerRef.value.contains(event.target as Node)) {
        closeDropdown();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    if (debounceTimer.value) {
        clearTimeout(debounceTimer.value);
    }
});

watch(() => props.modelValue, async () => {
    if (showDropdown.value) {
        await nextTick();
        await fetchSuggestions(input.value);
    }
});
</script>

<template>
    <div ref="containerRef" class="relative">
        <div
            class="flex flex-wrap items-center gap-1.5 rounded-md border border-input bg-background px-2 py-1.5 focus-within:ring-1 focus-within:ring-ring"
            :class="{ 'opacity-60': disabled }"
        >
            <TagChip
                v-for="tag in modelValue"
                :key="tag.id"
                :tag="tag"
                removable
                @remove="removeTag"
            />
            <input
                v-model="input"
                type="text"
                :placeholder="modelValue.length === 0 ? placeholder : ''"
                :disabled="disabled"
                class="min-w-[8ch] flex-1 bg-transparent text-sm placeholder:text-muted-foreground focus:outline-none"
                @input="onInput"
                @focus="openDropdown"
                @keydown="onKeydown"
            />
        </div>

        <ul
            v-if="showDropdown && (suggestions.length > 0 || input.trim().length > 0)"
            class="absolute z-30 mt-1 w-full max-w-md overflow-hidden rounded-md border border-input bg-popover shadow-md"
            role="listbox"
        >
            <li
                v-for="(suggestion, idx) in suggestions"
                :key="suggestion.id"
                role="option"
                :aria-selected="idx === activeIndex"
                :class="[
                    'flex cursor-pointer items-center justify-between px-3 py-1.5 text-sm',
                    idx === activeIndex ? 'bg-muted' : 'hover:bg-muted/60',
                ]"
                @mousedown.prevent="selectTag(suggestion)"
            >
                <TagChip :tag="suggestion" />
                <span v-if="suggestion.usage_count" class="text-[10px] text-muted-foreground">
                    {{ suggestion.usage_count }}
                </span>
            </li>
            <li
                v-if="input.trim().length > 0 && !suggestions.some((s) => s.name.toLowerCase() === input.trim().toLowerCase())"
                role="option"
                class="flex cursor-pointer items-center gap-2 border-t border-input/40 px-3 py-1.5 text-sm hover:bg-muted/60"
                @mousedown.prevent="createFromInput"
            >
                <span class="text-muted-foreground">Criar</span>
                <span class="font-medium">"{{ input.trim() }}"</span>
            </li>
        </ul>
    </div>
</template>
