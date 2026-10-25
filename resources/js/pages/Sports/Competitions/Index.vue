<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Plus, Trophy } from '@lucide/vue';
import { buttonClasses } from '@/components/buttons';
import EmptyState from '@/components/states/EmptyState.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CompetitionRow, LayoutData } from '@/types';

/**
 * Competitions page (M02 F-02; D-137: admin only), in the order of the lists. Competitions are
 * deactivated, never deleted (C4).
 */
defineProps<{
    layout: LayoutData;
    competitions: CompetitionRow[];
    createUrl: string;
}>();

const { t } = useTrans();
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-muted">
                        {{ t('sports.competitions.count', { count: String(competitions.length) }) }}
                    </p>
                    <p class="mt-1 max-w-2xl text-sm leading-relaxed text-muted">
                        {{ t('sports.competitions.intro') }}
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
                    {{ t('sports.competitions.new') }}
                </Link>
            </div>

            <div class="overflow-hidden rounded-[14px] border border-line bg-white">
                <EmptyState
                    v-if="competitions.length === 0"
                    :title="t('sports.competitions.empty_title')"
                    :message="t('sports.competitions.empty_message')"
                >
                    <template #icon>
                        <Trophy
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
                                {{ t('sports.competitions.columns.name') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('sports.competitions.columns.type') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('sports.competitions.columns.tier') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('sports.competitions.columns.top_match') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('sports.competitions.columns.status') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('sports.competitions.columns.seasons') }}
                            </th>
                            <th
                                scope="col"
                                class="w-12 px-2 py-3"
                            >
                                <span class="sr-only">{{ t('sports.competitions.columns.name') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="competition in competitions"
                            :key="competition.id"
                            class="border-b border-line last:border-b-0 hover:bg-surface/60"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="competition.url"
                                    class="font-semibold text-ink underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                                >
                                    {{ competition.name }}
                                </Link>
                                <div class="font-mono text-[13px] text-muted">
                                    {{ competition.code }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                {{ competition.typeLabel }}
                            </td>
                            <td class="px-4 py-3">
                                {{ competition.tier }}
                            </td>
                            <td class="px-4 py-3">
                                {{ competition.hasTopMatchKpi ? t('sports.yes') : t('sports.no') }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusPill
                                    :label="competition.isActive ? t('sports.active') : t('sports.inactive')"
                                    :tone="competition.isActive ? 'success' : 'muted'"
                                />
                            </td>
                            <td class="px-4 py-3">
                                {{ competition.seasons }}
                            </td>
                            <td class="px-2 py-3">
                                <Link
                                    :href="competition.url"
                                    class="flex size-11 items-center justify-center rounded-[10px] text-muted hover:bg-surface hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
                                    :aria-label="t('sports.competitions.open', { competition: competition.name })"
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
                    v-if="competitions.length > 0"
                    class="divide-y divide-line md:hidden"
                >
                    <li
                        v-for="competition in competitions"
                        :key="competition.id"
                    >
                        <Link
                            :href="competition.url"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold">
                                    {{ competition.name }}
                                </div>
                                <div class="text-[13px] text-muted">
                                    {{ competition.typeLabel }} · {{ t('sports.competitions.columns.tier') }} {{ competition.tier }}
                                </div>
                                <div class="mt-1.5">
                                    <StatusPill
                                        :label="competition.isActive ? t('sports.active') : t('sports.inactive')"
                                        :tone="competition.isActive ? 'success' : 'muted'"
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
