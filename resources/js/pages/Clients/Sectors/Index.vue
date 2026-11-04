<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Plus, Search, Shapes } from '@lucide/vue';
import { computed, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import EmptyState from '@/components/states/EmptyState.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CommoditySectorRow, LayoutData } from '@/types';

/**
 * Sectors page (M05 F-05; D-357: admin only). About twenty-five sectors: the list arrives from the
 * server, already in order, and the search works in the browser.
 */
const props = defineProps<{
    layout: LayoutData;
    sectors: CommoditySectorRow[];
    createUrl: string;
}>();

const { t } = useTrans();

const search = ref('');

const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('it');

    return props.sectors.filter(
        (sector) => term === '' || sector.name.toLocaleLowerCase('it').includes(term) || sector.code.toLocaleLowerCase('it').includes(term),
    );
});
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-muted">
                        {{ t('clients.sectors.count', { count: String(filtered.length) }) }}
                    </p>
                    <p class="mt-1 max-w-2xl text-sm leading-relaxed text-muted">
                        {{ t('clients.sectors.intro') }}
                    </p>
                </div>
                <Link
                    :href="createUrl"
                    :class="buttonClasses.primary"
                >
                    <Plus
                        :size="18"
                        :stroke-width="2.2"
                        aria-hidden="true"
                    />
                    {{ t('clients.sectors.new') }}
                </Link>
            </div>

            <div class="rounded-[14px] border border-line bg-white p-4">
                <label
                    for="sectors-search"
                    class="text-sm font-medium text-ink"
                >{{ t('clients.sectors.search') }}</label>
                <div class="relative mt-1.5">
                    <Search
                        :size="18"
                        :stroke-width="2"
                        class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted"
                        aria-hidden="true"
                    />
                    <input
                        id="sectors-search"
                        v-model="search"
                        type="search"
                        autocomplete="off"
                        class="min-h-11 w-full rounded-[10px] border border-line-strong bg-white pr-3.5 pl-10 text-[15px] text-ink outline-none focus:border-accent focus:ring-2 focus:ring-accent/20"
                    >
                </div>
            </div>

            <div class="overflow-hidden rounded-[14px] border border-line bg-white">
                <EmptyState
                    v-if="filtered.length === 0"
                    :title="t('clients.sectors.empty_title')"
                    :message="t('clients.sectors.empty_message')"
                >
                    <template #icon>
                        <Shapes
                            :size="34"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                    </template>
                </EmptyState>

                <table
                    v-else
                    class="hidden w-full text-left text-sm md:table"
                >
                    <thead class="border-b border-line bg-surface text-[13px] text-muted">
                        <tr>
                            <th
                                scope="col"
                                class="w-20 px-4 py-3 font-medium"
                            >
                                {{ t('clients.sectors.columns.order') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('clients.sectors.columns.name') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('clients.sectors.columns.code') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('clients.sectors.columns.free_text') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('clients.sectors.columns.status') }}
                            </th>
                            <th
                                scope="col"
                                class="w-12 px-2 py-3"
                            >
                                <span class="sr-only">{{ t('clients.sectors.columns.name') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="sector in filtered"
                            :key="sector.id"
                            class="border-b border-line last:border-b-0 hover:bg-surface/60"
                        >
                            <td class="px-4 py-3 font-mono text-[13px] text-muted">
                                {{ sector.sortOrder }}
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    :href="sector.url"
                                    class="font-semibold text-ink underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                                >
                                    {{ sector.name }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 font-mono text-[13px]">
                                {{ sector.code }}
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="sector.requiresFreeText">{{ t('clients.sectors.free_text') }}</span>
                                <span
                                    v-else
                                    class="text-muted"
                                >—</span>
                            </td>
                            <td class="px-4 py-3">
                                <StatusPill
                                    :label="sector.isActive ? t('clients.active') : t('clients.inactive')"
                                    :tone="sector.isActive ? 'success' : 'muted'"
                                />
                            </td>
                            <td class="px-2 py-3">
                                <Link
                                    :href="sector.url"
                                    class="flex size-11 items-center justify-center rounded-[10px] text-muted hover:bg-surface hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
                                    :aria-label="t('clients.sectors.open', { sector: sector.name })"
                                >
                                    <ChevronRight
                                        :size="18"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <ul
                    v-if="filtered.length > 0"
                    class="divide-y divide-line md:hidden"
                >
                    <li
                        v-for="sector in filtered"
                        :key="sector.id"
                    >
                        <Link
                            :href="sector.url"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold">
                                    {{ sector.name }}
                                </div>
                                <div class="font-mono text-[13px] text-muted">
                                    {{ sector.code }}
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-2">
                                    <StatusPill
                                        :label="sector.isActive ? t('clients.active') : t('clients.inactive')"
                                        :tone="sector.isActive ? 'success' : 'muted'"
                                    />
                                    <span
                                        v-if="sector.requiresFreeText"
                                        class="text-[13px] text-muted"
                                    >{{ t('clients.sectors.free_text') }}</span>
                                </div>
                            </div>
                            <ChevronRight
                                :size="18"
                                :stroke-width="2"
                                class="shrink-0 text-muted"
                                aria-hidden="true"
                            />
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
