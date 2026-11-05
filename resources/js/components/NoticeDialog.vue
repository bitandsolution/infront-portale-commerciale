<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { buttonClasses } from '@/components/buttons';

/**
 * Blocking message with a single button (ux-architettura §4.5, "Cliente duplicato": dialog
 * bloccante). Same behaviour as ConfirmDialog: focus on the button when it opens, Escape or a click
 * outside closes it, focus back to the element that opened it.
 */
const props = defineProps<{
    open: boolean;
    title: string;
    closeLabel: string;
}>();

const emit = defineEmits<{ close: [] }>();

const closeButton = ref<HTMLButtonElement | null>(null);
let opener: HTMLElement | null = null;

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        emit('close');
    }
}

watch(
    () => props.open,
    async (open) => {
        if (open) {
            opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            document.addEventListener('keydown', onKeydown);
            await nextTick();
            closeButton.value?.focus();
        } else {
            document.removeEventListener('keydown', onKeydown);
            opener?.focus();
            opener = null;
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
    >
        <div
            class="absolute inset-0 bg-ink/45"
            aria-hidden="true"
            @click="emit('close')"
        />
        <section
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="notice-dialog-title"
            aria-describedby="notice-dialog-body"
            class="relative w-full max-w-md rounded-[14px] bg-white p-6 shadow-xl"
        >
            <h2
                id="notice-dialog-title"
                class="text-lg font-semibold"
            >
                {{ title }}
            </h2>
            <div
                id="notice-dialog-body"
                class="mt-2 flex flex-col gap-2 text-sm leading-relaxed text-ink-soft"
            >
                <slot />
            </div>
            <div class="mt-6 flex justify-end">
                <button
                    ref="closeButton"
                    type="button"
                    :class="buttonClasses.primary"
                    @click="emit('close')"
                >
                    {{ closeLabel }}
                </button>
            </div>
        </section>
    </div>
</template>
