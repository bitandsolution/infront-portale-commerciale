<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Lock, Warehouse } from '@lucide/vue';
import { computed, ref } from 'vue';
import StatusPill from '@/components/StatusPill.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, StatusTone } from '@/types';

/**
 * Page "Magazzino › Disponibilità" (M04 F-06; mockup "Magazzino"; D-337; F2-05): one LED pool at a
 * time for every club of the season (capacity, committed, pending, residual with its bar), and on
 * the side the selected club: partnership and hospitality counters and what the configurator can
 * sell. Admin and manager, read only; every quantity and the share of the bar come from the server.
 */
type CellState = 'not_configured' | 'managed' | 'not_managed';

interface Cell {
    state: CellState;
    capacity: string | null;
    committed: string;
    pending: string | null;
    remaining: string | null;
    share: number;
    isSoldOut: boolean;
    isBelowCommitted: boolean;
}

interface PoolCell extends Cell {
    notSellable: string;
}

interface Counter extends Cell {
    name: string;
}

interface Row {
    clubId: number;
    name: string;
    competitionIds: number[];
    pools: Record<string, PoolCell>;
    tiers: Counter[];
    variants: Counter[];
    sellability: {
        selectable: boolean;
        reason: string | null;
        formats: { name: string; status: 'available' | 'sold_out' | 'not_managed' | 'not_available'; label: string }[];
    };
    editUrl: string | null;
}

interface AvailabilityPage {
    season: { id: number; code: string; readOnly: boolean };
    pools: { id: number; name: string; isActive: boolean }[];
    competitions: { id: number; name: string }[];
    rows: Row[];
}

const props = defineProps<{
    layout: LayoutData;
    page: AvailabilityPage | null;
}>();

const { t } = useTrans();

const pool = ref<number | null>(props.page?.pools.find((item) => item.isActive)?.id ?? null);
const competition = ref<number | null>(null);
const selected = ref<number | null>(props.page?.rows[0]?.clubId ?? null);

const poolName = computed(() => props.page?.pools.find((item) => item.id === pool.value)?.name ?? '');

const visibleRows = computed(() => {
    const rows = props.page?.rows ?? [];
    const filter = competition.value;

    return filter === null ? rows : rows.filter((row) => row.competitionIds.includes(filter));
});

const detail = computed(() => props.page?.rows.find((row) => row.clubId === selected.value) ?? null);

function cell(row: Row): PoolCell | null {
    return pool.value === null ? null : (row.pools[String(pool.value)] ?? null);
}

