<script setup lang="ts">
import { ChartColumn } from '@lucide/vue';
import { useTrans } from '@/composables/useTrans';

/**
 * Clubs of a document without some of the KPI its type uses (M08 RB-05, §10; D-502, D-503; F6-03):
 * shown to admin and manager in the simulator, in the detail of a group waiting for approval and in
 * the step "Documento". It never blocks: the document is generated anyway.
 */
defineProps<{
    warnings: string[];
}>();

const { t } = useTrans();
</script>

<template>
    <div
        v-if="warnings.length > 0"
        class="flex flex-col gap-2 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
        role="status"
    >
        <p class="flex items-center gap-2 font-semibold">
            <ChartColumn
                :size="18"
                :stroke-width="2"
                class="shrink-0 text-notice"
                aria-hidden="true"
            />
            {{ t('content.kpi_warnings.title') }}
        </p>
        <ul class="flex list-disc flex-col gap-1 pl-5">
            <li
                v-for="(warning, index) in warnings"
                :key="index"
            >
                {{ warning }}
            </li>
        </ul>
        <p class="text-muted">
            {{ t('content.kpi_warnings.hint') }}
        </p>
    </div>
</template>
