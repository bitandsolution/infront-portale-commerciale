<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarRange, ChevronRight, Plus } from '@lucide/vue';
import { buttonClasses } from '@/components/buttons';
import EmptyState from '@/components/states/EmptyState.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, SeasonRow, StatusTone } from '@/types';

/**
 * Seasons page (M02 F-03; D-137: admin only), the most recent first. A season never overlaps
 * another one; a closed season is read-only (D-317).
 */
defineProps<{
    layout: LayoutData;
    seasons: SeasonRow[];
    createUrl: string;
}>();

const { t } = useTrans();

const tones: Record<string, StatusTone> = { open: 'success', planned: 'notice', closed: 'muted' };
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-muted">
                        {{ t('sports.seasons.count', { count: String(seasons.length) }) }}
                    </p>
                    <p class="mt-1 max-w-2xl text-sm leading-relaxed text-muted">
                        {{ t('sports.seasons.intro') }}
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
                    {{ t('sports.seasons.new') }}
                </Link>
            </div>

            <div class="overflow-hidden rounded-[14px] border border-line bg-white">
                <EmptyState
                    v-if="seasons.length === 0"
                    :title="t('sports.seasons.empty_title')"
                    :message="t('sports.seasons.empty_message')"
                >
                    <template #icon>
                        <CalendarRange
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
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('sports.seasons.columns.code') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('sports.seasons.columns.period') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('sports.seasons.columns.status') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('sports.seasons.columns.competitions') }}
                            </th>
                            <th
                                scope="col"
                                class="w-12 px-2 py-3"
                            >
                                <span class="sr-only">{{ t('sports.seasons.columns.code') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="season in seasons"
                            :key="season.id"
                            class="border-b border-line last:border-b-0 hover:bg-surface/60"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="season.url"
                                    class="font-semibold text-ink underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                                >
                                    {{ season.code }}
                                </Link>
                                <div class="text-[13px] text-muted">
                                    {{ season.name }}
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono text-[13px] whitespace-nowrap">
                                {{ t('sports.seasons.period', { start: season.startsOn, end: season.endsOn }) }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusPill
                                    :label="season.statusLabel"
                                    :tone="tones[season.status] ?? 'muted'"
                                />
                            </td>
                            <td class="px-4 py-3">
                                {{ season.competitions }}
                            </td>
                            <td class="px-2 py-3">
                                <Link
                                    :href="season.url"
                                    class="flex size-11 items-center justify-center rounded-[10px] text-muted hover:bg-surface hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
                                    :aria-label="t('sports.seasons.open', { season: season.code })"
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
                    v-if="seasons.length > 0"
                    class="divide-y divide-line md:hidden"
                >
                    <li
                        v-for="season in seasons"
                        :key="season.id"
                    >
                        <Link
                            :href="season.url"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold">
                                    {{ season.code }}
                                </div>
                                <div class="font-mono text-[13px] text-muted">
                                    {{ t('sports.seasons.period', { start: season.startsOn, end: season.endsOn }) }}
                                </div>
                                <div class="mt-1.5">
                                    <StatusPill
                                        :label="season.statusLabel"
                                        :tone="tones[season.status] ?? 'muted'"
                                    />
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
