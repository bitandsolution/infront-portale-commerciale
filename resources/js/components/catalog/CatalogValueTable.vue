<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import type { CatalogValueRow } from '@/types';

/**
 * One kind of value of the catalogue the admin changes (D-331): table on desktop and tablet, list
 * on the smartphone. Each row opens the page of the value.
 */
const props = defineProps<{
    title: string;
    intro?: string;
    values: CatalogValueRow[];
}>();

const { t } = useTrans();

const withDescription = computed(() => props.values.some((value) => value.kind === 'led_format'));
const withStatus = computed(() => props.values.some((value) => value.isActive !== null));
const withNotes = computed(() => props.values.some((value) => value.notes.length > 0));
</script>

<template>
    <section class="overflow-hidden rounded-[14px] border border-line bg-white">
        <header class="border-b border-line px-4 py-3">
            <h2 class="text-base font-semibold">
                {{ title }}
            </h2>
            <p
                v-if="intro"
                class="mt-0.5 text-sm text-muted"
            >
                {{ intro }}
            </p>
        </header>

        <p
            v-if="values.length === 0"
            class="px-4 py-6 text-sm text-muted"
        >
            {{ t('catalog.values.empty_message') }}
        </p>

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
                        {{ t('catalog.values.columns.sort_order') }}
                    </th>
                    <th
                        scope="col"
                        class="px-4 py-3 font-medium"
                    >
                        {{ t('catalog.values.columns.name') }}
                    </th>
                    <th
                        scope="col"
                        class="px-4 py-3 font-medium"
                    >
                        {{ t('catalog.values.columns.code') }}
                    </th>
                    <th
                        v-if="withDescription"
                        scope="col"
                        class="px-4 py-3 font-medium"
                    >
                        {{ t('catalog.values.columns.description') }}
                    </th>
                    <th
                        v-if="withNotes"
                        scope="col"
                        class="px-4 py-3 font-medium"
                    >
                        {{ t('catalog.values.columns.notes') }}
                    </th>
                    <th
                        v-if="withStatus"
                        scope="col"
                        class="px-4 py-3 font-medium"
                    >
                        {{ t('catalog.values.columns.status') }}
                    </th>
                    <th
                        scope="col"
                        class="w-12 px-2 py-3"
                    >
                        <span class="sr-only">{{ t('catalog.values.columns.name') }}</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="value in values"
                    :key="value.id"
                    class="border-b border-line last:border-b-0 hover:bg-surface/60"
                >
                    <td class="px-4 py-3 font-mono text-[13px] text-muted">
                        {{ value.sortOrder }}
                    </td>
                    <td class="px-4 py-3">
                        <Link
                            :href="value.url"
                            class="font-semibold text-ink underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                        >
                            {{ value.name }}
                        </Link>
                    </td>
                    <td class="px-4 py-3 font-mono text-[13px]">
                        {{ value.code }}
                    </td>
                    <td
                        v-if="withDescription"
                        class="px-4 py-3 text-ink-soft"
                    >
                        {{ value.description ?? '—' }}
                    </td>
                    <td
                        v-if="withNotes"
                        class="px-4 py-3 text-[13px] text-muted"
                    >
                        {{ value.notes.join(' · ') || '—' }}
                    </td>
                    <td
                        v-if="withStatus"
                        class="px-4 py-3"
                    >
                        <StatusPill
                            v-if="value.isActive !== null"
                            :label="value.isActive ? t('catalog.active') : t('catalog.inactive')"
                            :tone="value.isActive ? 'success' : 'muted'"
                        />
                    </td>
                    <td class="px-2 py-3">
                        <Link
                            :href="value.url"
                            class="flex size-11 items-center justify-center rounded-[10px] text-muted hover:bg-surface hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
                            :aria-label="t('catalog.values.open', { value: value.name })"
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
            v-if="values.length > 0"
            class="divide-y divide-line md:hidden"
        >
            <li
                v-for="value in values"
                :key="value.id"
            >
                <Link
                    :href="value.url"
                    class="flex items-center gap-3 px-4 py-3 hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                >
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold">
                            {{ value.name }}
                        </div>
                        <div class="font-mono text-[13px] text-muted">
                            {{ value.code }}
                        </div>
                        <div
                            v-if="value.notes.length > 0"
                            class="text-[13px] text-muted"
                        >
                            {{ value.notes.join(' · ') }}
                        </div>
                        <div
                            v-if="value.isActive !== null"
                            class="mt-1.5"
                        >
                            <StatusPill
                                :label="value.isActive ? t('catalog.active') : t('catalog.inactive')"
                                :tone="value.isActive ? 'success' : 'muted'"
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
    </section>
</template>
