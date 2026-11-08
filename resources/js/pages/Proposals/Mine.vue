<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { FileText, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, ProposalDraftRow } from '@/types';

/**
 * "Le mie proposte" (D-374; F4-02): the agent's drafts, the most recent first, reopened in the
 * configurator or deleted with confirmation (M07 RB-08, ux-architettura §4.5). The other states of the
 * proposals arrive with F5, "Duplica" with F4-05. Table from 1024 px, cards below.
 */
const props = defineProps<{
    layout: LayoutData;
    drafts: ProposalDraftRow[];
    newUrl: string;
}>();

const { t } = useTrans();

const deleting = ref<ProposalDraftRow | null>(null);
const processing = ref(false);

const count = computed(() => t('proposals.mine.count', { count: String(props.drafts.length) }));

function confirmDelete(): void {
    if (deleting.value === null) {
        return;
    }
    processing.value = true;
    router.delete(deleting.value.deleteUrl, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            deleting.value = null;
        },
    });
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-muted">
                        {{ count }}
                    </p>
                    <p class="mt-1 max-w-2xl text-sm leading-relaxed text-muted">
                        {{ t('proposals.mine.intro') }}
                    </p>
                </div>
                <Link
                    :href="newUrl"
                    :class="buttonClasses.primary"
                >
                    <Plus
                        :size="18"
                        :stroke-width="2.2"
                        aria-hidden="true"
                    />
                    {{ t('proposals.mine.new') }}
                </Link>
            </div>

            <div class="overflow-hidden rounded-[14px] border border-line bg-white">
                <EmptyState
                    v-if="drafts.length === 0"
                    :title="t('proposals.mine.empty_title')"
                    :message="t('proposals.mine.empty_message')"
                >
                    <template #icon>
                        <FileText
                            :size="34"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                    </template>
                    <template #action>
                        <Link
                            :href="newUrl"
                            :class="buttonClasses.primary"
                        >
                            {{ t('proposals.mine.empty_action') }}
                        </Link>
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
                                {{ t('proposals.mine.columns.number') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('proposals.mine.columns.client') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('proposals.mine.columns.season') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 text-right font-medium"
                            >
                                {{ t('proposals.mine.columns.total') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('proposals.mine.columns.updated') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('proposals.mine.columns.status') }}
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
                            v-for="draft in drafts"
                            :key="draft.id"
                            class="hover:bg-surface/60"
                        >
                            <td class="px-4 py-3 font-mono font-semibold">
                                {{ draft.number }}
                            </td>
                            <td class="px-4 py-3 font-medium">
                                {{ draft.client }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="block">{{ draft.season }}</span>
                                <span class="block text-[13px] text-muted">{{ draft.period ?? t('proposals.mine.no_period') }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                € {{ draft.listTotal }}
                            </td>
                            <td class="px-4 py-3 text-muted">
                                {{ draft.updatedAt }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusPill
                                    :label="t('proposals.draft_badge')"
                                    tone="muted"
                                />
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="draft.editUrl"
                                        :class="buttonClasses.secondary"
                                        :aria-label="t('proposals.mine.open', { number: String(draft.number) })"
                                    >
                                        {{ t('proposals.mine.reopen') }}
                                    </Link>
                                    <button
                                        type="button"
                                        class="flex size-11 items-center justify-center rounded-[10px] text-muted hover:bg-accent-soft hover:text-accent focus-visible:outline-2 focus-visible:outline-accent"
                                        :aria-label="t('proposals.mine.delete', { number: String(draft.number) })"
                                        @click="deleting = draft"
                                    >
                                        <Trash2
                                            :size="18"
                                            :stroke-width="2"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <ul
                    v-if="drafts.length > 0"
                    class="divide-y divide-line lg:hidden"
                >
                    <li
                        v-for="draft in drafts"
                        :key="draft.id"
                        class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono font-semibold">{{ draft.number }}</span>
                                <span class="truncate font-medium">{{ draft.client }}</span>
                                <StatusPill
                                    :label="t('proposals.draft_badge')"
                                    tone="muted"
                                />
                            </div>
                            <p class="mt-1 text-sm text-muted">
                                {{ draft.season }} · {{ draft.period ?? t('proposals.mine.no_period') }}
                            </p>
                            <p class="text-sm text-muted">
                                <span class="font-mono text-ink">€ {{ draft.listTotal }}</span>
                                · {{ t('proposals.mine.updated', { date: draft.updatedAt }) }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <Link
                                :href="draft.editUrl"
                                :class="buttonClasses.secondary"
                                class="flex-1 sm:flex-none"
                                :aria-label="t('proposals.mine.open', { number: String(draft.number) })"
                            >
                                {{ t('proposals.mine.reopen') }}
                            </Link>
                            <button
                                type="button"
                                class="flex size-11 items-center justify-center rounded-[10px] border border-line-strong text-muted hover:bg-accent-soft hover:text-accent focus-visible:outline-2 focus-visible:outline-accent"
                                :aria-label="t('proposals.mine.delete', { number: String(draft.number) })"
                                @click="deleting = draft"
                            >
                                <Trash2
                                    :size="18"
                                    :stroke-width="2"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <ConfirmDialog
            :open="deleting !== null"
            :title="t('proposals.delete.confirm_title', { number: String(deleting?.number ?? '') })"
            :confirm-label="t('proposals.delete.confirm')"
            :processing="processing"
            @cancel="deleting = null"
            @confirm="confirmDelete"
        >
            <p>{{ t('proposals.delete.confirm_text', { number: String(deleting?.number ?? '') }) }}</p>
        </ConfirmDialog>
    </AppLayout>
</template>
