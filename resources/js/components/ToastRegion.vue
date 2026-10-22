<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { onBeforeUnmount, onMounted, watch } from 'vue';
import { useToasts } from '@/composables/useToasts';
import { useTrans } from '@/composables/useTrans';

/**
 * Toast area (ux-architettura §4.5). Shows the toasts flashed by the server (props.toast,
 * e.g. a system error with its support code, D-265) and those raised in the browser.
 * Without an answer from the server (network down) the toast has no support code.
 */
const page = usePage();
const { toasts, push, show, dismiss } = useToasts();
const { t } = useTrans();

let removeNetworkListener: VoidFunction | null = null;

onMounted(() => {
    removeNetworkListener = router.on('networkError', (event) => {
        event.preventDefault();
        show('error', t('errors.offline'));
    });
});

onBeforeUnmount(() => removeNetworkListener?.());

watch(
    () => page.props.toast,
    (toast) => {
        if (toast) {
            push(toast);
        }
    },
    { immediate: true },
);
</script>

<template>
    <section
        :aria-label="t('toast.region')"
        class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-center gap-2 md:inset-x-auto md:right-6 md:bottom-6 md:items-end"
    >
        <div
            v-for="toast in toasts"
            :key="toast.id"
            :role="toast.type === 'error' ? 'alert' : 'status'"
            class="pointer-events-auto flex w-full max-w-[480px] items-start gap-3 rounded-xl px-4 py-3.5 text-sm shadow-lg"
            :class="toast.type === 'error' ? 'bg-ink text-white' : 'border border-success/30 bg-success-soft text-success'"
        >
            <p class="flex-1 leading-relaxed">
                <template v-if="toast.code">
                    {{ `${toast.message} (${t('toast.code')} ` }}<span class="font-mono font-medium tracking-wide">{{ toast.code }}</span>{{ ').' }}
                </template>
                <template v-else>
                    {{ `${toast.message}.` }}
                </template>
            </p>
            <button
                type="button"
                class="-m-1 flex size-11 shrink-0 items-center justify-center rounded-lg opacity-80 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-current"
                :aria-label="t('toast.close')"
                @click="dismiss(toast.id)"
            >
                <X
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
            </button>
        </div>
    </section>
</template>
