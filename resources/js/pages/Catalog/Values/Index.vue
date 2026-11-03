<script setup lang="ts">
import { computed } from 'vue';
import CatalogValueTable from '@/components/catalog/CatalogValueTable.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CatalogSystemValue, CatalogValueRow, LayoutData } from '@/types';

/**
 * Page "Catalogo › Valori" (M03 F-01…F-04, F-06; D-331): admin only. Values and codes come from the
 * system data (D-332): nothing is created or deleted here. Territories, visibility types and pools
 * are read-only; formats, tiers, variants and categories open their page.
 */
const props = defineProps<{
    layout: LayoutData;
    territories: CatalogSystemValue[];
    visibilityTypes: CatalogSystemValue[];
    pools: CatalogSystemValue[];
    formats: CatalogValueRow[];
    tiers: CatalogValueRow[];
    variants: CatalogValueRow[];
    categories: CatalogValueRow[];
}>();

const { t } = useTrans();

const systemGroups = computed(() => [
    { key: 'territories', title: t('catalog.values.territories'), items: props.territories },
    { key: 'visibility_types', title: t('catalog.values.visibility_types'), items: props.visibilityTypes },
    { key: 'pools', title: t('catalog.values.pools'), items: props.pools },
]);
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <p class="max-w-3xl text-sm leading-relaxed text-muted">
                {{ t('catalog.values.intro') }}
            </p>

            <CatalogValueTable
                :title="t('catalog.values.formats')"
                :intro="t('catalog.values.formats_intro')"
                :values="formats"
            />
            <CatalogValueTable
                :title="t('catalog.values.tiers')"
                :intro="t('catalog.values.tiers_intro')"
                :values="tiers"
            />
            <CatalogValueTable
                :title="t('catalog.values.variants')"
                :values="variants"
            />
            <CatalogValueTable
                :title="t('catalog.values.categories')"
                :intro="t('catalog.values.categories_intro')"
                :values="categories"
            />

            <section class="rounded-[14px] border border-line bg-white">
                <header class="border-b border-line px-4 py-3">
                    <h2 class="text-base font-semibold">
                        {{ t('catalog.values.system_title') }}
                    </h2>
                    <p class="mt-0.5 text-sm text-muted">
                        {{ t('catalog.values.system_intro') }}
                    </p>
                </header>

                <div class="grid grid-cols-1 gap-x-6 gap-y-5 p-4 md:grid-cols-3">
                    <div
                        v-for="group in systemGroups"
                        :key="group.key"
                    >
                        <h3 class="text-sm font-semibold text-ink">
                            {{ group.title }}
                        </h3>
                        <p
                            v-if="group.items.length === 0"
                            class="mt-2 text-sm text-muted"
                        >
                            {{ t('catalog.values.empty_message') }}
                        </p>
                        <ul
                            v-else
                            class="mt-2 divide-y divide-line rounded-[10px] border border-line"
                        >
                            <li
                                v-for="item in group.items"
                                :key="item.code"
                                class="flex min-h-11 items-center justify-between gap-3 px-3 py-2 text-sm"
                            >
                                <div class="min-w-0">
                                    <div class="font-medium">
                                        {{ item.name }}
                                    </div>
                                    <div class="font-mono text-[12px] text-muted">
                                        {{ item.code }}<template v-if="item.visibilityType">
                                            · {{ item.visibilityType }}
                                        </template>
                                    </div>
                                </div>
                                <StatusPill
                                    :label="item.isActive ? t('catalog.active') : t('catalog.inactive')"
                                    :tone="item.isActive ? 'success' : 'muted'"
                                />
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
