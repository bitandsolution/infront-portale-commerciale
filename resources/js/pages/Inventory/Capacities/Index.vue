<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Lock, Warehouse } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/states/EmptyState.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData } from '@/types';

/**
 * Page "Magazzino › Capacità" (M04 F-01…F-03; D-191, D-337): the clubs of the season of the top bar
 * with their capacities, one tab per resource (LED pools, partnership, hospitality). "—" is a
 * capacity never set, "Non gestito" a resource switched off. Admin only; each club opens its form.
 */
type Resource = 'pool' | 'partnership' | 'hospitality';

interface CapacityCell {
    state: 'not_configured' | 'managed' | 'not_managed';
    display: string | null;
    isBelowCommitted: boolean;
}

interface CapacityRow {
    clubId: number;
    name: string;
    competitionIds: number[];
    cells: Partial<Record<Resource, Record<string, CapacityCell>>>;
    editUrl: string;
}

interface CapacitiesPage {
    season: { id: number; code: string; readOnly: boolean };
    groups: { resource: Resource; columns: { id: number; name: string }[] }[];
    competitions: { id: number; name: string }[];
    rows: CapacityRow[];
}

const props = defineProps<{
    layout: LayoutData;
    page: CapacitiesPage | null;
}>();

const { t } = useTrans();

const active = ref<Resource>('pool');
const competition = ref<number | null>(null);

const group = computed(() => props.page?.groups.find((item) => item.resource === active.value) ?? null);

const visibleRows = computed(() => {
    const rows = props.page?.rows ?? [];
    const selected = competition.value;

    return selected === null ? rows : rows.filter((row) => row.competitionIds.includes(selected));
});

function cell(row: CapacityRow, columnId: number): CapacityCell | null {
    return row.cells[active.value]?.[String(columnId)] ?? null;
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <EmptyState
                v-if="page === null"
                class="rounded-[14px] border border-line bg-white"
                :title="t('inventory.no_season_title')"
                :message="t('inventory.no_season')"
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
                    {{ t('inventory.intro', { season: page.season.code }) }}
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
                    {{ t('inventory.closed_note', { season: page.season.code }) }}
                </p>

                <EmptyState
                    v-if="page.rows.length === 0"
                    class="rounded-[14px] border border-line bg-white"
                    :title="t('inventory.no_clubs_title')"
                    :message="t('inventory.no_clubs', { season: page.season.code })"
                >
                    <template #icon>
                        <Warehouse
                            :size="34"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                    </template>
                </EmptyState>

                <template v-else>
                    <div
                        role="tablist"
                        :aria-label="t('inventory.resources_label')"
                        class="flex flex-wrap gap-1 border-b border-line"
                    >
                        <button
                            v-for="item in page.groups"
                            :key="item.resource"
                            type="button"
                            role="tab"
                            class="-mb-px min-h-11 border-b-2 px-4 text-sm font-medium focus-visible:outline-2 focus-visible:outline-accent"
                            :class="active === item.resource ? 'border-accent text-ink' : 'border-transparent text-muted hover:text-ink'"
                            :aria-selected="active === item.resource"
                            @click="active = item.resource"
                        >
                            {{ t(`inventory.resource_tabs.${item.resource}`) }}
                        </button>
                    </div>

                    <div
                        v-if="page.competitions.length > 1"
                        role="group"
                        :aria-label="t('inventory.filter')"
                        class="flex flex-wrap gap-2"
                    >
                        <button
                            type="button"
                            class="min-h-11 rounded-full px-4 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                            :class="competition === null ? 'bg-ink text-white' : 'border border-line-strong bg-white text-ink'"
                            :aria-pressed="competition === null"
                            @click="competition = null"
                        >
                            {{ t('inventory.filter_all') }}
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

                    <p
                        v-if="group === null || group.columns.length === 0"
                        class="text-sm text-muted"
                    >
                        {{ t('inventory.no_columns') }}
                    </p>

                    <div
                        v-else
                        role="tabpanel"
                        class="overflow-x-auto rounded-[14px] border border-line bg-white"
                    >
                        <table class="w-full min-w-[560px] border-collapse text-sm">
                            <thead>
                                <tr class="bg-surface text-xs tracking-wide text-muted uppercase">
                                    <th
                                        scope="col"
                                        class="px-4 py-2.5 text-left font-semibold"
                                    >
                                        {{ t('inventory.club') }}
                                    </th>
                                    <th
                                        v-for="column in group.columns"
                                        :key="column.id"
                                        scope="col"
                                        class="px-3 py-2.5 text-right font-semibold"
                                    >
                                        {{ column.name }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5"
                                    >
                                        <span class="sr-only">{{ t('inventory.edit') }}</span>
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
                                        v-for="column in group.columns"
                                        :key="column.id"
                                        class="px-3 py-2 text-right"
                                    >
                                        <span
                                            v-if="cell(row, column.id)?.state === 'managed'"
                                            class="font-mono"
                                            :class="cell(row, column.id)?.isBelowCommitted ? 'font-semibold text-accent' : 'text-ink'"
                                        >
                                            {{ cell(row, column.id)?.display }}
                                            <span
                                                v-if="cell(row, column.id)?.isBelowCommitted"
                                                class="sr-only"
                                            >({{ t('inventory.below_committed') }})</span>
                                        </span>
                                        <span
                                            v-else-if="cell(row, column.id)?.state === 'not_managed'"
                                            class="text-muted"
                                        >
                                            {{ t('inventory.not_managed') }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-muted"
                                        >
                                            <span aria-hidden="true">{{ t('inventory.not_configured') }}</span>
                                            <span class="sr-only">{{ t('inventory.not_configured_label') }}</span>
                                        </span>
                                    </td>
                                    <td class="px-3 py-1 text-right">
                                        <Link
                                            :href="row.editUrl"
                                            class="inline-flex min-h-11 items-center text-sm font-medium text-ink underline-offset-2 hover:text-accent hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                                            :aria-label="t('inventory.edit_label', { club: row.name })"
                                        >
                                            {{ t('inventory.edit') }}
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
