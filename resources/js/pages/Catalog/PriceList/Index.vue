<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarDays, Lock, Tags, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import OnTopTexts from '@/components/catalog/OnTopTexts.vue';
import PriceGridTable from '@/components/catalog/PriceGridTable.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, OnTopData, PriceColumn, PriceListInfo, PriceRow } from '@/types';

/**
 * Pages "Catalogo › Listini LED", "Partnership" and "Hospitality" (M03 F-05, F-07, F-09, F-11;
 * D-333, D-337; mockup "Listino LED"): the grid club × value of the price list of the season of the
 * top bar. The admin creates the price list, writes the amounts cell by cell and publishes it after
 * a confirmation, from any of the three pages; the manager only reads. On "Partnership" also the
 * general on-top texts and, from each club, its benefit texts (D-335).
 */
interface PriceListPage {
    kind: 'led' | 'partnership' | 'hospitality';
    season: { id: number; code: string; readOnly: boolean };
    canManage: boolean;
    priceList: PriceListInfo | null;
    createUrl: string;
    competitions: { id: number; name: string }[];
    columns: PriceColumn[];
    rows: PriceRow[];
    withoutOffers: string[];
    onTop: OnTopData | null;
}

const props = defineProps<{
    layout: LayoutData;
    page: PriceListPage | null;
}>();

const { t } = useTrans();

const editable = computed(() => props.page !== null && props.page.canManage && props.page.priceList !== null);

const publishedNote = computed(() => {
    const priceList = props.page?.priceList;
    if (!priceList?.isPublished) {
        return '';
    }

    return priceList.publishedBy
        ? t('catalog.price_list.published_note', { date: priceList.publishedAt ?? '', name: priceList.publishedBy })
        : t('catalog.price_list.published_note_system', { date: priceList.publishedAt ?? '' });
});

const creating = ref(false);

function createPriceList(): void {
    if (props.page === null) {
        return;
    }
    creating.value = true;
    router.post(props.page.createUrl, { season_id: props.page.season.id }, {
        preserveScroll: true,
        onFinish: () => {
            creating.value = false;
        },
    });
}

const confirmingPublish = ref(false);
const publishing = ref(false);

function publish(): void {
    const priceList = props.page?.priceList;
    if (!priceList) {
        return;
    }
    publishing.value = true;
    router.post(priceList.publishUrl, {}, {
        preserveScroll: true,
        onFinish: () => {
            publishing.value = false;
            confirmingPublish.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <EmptyState
                v-if="page === null"
                class="rounded-[14px] border border-line bg-white"
                :title="t('catalog.price_list.no_season_title')"
                :message="t('catalog.price_list.no_season')"
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
                <div class="flex flex-wrap items-center gap-3">
                    <StatusPill
                        v-if="page.priceList"
                        :label="page.priceList.statusLabel"
                        :tone="page.priceList.isPublished ? 'success' : 'notice'"
                    />
                    <p class="min-w-0 flex-1 text-sm leading-relaxed text-muted">
                        {{ t(`catalog.price_list.kinds.${page.kind}.intro`, { season: page.season.code }) }}
                    </p>
                    <button
                        v-if="page.canManage && page.priceList && !page.priceList.isPublished"
                        type="button"
                        :class="buttonClasses.primary"
                        @click="confirmingPublish = true"
                    >
                        {{ t('catalog.price_list.publish') }}
                    </button>
                </div>

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
                    {{ t('catalog.price_list.closed_note', { season: page.season.code }) }}
                </p>

                <EmptyState
                    v-if="page.priceList === null"
                    class="rounded-[14px] border border-line bg-white"
                    :title="t('catalog.price_list.missing_title')"
                    :message="page.canManage ? t('catalog.price_list.missing_admin', { season: page.season.code }) : t('catalog.price_list.missing_reader', { season: page.season.code })"
                >
                    <template #icon>
                        <Tags
                            :size="34"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                    </template>
                    <template
                        v-if="page.canManage"
                        #action
                    >
                        <button
                            type="button"
                            :class="buttonClasses.primary"
                            :disabled="creating"
                            @click="createPriceList"
                        >
                            {{ t('catalog.price_list.create', { season: page.season.code }) }}
                        </button>
                    </template>
                </EmptyState>

                <template v-else>
                    <p class="text-sm leading-relaxed text-ink-soft">
                        {{ page.priceList.isPublished ? publishedNote : t('catalog.price_list.draft_note') }}
                    </p>
                    <p
                        v-if="editable || !page.season.readOnly"
                        class="text-sm leading-relaxed text-muted"
                    >
                        {{ editable ? t('catalog.price_list.edit_hint') : t('catalog.price_list.read_only_note') }}
                    </p>

                    <EmptyState
                        v-if="page.rows.length === 0"
                        class="rounded-[14px] border border-line bg-white"
                        :title="t('catalog.price_list.no_clubs_title')"
                        :message="t('catalog.price_list.no_clubs', { season: page.season.code })"
                    >
                        <template #icon>
                            <Tags
                                :size="34"
                                :stroke-width="1.6"
                                aria-hidden="true"
                            />
                        </template>
                    </EmptyState>

                    <PriceGridTable
                        v-else
                        :kind="page.kind"
                        :columns="page.columns"
                        :rows="page.rows"
                        :competitions="page.competitions"
                        :editable="editable"
                        :update-url="page.priceList.updateUrl"
                    />

                    <OnTopTexts
                        v-if="page.onTop"
                        :title="t('catalog.on_top.title_general')"
                        :intro="t('catalog.on_top.intro_general')"
                        :data="page.onTop"
                        :can-manage="page.canManage"
                    />
                </template>
            </template>
        </div>

        <ConfirmDialog
            v-if="page && page.priceList"
            :open="confirmingPublish"
            :title="t('catalog.price_list.publish_title', { season: page.season.code })"
            :confirm-label="t('catalog.price_list.publish_confirm')"
            :processing="publishing"
            @confirm="publish"
            @cancel="confirmingPublish = false"
        >
            <p>{{ t('catalog.price_list.publish_body') }}</p>
            <div
                v-if="page.withoutOffers.length > 0"
                class="flex items-start gap-2 rounded-[10px] border border-notice-line bg-notice-soft p-3 text-notice"
            >
                <TriangleAlert
                    :size="18"
                    :stroke-width="2"
                    class="mt-0.5 shrink-0"
                    aria-hidden="true"
                />
                <div>
                    <p>{{ t('catalog.price_list.publish_warning') }}</p>
                    <ul class="mt-1 list-disc pl-5">
                        <li
                            v-for="club in page.withoutOffers"
                            :key="club"
                        >
                            {{ club }}
                        </li>
                    </ul>
                </div>
            </div>
        </ConfirmDialog>
    </AppLayout>
</template>
