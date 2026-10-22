<script setup lang="ts">
import { CircleAlert, RefreshCw } from '@lucide/vue';
import { useTrans } from '@/composables/useTrans';

/** Error state of a block that could not load its data (ux-architettura §4.5), with "Riprova". */
defineProps<{
    title?: string;
    message?: string;
}>();

const emit = defineEmits<{ retry: [] }>();

const { t } = useTrans();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 px-6 py-10 text-center"
        role="alert"
    >
        <CircleAlert
            :size="36"
            :stroke-width="1.8"
            class="text-accent"
            aria-hidden="true"
        />
        <h2 class="text-lg font-semibold">
            {{ title ?? t('states.error_title') }}
        </h2>
        <p class="max-w-[380px] text-[15px] leading-relaxed text-muted">
            {{ message ?? t('states.error_message') }}
        </p>
        <button
            type="button"
            class="mt-1 flex h-11 items-center gap-2 rounded-[10px] border border-line-strong bg-white px-4 text-sm font-semibold hover:bg-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
            @click="emit('retry')"
        >
            <RefreshCw
                :size="16"
                :stroke-width="2"
                aria-hidden="true"
            />
            {{ t('states.retry') }}
        </button>
    </div>
</template>
