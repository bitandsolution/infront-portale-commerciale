<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ImageOff } from '@lucide/vue';
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, LedMockupKindCell, LedMockupRow } from '@/types';

/**
 * "Contenuti › Simulazioni LED" (D-398, D-427; DM-06): the generic configuration of Infront and the
 * Infront clubs, with the three kinds of photo (1ª fila, 2ª fila, Jumbo e Domination). A club without
 * its photo uses the generic one; admin and manager see the page, only the admin changes it.
 */
const props = defineProps<{
    layout: LayoutData;
    rows: LedMockupRow[];
}>();

const { t } = useTrans();

const generic = computed(() => props.rows.find((row) => row.generic));
const kinds = computed(() => props.rows[0]?.kinds ?? []);

function genericConfigured(kind: LedMockupKindCell['kind']): boolean {
    return generic.value?.kinds.find((cell) => cell.kind === kind)?.configured ?? false;
}

function statusText(row: LedMockupRow, cell: LedMockupKindCell): string {
    if (cell.configured) {
        return t('content.led_mockups.configured');
    }

    return !row.generic && genericConfigured(cell.kind) ? t('content.led_mockups.uses_generic') : t('content.led_mockups.missing');
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-7xl flex-col gap-5 p-4 md:p-6">
            <p class="max-w-3xl text-sm leading-relaxed text-muted">
                {{ t('content.led_mockups.intro') }}
            </p>

            <div class="overflow-x-auto rounded-[14px] border border-line bg-white">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b border-line bg-surface text-[13px] text-muted">
                        <tr>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('content.led_mockups.columns.club') }}
                            </th>
                            <th
                                v-for="cell in kinds"
                                :key="cell.kind"
                                scope="col"
                                class="px-3 py-3 font-medium"
                            >
                                {{ cell.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.scope"
                            class="border-b border-line last:border-b-0"
                            :class="row.generic ? 'bg-surface/60' : ''"
                        >
                            <th
                                scope="row"
                                class="px-4 py-3 font-semibold text-ink"
                            >
                                {{ row.name }}
                            </th>
                            <td
                                v-for="cell in row.kinds"
                                :key="cell.kind"
                                class="px-3 py-3"
                            >
                                <Link
                                    :href="cell.editUrl"
                                    class="flex items-center gap-3 rounded-[10px] p-1 hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                                >
                                    <span class="relative flex h-12 w-24 shrink-0 items-center justify-center overflow-hidden rounded-md border border-line bg-surface">
                                        <img
                                            v-if="cell.thumbnailUrl"
                                            :src="cell.thumbnailUrl"
                                            alt=""
                                            loading="lazy"
                                            class="size-full object-cover"
                                        >
                                        <ImageOff
                                            v-else
                                            :size="20"
                                            :stroke-width="1.6"
                                            class="text-muted"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span class="flex flex-col">
                                        <span :class="cell.configured ? 'text-ink' : 'text-muted'">{{ statusText(row, cell) }}</span>
                                        <span class="text-[13px] font-medium text-accent">{{ cell.configured ? t('content.led_mockups.open') : t('content.led_mockups.configure') }}</span>
                                    </span>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
