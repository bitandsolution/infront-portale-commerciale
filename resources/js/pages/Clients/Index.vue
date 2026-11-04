<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Building2, ChevronRight, Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import EmptyState from '@/components/states/EmptyState.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ClientRow, LayoutData } from '@/types';

/**
 * Client lists (M05 F-01; D-076): "I miei clienti" for the agent (only his own clients, without the
 * owner column), "Elenco clienti" for admin and manager (every client, with its agent). The list
 * arrives from the server already filtered by the owner scope; the search works in the browser.
 */
const props = defineProps<{
    layout: LayoutData;
    clients: ClientRow[];
    withOwner: boolean;
    createUrl: string | null;
}>();

const { t } = useTrans();

const search = ref('');

const scope = computed(() => (props.withOwner ? 'all' : 'mine'));

const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('it');

    return props.clients.filter(
        (client) =>
            term === ''
            || client.companyName.toLocaleLowerCase('it').includes(term)
            || (client.vatNumber ?? '').includes(term)
            || client.email.toLocaleLowerCase('it').includes(term),
    );
});
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-muted">
                        {{ t('clients.list.count', { count: String(filtered.length) }) }}
                    </p>
                    <p class="mt-1 max-w-2xl text-sm leading-relaxed text-muted">
                        {{ t(`clients.list.intro_${scope}`) }}
                    </p>
                </div>
                <Link
                    v-if="createUrl"
                    :href="createUrl"
                    :class="buttonClasses.primary"
                >
                    <Plus
                        :size="18"
                        :stroke-width="2.2"
                        aria-hidden="true"
                    />
                    {{ t('clients.list.new') }}
                </Link>
            </div>

            <div
                v-if="clients.length > 0"
                class="rounded-[14px] border border-line bg-white p-4"
            >
                <label
                    for="clients-search"
                    class="text-sm font-medium text-ink"
                >{{ t('clients.list.search') }}</label>
                <div class="relative mt-1.5">
                    <Search
                        :size="18"
                        :stroke-width="2"
                        class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted"
                        aria-hidden="true"
                    />
                    <input
                        id="clients-search"
                        v-model="search"
                        type="search"
                        autocomplete="off"
                        class="min-h-11 w-full rounded-[10px] border border-line-strong bg-white pr-3.5 pl-10 text-[15px] text-ink outline-none focus:border-accent focus:ring-2 focus:ring-accent/20"
                    >
                </div>
            </div>

            <div class="overflow-hidden rounded-[14px] border border-line bg-white">
                <EmptyState
                    v-if="clients.length === 0"
                    :title="t(`clients.list.empty_title_${scope}`)"
                    :message="t(`clients.list.empty_message_${scope}`)"
                >
                    <template #icon>
                        <Building2
                            :size="34"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                    </template>
                    <template
                        v-if="createUrl"
                        #action
                    >
                        <Link
                            :href="createUrl"
                            :class="buttonClasses.primary"
                        >
                            {{ t('clients.list.new') }}
                        </Link>
                    </template>
                </EmptyState>

                <p
                    v-else-if="filtered.length === 0"
                    class="px-4 py-8 text-center text-sm text-muted"
                >
                    {{ t('clients.list.empty_search') }}
                </p>

                <table
                    v-if="filtered.length > 0"
                    class="hidden w-full text-left text-sm lg:table"
                >
                    <thead class="border-b border-line bg-surface text-[13px] text-muted">
                        <tr>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('clients.list.columns.client') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('clients.list.columns.vat_number') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('clients.list.columns.sector') }}
                            </th>
                            <th
                                v-if="withOwner"
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('clients.list.columns.owner') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('clients.list.columns.status') }}
                            </th>
                            <th
                                scope="col"
                                class="w-12 px-2 py-3"
                            >
                                <span class="sr-only">{{ t('clients.list.columns.client') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="client in filtered"
                            :key="client.id"
                            class="border-b border-line last:border-b-0 hover:bg-surface/60"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img
                                        :src="client.logoUrl"
                                        :alt="t('clients.list.logo_alt', { client: client.companyName })"
                                        class="size-10 shrink-0 rounded-md border border-line bg-white object-contain p-0.5"
                                        loading="lazy"
                                    >
                                    <div class="min-w-0">
                                        <Link
                                            :href="client.url"
                                            class="font-semibold text-ink underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                                        >
                                            {{ client.companyName }}
                                        </Link>
                                        <div class="truncate text-[13px] text-muted">
                                            {{ client.email }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono text-[13px]">
                                <span v-if="client.vatNumber">{{ client.vatNumber }}</span>
                                <span
                                    v-else
                                    class="text-muted"
                                >—</span>
                            </td>
                            <td class="px-4 py-3">
                                {{ client.sector }}
                            </td>
                            <td
                                v-if="withOwner"
                                class="px-4 py-3"
                            >
                                {{ client.owner }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusPill
                                    :label="client.isActive ? t('clients.active') : t('clients.inactive')"
                                    :tone="client.isActive ? 'success' : 'muted'"
                                />
                            </td>
                            <td class="px-2 py-3">
                                <Link
                                    :href="client.url"
                                    class="flex size-11 items-center justify-center rounded-[10px] text-muted hover:bg-surface hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
                                    :aria-label="t('clients.list.open', { client: client.companyName })"
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
                    class="divide-y divide-line lg:hidden"
                >
                    <li
                        v-for="client in filtered"
                        :key="client.id"
                    >
                        <Link
                            :href="client.url"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                        >
                            <img
                                :src="client.logoUrl"
                                :alt="t('clients.list.logo_alt', { client: client.companyName })"
                                class="size-11 shrink-0 rounded-md border border-line bg-white object-contain p-0.5"
                                loading="lazy"
                            >
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold">
                                    {{ client.companyName }}
                                </div>
                                <div class="truncate text-[13px] text-muted">
                                    {{ withOwner && client.owner ? `${client.sector} · ${client.owner}` : client.sector }}
                                </div>
                                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                    <StatusPill
                                        :label="client.isActive ? t('clients.active') : t('clients.inactive')"
                                        :tone="client.isActive ? 'success' : 'muted'"
                                    />
                                    <span
                                        v-if="client.vatNumber"
                                        class="font-mono text-[13px] text-muted"
                                    >{{ client.vatNumber }}</span>
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
