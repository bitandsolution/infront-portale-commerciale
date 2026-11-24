<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, ChartColumn, Lock, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/states/EmptyState.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData } from '@/types';

/**
 * Page "Contenuti › KPI e digital" (M08 F-05, F-08; D-491, D-492; F6-01): the Infront clubs of the
 * season of the top bar with how many KPI they have, how many are not updated for more than 6 months
 * and the date of the last update. Admin and manager; each club opens its form.
 */
interface KpiRow {
    clubId: number;
    name: string;
    competitionIds: number[];
    present: number;
    stale: number;
    lastUpdate: string | null;
    editUrl: string;
}

interface KpisPage {
    season: { id: number; code: string; readOnly: boolean };
    total: number;
    staleMonths: number;
    competitions: { id: number; name: string }[];
    rows: KpiRow[];
}

const props = defineProps<{
    layout: LayoutData;
    page: KpisPage | null;
}>();

const { t } = useTrans();

const competition = ref<number | null>(null);

const visibleRows = computed(() => {
    const rows = props.page?.rows ?? [];
    const selected = competition.value;

    return selected === null ? rows : rows.filter((row) => row.competitionIds.includes(selected));
});
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-5xl flex-col gap-5 p-4 md:p-6">
            <EmptyState
                v-if="page === null"
                class="rounded-[14px] border border-line bg-white"
                :title="t('content.kpis.no_season_title')"
                :message="t('content.kpis.no_season')"
            >
                <template #icon>
                    <CalendarDays
                        :size="34"
                        :stroke-width="1.6"
                        aria-hidden="true"
                    />
                </template>
            </EmptyState>

            <template v-else>
                <p class="text-sm leading-relaxed text-muted">
                    {{ t('content.kpis.intro', { season: page.season.code }) }}
                </p>
                <p
                    v-if="page.season.readOnly"
                    class="flex items-start gap-2 rounded-[10px] bg-surface px-4 py-3 text-sm text-ink-soft"
                >
                    <Lock
                        :size="18"
                        :stroke-width="2"
                        class="mt-0.5 shrink-0"
                        aria-hidden="true"
                    />
                    {{ t('content.kpis.closed', { season: page.season.code }) }}
                </p>

                <EmptyState
                    v-if="page.rows.length === 0"
                    class="rounded-[14px] border border-line bg-white"
                    :title="t('content.kpis.no_clubs_title')"
                    :message="t('content.kpis.no_clubs', { season: page.season.code })"
                >
                    <template #icon>
                        <ChartColumn
                            :size="34"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                    </template>
                </EmptyState>

                <template v-else>
                    <div
                        v-if="page.competitions.length > 1"
                        role="group"
                        :aria-label="t('content.kpis.filter')"
                        class="flex flex-wrap gap-2"
                    >
                        <button
                            type="button"
                            class="min-h-11 rounded-full px-4 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                            :class="competition === null ? 'bg-ink text-white' : 'border border-line-strong bg-white text-ink'"
                            :aria-pressed="competition === null"
                            @click="competition = null"
                        >
                            {{ t('content.kpis.filter_all') }}
                        </button>
                        <button
                            v-for="item in page.competitions"
                            :key="item.id"
                            type="button"
                            class="min-h-11 rounded-full px-4 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                            :class="competition === item.id ? 'bg-ink text-white' : 'border border-line-strong bg-white text-ink'"
                            :aria-pressed="competition === item.id"
                            @click="competition = item.id"
                        >
                            {{ item.name }}
                        </button>
                    </div>

                    <div class="overflow-x-auto rounded-[14px] border border-line bg-white">
                        <table class="w-full min-w-[600px] border-collapse text-sm">
                            <thead>
                                <tr class="bg-surface text-xs tracking-wide text-muted uppercase">
                                    <th
                                        scope="col"
                                        class="px-4 py-2.5 text-left font-semibold"
                                    >
                                        {{ t('content.kpis.columns.club') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-right font-semibold"
                                    >
                                        {{ t('content.kpis.columns.present') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-left font-semibold"
                                    >
                                        {{ t('content.kpis.columns.stale') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-left font-semibold"
                                    >
                                        {{ t('content.kpis.columns.last_update') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5"
                                    >
                                        <span class="sr-only">{{ t('content.kpis.edit') }}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in visibleRows"
                                    :key="row.clubId"
                                    class="border-t border-skeleton"
                                >
                                    <th
                                        scope="row"
                                        class="px-4 py-2 text-left font-semibold"
                                    >
                                        {{ row.name }}
                                    </th>
                                    <td
                                        class="px-3 py-2 text-right font-mono"
                                        :class="row.present < page.total ? 'text-muted' : 'text-ink'"
                                    >
                                        {{ t('content.kpis.present', { present: String(row.present), total: String(page.total) }) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span
                                            v-if="row.stale > 0"
                                            class="inline-flex items-center gap-1.5 font-semibold text-accent"
                                        >
                                            <TriangleAlert
                                                :size="16"
                                                :stroke-width="2"
                                                aria-hidden="true"
                                            />
                                            {{ t('content.kpis.stale_count', { count: String(row.stale), months: String(page.staleMonths) }) }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-muted"
                                        >{{ t('content.kpis.none') }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-ink-soft">
                                        {{ row.lastUpdate ?? t('content.kpis.never') }}
                                    </td>
                                    <td class="px-3 py-1 text-right">
                                        <Link
                                            :href="row.editUrl"
                                            class="inline-flex min-h-11 items-center text-sm font-medium text-ink underline-offset-2 hover:text-accent hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                                            :aria-label="t('content.kpis.edit_label', { club: row.name })"
                                        >
                                            {{ t('content.kpis.edit') }}
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </template>
        </div>
    </AppLayout>
</template>
