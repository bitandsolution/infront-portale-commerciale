<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { FileText } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { buttonClasses } from '@/components/buttons';
import SelectField from '@/components/forms/SelectField.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, Option, ProposalGroupRow } from '@/types';

/**
 * "Proposte › Tutte" (M07 F-01; DM-03b): every group of variants for admin and manager, the most
 * recently changed first, with the filter by status in the address (?stato=). Read only: the
 * decisions are taken in the detail of the group. Table from 1024 px, cards below.
 */
const props = defineProps<{
    layout: LayoutData;
    groups: ProposalGroupRow[];
    status: string;
    statuses: Option[];
    limit: number;
}>();

const { t } = useTrans();

const statusFilter = ref(props.status);

watch(statusFilter, (value) => {
    router.get(window.location.pathname, value === '' ? {} : { stato: value }, { preserveScroll: true, preserveState: true, replace: true });
});

const count = computed(() => (props.groups.length === 1 ? t('proposals.all.count_one') : t('proposals.all.count', { count: String(props.groups.length) })));
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm text-muted">
                        {{ count }}
                    </p>
                    <p class="mt-1 max-w-2xl text-sm leading-relaxed text-muted">
                        {{ t('proposals.all.intro') }}
                    </p>
                </div>
                <div class="w-64">
                    <SelectField
                        v-model="statusFilter"
                        :label="t('proposals.all.filter')"
                        :options="statuses"
                    />
                </div>
            </div>

            <div class="overflow-hidden rounded-[14px] border border-line bg-white">
                <EmptyState
                    v-if="groups.length === 0"
                    :title="t('proposals.all.empty_title')"
                    :message="status === '' ? t('proposals.all.empty_message') : t('proposals.all.empty_filtered')"
                >
                    <template #icon>
                        <FileText
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
                                {{ t('proposals.all.columns.number') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('proposals.all.columns.status') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('proposals.all.columns.client') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('proposals.all.columns.agent') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('proposals.all.columns.clubs') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 text-right font-medium"
                            >
                                {{ t('proposals.all.columns.total') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('proposals.all.columns.updated') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 text-right font-medium"
                            >
                                {{ t('proposals.all.columns.actions') }}
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
                            <td class="px-4 py-3">
                                <StatusPill
                                    :label="group.statusLabel"
                                    :tone="group.statusTone"
                                />
                            </td>
                            <td class="px-4 py-3 font-medium">
                                {{ group.client ?? '—' }}
                                <span class="block text-[13px] font-normal text-muted">{{ group.season }}</span>
                            </td>
                            <td class="px-4 py-3">
                                {{ group.agent }}
                            </td>
                            <td class="max-w-56 px-4 py-3 text-muted">
                                {{ group.clubs || '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono whitespace-nowrap">
                                <span
                                    v-for="(total, index) in group.totals"
                                    :key="index"
                                    class="block"
                                >
                                    <span
                                        v-if="total.letter"
                                        class="text-muted"
                                    >{{ total.letter }} · </span>€ {{ total.total }}
                                    <span
                                        v-if="total.final"
                                        class="block font-sans text-[13px] text-muted"
                                    >{{ t('proposals.all.final', { total: total.final }) }}</span>
                                    <span
                                        v-if="total.status"
                                        class="block font-sans text-[13px] text-muted"
                                    >{{ total.status }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-muted">
                                {{ group.updatedAt }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                <Link
                                    :href="group.url"
                                    :class="buttonClasses.secondary"
                                    :aria-label="t('proposals.all.open_label', { number: String(group.number) })"
                                >
                                    {{ t('proposals.all.open') }}
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
                                <StatusPill
                                    :label="group.statusLabel"
                                    :tone="group.statusTone"
                                />
                                <span class="truncate font-medium">{{ group.client ?? '—' }}</span>
                            </div>
                            <p class="mt-1 text-sm text-muted">
                                {{ group.agent }} · {{ group.season }} · {{ group.updatedAt }}
                            </p>
                            <p class="text-sm text-muted">
                                {{ group.clubs }}
                            </p>
                            <p class="text-sm">
                                <span
                                    v-for="(total, index) in group.totals"
                                    :key="index"
                                    class="font-mono"
                                ><template v-if="index > 0"> · </template><template v-if="total.letter">{{ total.letter }} </template>€ {{ total.total }}</span>
                            </p>
                        </div>
                        <Link
                            :href="group.url"
                            :class="buttonClasses.secondary"
                            class="shrink-0"
                            :aria-label="t('proposals.all.open_label', { number: String(group.number) })"
                        >
                            {{ t('proposals.all.open') }}
                        </Link>
                    </li>
                </ul>
            </div>

            <p
                v-if="groups.length >= limit"
                class="text-sm text-muted"
            >
                {{ t('proposals.all.limit', { limit: String(limit) }) }}
            </p>
        </div>
    </AppLayout>
</template>
