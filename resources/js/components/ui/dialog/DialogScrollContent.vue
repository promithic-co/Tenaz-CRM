<script setup lang="ts">
import { reactiveOmit } from '@vueuse/core';
import { X } from 'lucide-vue-next';
import type { DialogContentEmits, DialogContentProps } from 'reka-ui';
import {
    DialogClose,
    DialogContent,
    DialogOverlay,
    DialogPortal,
    useForwardPropsEmits,
} from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps<
    DialogContentProps & { class?: HTMLAttributes['class'] }
>();
const emits = defineEmits<DialogContentEmits>();

const delegatedProps = reactiveOmit(props, 'class');

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <DialogPortal>
        <DialogOverlay
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/80 p-2 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 sm:p-4"
        >
            <DialogContent
                :class="
                    cn(
                        'relative z-50 my-2 grid max-h-[calc(100svh-1rem)] w-full max-w-lg gap-4 overflow-y-auto overscroll-contain border border-border bg-background p-4 shadow-lg duration-200 sm:my-8 sm:max-h-[calc(100svh-2rem)] sm:rounded-lg sm:p-6 md:w-full',
                        props.class,
                    )
                "
                v-bind="{ ...$attrs, ...forwarded }"
                @pointer-down-outside="
                    (event) => {
                        const originalEvent = event.detail.originalEvent;
                        const target = originalEvent.target as HTMLElement;
                        if (
                            originalEvent.offsetX > target.clientWidth ||
                            originalEvent.offsetY > target.clientHeight
                        ) {
                            event.preventDefault();
                        }
                    }
                "
            >
                <slot />

                <DialogClose
                    class="absolute top-2 right-2 flex size-10 items-center justify-center rounded-md transition-colors hover:bg-secondary sm:top-4 sm:right-4 sm:size-8"
                >
                    <X class="h-4 w-4" />
                    <span class="sr-only">Close</span>
                </DialogClose>
            </DialogContent>
        </DialogOverlay>
    </DialogPortal>
</template>
