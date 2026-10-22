<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';

/** Loading state (ux-architettura §4.5): grey skeletons instead of tables and cards, never a blank screen. */
withDefaults(defineProps<{ rows?: number }>(), { rows: 4 });

const { t } = useTrans();

// Fixed widths, so the skeleton does not jump between renders.
const widths = ['60%', '85%', '72%', '40%', '78%', '55%'];
</script>

<template>
    <div
        class="flex flex-col gap-3.5"
        role="status"
        aria-busy="true"
    >
        <span class="sr-only">{{ t('states.loading') }}</span>
        <div
            v-for="row in rows"
            :key="row"
            class="h-3.5 animate-pulse rounded-md bg-skeleton motion-reduce:animate-none"
            :style="{ width: widths[(row - 1) % widths.length] }"
            aria-hidden="true"
        />
    </div>
</template>
