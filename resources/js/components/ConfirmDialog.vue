<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { buttonClasses } from '@/components/buttons';
import { useTrans } from '@/composables/useTrans';

/**
 * Explicit confirmation of an action with effects that are hard to undo, with a summary of the
 * effect (ux-architettura §4.5, "Conferme": e.g. "Disattiva utente").
 * Modal dialog: focus on "Annulla" when it opens, Escape or a click outside closes it,
 * focus back to the button that opened it when it closes.
 */
const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        confirmLabel: string;
        processing?: boolean;
    }>(),
    { processing: false },
);

const emit = defineEmits<{ confirm: []; cancel: [] }>();

const { t } = useTrans();

const cancelButton = ref<HTMLButtonElement | null>(null);
let opener: HTMLElement | null = null;

function cancel(): void {
    if (!props.processing) {
        emit('cancel');
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        cancel();
    }
}

watch(
    () => props.open,
    async (open) => {
        if (open) {
            opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            document.addEventListener('keydown', onKeydown);
            await nextTick();
            cancelButton.value?.focus();
        } else {
            document.removeEventListener('keydown', onKeydown);
            opener?.focus();
            opener = null;
        }
    },
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
            @click="cancel"
        />
        <section
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="confirm-dialog-title"
            aria-describedby="confirm-dialog-body"
            class="relative w-full max-w-md rounded-[14px] bg-white p-6 shadow-xl"
        >
            <h2
                id="confirm-dialog-title"
                class="text-lg font-semibold"
            >
                {{ title }}
            </h2>
            <div
                id="confirm-dialog-body"
                class="mt-2 flex flex-col gap-2 text-sm leading-relaxed text-ink-soft"
            >
                <slot />
            </div>
            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    ref="cancelButton"
                    type="button"
                    :class="buttonClasses.secondary"
                    :disabled="processing"
                    @click="cancel"
                >
                    {{ t('common.cancel') }}
                </button>
                <button
                    type="button"
                    :class="buttonClasses.primary"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </button>
            </div>
        </section>
    </div>
</template>
