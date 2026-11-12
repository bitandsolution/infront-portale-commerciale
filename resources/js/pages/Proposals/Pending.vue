<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, ClipboardCheck, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { buttonClasses } from '@/components/buttons';
import EmptyState from '@/components/states/EmptyState.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, ProposalPendingGroup } from '@/types';

/**
 * "Proposte › Da approvare" (M07 F-01; DM-03a): the groups sent by the agents, the oldest first, for
 * admin and manager. "Disponibilità" is calculated by the server on the current list and residuals;
 * the decision is taken in the detail of the group (D-411). Table from 1024 px, cards below.
 */
const props = defineProps<{
    layout: LayoutData;
    groups: ProposalPendingGroup[];
}>();

const { t } = useTrans();

const count = computed(() => t('proposals.pending.count', { count: String(props.groups.length) }));
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <div>
                <p class="text-sm text-muted">
                    {{ count }}
                </p>
                <p class="mt-1 max-w-2xl text-sm leading-relaxed text-muted">
                    {{ t('proposals.pending.intro') }}
                </p>
            </div>

            <div class="overflow-hidden rounded-[14px] border border-line bg-white">
                <EmptyState
                    v-if="groups.length === 0"
                    :title="t('proposals.pending.empty_title')"
                    :message="t('proposals.pending.empty_message')"
                >
                    <template #icon>
                        <ClipboardCheck
                            :size="34"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                    </template>
                </EmptyState>

                <table
                    v-else
                    class="hidden w-full text-left text-sm lg:table"
                >
                    <thead class="border-b border-line bg-surface text-[13px] text-muted">
                        <tr>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('proposals.pending.columns.number') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('proposals.pending.columns.submitted') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('proposals.pending.columns.client') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('proposals.pending.columns.agent') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('proposals.pending.columns.clubs') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 text-right font-medium"
                            >
                                {{ t('proposals.pending.columns.total') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('proposals.pending.columns.check') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 text-right font-medium"
                            >
                                {{ t('proposals.mine.columns.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr
                            v-for="group in groups"
                            :key="group.id"
                            class="align-top hover:bg-surface/60"
                        >
                            <td class="px-4 py-3">
                                <span class="block font-mono font-semibold">{{ group.number }}</span>
                                <span
                                    v-if="group.variants > 1"
                                    class="mt-0.5 block text-[13px] whitespace-nowrap text-muted"
                                >{{ t('proposals.pending.variants', { count: String(group.variants) }) }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-muted">
                                {{ group.submittedAt }}
                            </td>
                            <td class="px-4 py-3 font-medium">
                                {{ group.client }}
                            </td>
                            <td class="px-4 py-3">
                                {{ group.agent }}
                            </td>
                            <td class="max-w-56 px-4 py-3 text-muted">
                                {{ group.clubs }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono whitespace-nowrap">
                                <span
                                    v-for="total in group.totals"
                                    :key="total.letter ?? 'single'"
                                    class="block"
                                >
                                    <span
                                        v-if="total.letter"
                                        class="text-muted"
                                    >{{ total.letter }} · </span>€ {{ total.total }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="group.problems.length === 0"
                                    class="inline-flex items-center gap-1.5 text-success"
                                >
                                    <CheckCircle2
                                        :size="16"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                    {{ t('proposals.pending.ok') }}
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-start gap-1.5 text-notice"
                                >
                                    <TriangleAlert
                                        :size="16"
                                        :stroke-width="2"
                                        class="mt-0.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {{ t('proposals.pending.problems', { clubs: group.problems.join('; ') }) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <Link
                                    :href="group.url"
                                    :class="buttonClasses.secondary"
                                    :aria-label="t('proposals.pending.open', { number: String(group.number) })"
                                >
                                    {{ t('proposals.mine.view') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <ul
                    v-if="groups.length > 0"
                    class="divide-y divide-line lg:hidden"
                >
                    <li
                        v-for="group in groups"
                        :key="group.id"
                        class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono font-semibold">{{ group.number }}</span>
                                <span
                                    v-if="group.variants > 1"
                                    class="text-[13px] text-muted"
                                >{{ t('proposals.pending.variants', { count: String(group.variants) }) }}</span>
                                <span class="truncate font-medium">{{ group.client }}</span>
                            </div>
                            <p class="mt-1 text-sm text-muted">
                                {{ group.agent }} · {{ group.submittedAt }}
                            </p>
                            <p class="text-sm text-muted">
                                {{ group.clubs }}
                            </p>
                            <p class="text-sm">
                                <span
                                    v-for="(total, index) in group.totals"
                                    :key="total.letter ?? 'single'"
                                    class="font-mono"
                                ><template v-if="index > 0"> · </template><template v-if="total.letter">{{ total.letter }} </template>€ {{ total.total }}</span>
                            </p>
                            <p
                                class="mt-1 text-sm"
                                :class="group.problems.length === 0 ? 'text-success' : 'text-notice'"
                            >
                                {{ group.problems.length === 0 ? `${t('proposals.pending.columns.check')}: ${t('proposals.pending.ok')}` : t('proposals.pending.problems', { clubs: group.problems.join('; ') }) }}
                            </p>
                        </div>
                        <Link
                            :href="group.url"
                            :class="buttonClasses.secondary"
                            class="shrink-0"
                            :aria-label="t('proposals.pending.open', { number: String(group.number) })"
                        >
                            {{ t('proposals.mine.view') }}
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
