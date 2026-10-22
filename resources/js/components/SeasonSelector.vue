<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { useTrans } from '@/composables/useTrans';
import type { LayoutData } from '@/types';

// Placeholder until F1 (seasons in the sports registry, M02): visible but not usable.
defineProps<{ season: LayoutData['season'] }>();

const { t } = useTrans();
</script>

<template>
    <button
        type="button"
        class="flex h-9 items-center gap-1.5 rounded-full border border-line-strong px-3 text-[13px] text-ink-soft disabled:cursor-not-allowed disabled:opacity-70"
        :disabled="!season.enabled"
        :aria-label="t('season.select')"
        :title="season.enabled ? t('season.select') : t('season.unavailable')"
    >
        <span>{{ t('season.label', { season: season.label }) }}</span>
        <ChevronDown
            :size="14"
            :stroke-width="2"
            aria-hidden="true"
        />
    </button>
</template>