const formatTones: Record<Row['sellability']['formats'][number]['status'], StatusTone> = {
    available: 'success',
    sold_out: 'notice',
    not_managed: 'muted',
    not_available: 'muted',
};
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-7xl flex-col gap-5 p-4 md:p-6">
            <EmptyState
                v-if="page === null"
                class="rounded-[14px] border border-line bg-white"
                :title="t('inventory.no_season_title')"
                :message="t('inventory.availability.no_season')"
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
                    {{ t('inventory.availability.intro', { season: page.season.code }) }}
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
                    {{ t('inventory.availability.closed_note', { season: page.season.code }) }}
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

                <div
                    v-else
                    class="grid items-start gap-5 min-[1440px]:grid-cols-[minmax(0,1fr)_340px]"
                >
                    <section class="flex min-w-0 flex-col gap-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-xl font-semibold text-ink">
                                {{ t('inventory.availability.title', { pool: poolName }) }}
                            </h1>
                            <div
                                role="group"
                                :aria-label="t('inventory.availability.pools_label')"
                                class="flex flex-wrap gap-2 lg:ml-auto"
                            >
                                <button
                                    v-for="item in page.pools"
                                    :key="item.id"
                                    type="button"
                                    class="min-h-11 rounded-full px-4 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed"
                                    :class="!item.isActive
                                        ? 'border border-dashed border-line-strong bg-surface text-muted'
                                        : pool === item.id ? 'bg-ink text-white' : 'border border-line-strong bg-white text-ink'"
                                    :disabled="!item.isActive"
                                    :aria-pressed="pool === item.id"
                                    :title="item.isActive ? undefined : t('inventory.availability.pool_inactive', { name: item.name })"
                                    @click="pool = item.id"
                                >
                                    {{ item.name }}
                                </button>
                            </div>
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
                            v-if="pool === null"
                            class="text-sm text-muted"
                        >
                            {{ t('inventory.no_columns') }}
                        </p>

                        <div
                            v-else
                            class="overflow-x-auto rounded-[14px] border border-line bg-white"
                        >
                            <table class="w-full min-w-[720px] border-collapse text-sm">
                                <thead>
                                    <tr class="bg-surface text-xs tracking-wide text-muted uppercase">
                                        <th
                                            scope="col"
                                            class="px-4 py-2.5 text-left font-semibold"
                                        >
                                            {{ t('inventory.availability.columns.club') }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-2 py-2.5 text-left font-semibold"
                                        >
                                            {{ t('inventory.availability.columns.managed') }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-2 py-2.5 text-right font-semibold"
                                        >
                                            {{ t('inventory.availability.columns.capacity') }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-2 py-2.5 text-right font-semibold"
                                        >
                                            {{ t('inventory.availability.columns.committed') }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-2 py-2.5 text-right font-semibold"
                                        >
                                            {{ t('inventory.availability.columns.pending') }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="w-[240px] px-4 py-2.5 text-left font-semibold"
                                        >
                                            {{ t('inventory.availability.columns.remaining') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in visibleRows"
                                        :key="row.clubId"
                                        class="border-t border-skeleton"
                                        :class="cell(row)?.isBelowCommitted ? 'bg-notice-soft' : (selected === row.clubId ? 'bg-surface' : '')"
                                    >
                                        <th
                                            scope="row"
                                            class="px-4 py-1 text-left font-semibold"
                                        >
                                            <button
                                                type="button"
                                                class="min-h-11 text-left underline-offset-2 hover:text-accent hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                                                :class="cell(row)?.state === 'managed' ? 'text-ink' : 'text-muted'"
                                                :aria-label="t('inventory.availability.select_club', { club: row.name })"
                                                :aria-pressed="selected === row.clubId"
                                                @click="selected = row.clubId"
                                            >
                                                {{ row.name }}
                                            </button>
                                        </th>
                                        <template v-if="cell(row)?.state === 'managed'">
                                            <td class="px-2 py-2">
                                                {{ t('inventory.availability.managed_yes') }}
                                            </td>
                                            <td class="px-2 py-2 text-right font-mono">
                                                {{ cell(row)?.capacity }}
                                            </td>
                                            <td class="px-2 py-2 text-right font-mono">
                                                {{ cell(row)?.committed }}
                                            </td>
                                            <td class="px-2 py-2 text-right font-mono text-muted">
                                                {{ cell(row)?.pending ?? t('inventory.availability.empty') }}
                                            </td>
                                            <td class="px-4 py-2">
                                                <div class="flex items-center gap-2.5">
                                                    <div
                                                        class="h-2 flex-1 rounded bg-skeleton"
                                                        aria-hidden="true"
                                                    >
                                                        <div
                                                            class="h-2 rounded bg-success"
                                                            :style="{ width: `${cell(row)?.share ?? 0}%` }"
                                                        />
                                                    </div>
                                                    <span
                                                        v-if="cell(row)?.isBelowCommitted"
                                                        class="font-semibold text-notice"
                                                    >
                                                        <span class="font-mono whitespace-nowrap">{{ cell(row)?.remaining }}</span> · {{ t('inventory.availability.below_committed') }}
                                                    </span>
                                                    <span
                                                        v-else-if="cell(row)?.isSoldOut"
                                                        class="font-semibold"
                                                    >
                                                        {{ t('inventory.availability.sold_out') }}
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="font-mono"
                                                    >
                                                        {{ cell(row)?.remaining }}
                                                    </span>
                                                </div>
                                            </td>
                                        </template>
                                        <template v-else>
                                            <td class="px-2 py-2 text-muted">
                                                {{ cell(row)?.state === 'not_managed' ? t('inventory.availability.managed_no') : t('inventory.availability.not_configured') }}
                                            </td>
                                            <td class="px-2 py-2 text-right text-muted">
                                                {{ t('inventory.availability.empty') }}
                                            </td>
                                            <td class="px-2 py-2 text-right font-mono text-muted">
                                                {{ cell(row)?.committed }}
                                            </td>
                                            <td class="px-2 py-2 text-right text-muted">
                                                {{ t('inventory.availability.empty') }}
                                            </td>
                                            <td class="px-4 py-2 text-muted">
                                                {{ cell(row)?.notSellable }}
                                            </td>
                                        </template>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <aside
                        v-if="detail !== null"
                        :aria-label="t('inventory.availability.detail_label')"
                        class="grid items-start gap-4 md:grid-cols-2 min-[1440px]:grid-cols-1"
                    >
                        <div class="rounded-[14px] border border-line bg-white p-[18px]">
                            <h2 class="text-base font-semibold text-ink">
                                {{ detail.name }}
                            </h2>
                            <p class="mb-3.5 text-[13px] text-muted">
                                {{ t('inventory.availability.detail_subtitle', { season: page.season.code }) }}
                            </p>

                            <template
                                v-for="(group, index) in [
                                    { key: 'tiers', title: t('inventory.availability.tier'), quantity: t('inventory.availability.capacity'), items: detail.tiers },
                                    { key: 'variants', title: t('inventory.availability.variant'), quantity: t('inventory.availability.seats'), items: detail.variants },
                                ]"
                                :key="group.key"
                            >
                                <div
                                    v-if="index > 0"
                                    class="my-3.5 h-px bg-skeleton"
                                />
                                <table class="w-full text-[13px]">
                                    <thead>
                                        <tr class="text-muted">
                                            <th
                                                scope="col"
                                                class="pb-1 text-left font-normal"
                                            >
                                                {{ group.title }}
                                            </th>
                                            <th
                                                scope="col"
                                                class="pb-1 text-right font-normal"
                                            >
                                                {{ group.quantity }}
                                            </th>
                                            <th
                                                scope="col"
                                                class="pb-1 text-right font-normal"
                                            >
                                                {{ t('inventory.availability.remaining') }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="group.items.length === 0">
                                            <td
                                                colspan="3"
                                                class="py-1 text-muted"
                                            >
                                                {{ t('inventory.availability.no_counters') }}
                                            </td>
                                        </tr>
                                        <tr
                                            v-for="item in group.items"
                                            :key="item.name"
                                        >
                                            <th
                                                scope="row"
                                                class="py-1 text-left font-normal"
                                                :class="item.state === 'managed' ? 'text-ink' : 'text-muted'"
                                            >
                                                {{ item.name }}
                                            </th>
                                            <td
                                                class="py-1 text-right"
                                                :class="item.state === 'managed' ? 'text-ink' : 'text-muted'"
                                            >
                                                {{ item.state === 'managed' ? item.capacity : (item.state === 'not_managed' ? t('inventory.not_managed') : t('inventory.availability.empty')) }}
                                            </td>
                                            <td
                                                class="py-1 text-right font-semibold"
                                                :class="item.isBelowCommitted ? 'text-notice' : (item.state === 'managed' ? 'text-ink' : 'text-muted')"
                                            >
                                                <template v-if="item.state !== 'managed'">
                                                    {{ t('inventory.availability.empty') }}
                                                </template>
                                                <template v-else-if="item.isBelowCommitted">
                                                    {{ item.remaining }} · {{ t('inventory.availability.below_committed') }}
                                                </template>
                                                <template v-else-if="item.isSoldOut">
                                                    {{ t('inventory.availability.sold_out') }}
                                                </template>
                                                <template v-else>
                                                    {{ item.remaining }}
                                                </template>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </template>

                            <div class="my-3.5 h-px bg-skeleton" />
                            <h3 class="text-[13px] font-semibold text-ink">
                                {{ t('inventory.availability.configurator') }}
                            </h3>
                            <p
                                class="mt-1 text-[13px]"
                                :class="detail.sellability.selectable ? 'text-success' : 'text-muted'"
                            >
                                {{ detail.sellability.selectable
                                    ? t('inventory.availability.selectable')
                                    : t('inventory.availability.not_selectable', { reason: detail.sellability.reason ?? '' }) }}
                            </p>
                            <ul
                                v-if="detail.sellability.formats.length > 0"
                                :aria-label="t('inventory.availability.formats')"
                                class="mt-2 flex flex-col gap-1.5 text-[13px]"
                            >
                                <li
                                    v-for="format in detail.sellability.formats"
                                    :key="format.name"
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span>{{ format.name }}</span>
                                    <StatusPill
                                        :label="format.label"
                                        :tone="formatTones[format.status]"
                                    />
                                </li>
                            </ul>

                            <Link
                                v-if="detail.editUrl !== null"
                                :href="detail.editUrl"
                                class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-[10px] border border-line-strong bg-white text-sm font-medium text-ink hover:border-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                                :aria-label="t('inventory.availability.edit_label', { club: detail.name })"
                            >
                                {{ t('inventory.availability.edit') }}
                            </Link>
                        </div>
                        <p
                            role="note"
                            class="rounded-xl border border-notice-line bg-notice-soft px-3.5 py-3 text-[13px] leading-relaxed text-notice"
                        >
                            {{ t('inventory.availability.note') }}
                        </p>
                    </aside>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
