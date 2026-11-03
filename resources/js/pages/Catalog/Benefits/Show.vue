<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Lock } from '@lucide/vue';
import { reactive, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import OnTopTexts from '@/components/catalog/OnTopTexts.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, OnTopData, PriceListInfo } from '@/types';

/**
 * Benefit and on-top texts of a club in a price list (M03 F-08, F-10; D-117, D-335): one optional
 * text per partnership tier and benefit category, saved tier by tier, and the on-top texts of the
 * club. Plain text with line breaks. The admin writes them, the manager reads them.
 */
interface BenefitCategoryText {
    id: number;
    name: string;
    body: string;
}

interface BenefitTier {
    id: number;
    name: string;
    saveUrl: string;
    categories: BenefitCategoryText[];
}

defineProps<{
    layout: LayoutData;
    season: { id: number; code: string; readOnly: boolean };
    canManage: boolean;
    priceList: PriceListInfo;
    club: { id: number; name: string };
    tiers: BenefitTier[];
    onTop: OnTopData;
    backUrl: string;
}>();

const { t } = useTrans();

// Texts being edited, per tier and category (initialised from the server, reset after each save).
const drafts = reactive<Record<number, Record<number, string>>>({});
const errors = ref<Record<number, Record<string, string>>>({});
const savingTier = ref<number | null>(null);

function draftsOf(tier: BenefitTier): Record<number, string> {
    if (drafts[tier.id] === undefined) {
        drafts[tier.id] = Object.fromEntries(tier.categories.map((category) => [category.id, category.body]));
    }

    return drafts[tier.id] as Record<number, string>;
}

function save(tier: BenefitTier): void {
    savingTier.value = tier.id;
    router.put(tier.saveUrl, { partnership_tier_id: tier.id, texts: draftsOf(tier) }, {
        preserveScroll: true,
        onSuccess: () => {
            errors.value = { ...errors.value, [tier.id]: {} };
            delete drafts[tier.id];
        },
        onError: (received) => {
            errors.value = { ...errors.value, [tier.id]: received };
        },
        onFinish: () => {
            savingTier.value = null;
        },
    });
}

function errorOf(tier: BenefitTier, category: BenefitCategoryText): string | undefined {
    return errors.value[tier.id]?.[`texts.${category.id}`];
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-4xl flex-col gap-5 p-4 md:p-6">
            <Link
                :href="backUrl"
                class="inline-flex min-h-11 items-center gap-2 self-start text-sm font-medium text-ink-soft hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
            >
                <ArrowLeft
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('catalog.benefits.back') }}
            </Link>

            <header class="flex flex-wrap items-center gap-3">
                <h1 class="text-xl font-semibold">
                    {{ club.name }}
                </h1>
                <StatusPill
                    :label="priceList.statusLabel"
                    :tone="priceList.isPublished ? 'success' : 'notice'"
                />
            </header>
            <p class="text-sm leading-relaxed text-muted">
                {{ t('catalog.benefits.intro', { season: season.code }) }}
            </p>
            <p
                v-if="season.readOnly"
                class="flex items-start gap-2 rounded-[10px] bg-surface px-4 py-3 text-sm text-ink-soft"
            >
                <Lock
                    :size="18"
                    :stroke-width="2"
                    class="mt-0.5 shrink-0"
                    aria-hidden="true"
                />
                {{ t('catalog.benefits.closed_note', { season: season.code }) }}
            </p>
            <p
                v-else-if="!canManage"
                class="text-sm text-muted"
            >
                {{ t('catalog.benefits.read_only_note') }}
            </p>

            <p
                v-if="tiers.length === 0"
                class="text-sm text-muted"
            >
                {{ t('catalog.benefits.no_tiers') }}
            </p>

            <section
                v-for="tier in tiers"
                :key="tier.id"
                class="rounded-[14px] border border-line bg-white"
            >
                <header class="border-b border-line px-4 py-3">
                    <h2 class="text-base font-semibold">
                        {{ t('catalog.benefits.tier_title', { tier: tier.name }) }}
                    </h2>
                </header>

                <form
                    v-if="canManage"
                    class="flex flex-col gap-4 p-4"
                    @submit.prevent="save(tier)"
                >
                    <div
                        v-for="category in tier.categories"
                        :key="category.id"
                        class="flex flex-col gap-1.5"
                    >
                        <label
                            :for="`benefit-${tier.id}-${category.id}`"
                            class="text-sm font-medium text-ink"
                        >{{ category.name }}</label>
                        <textarea
                            :id="`benefit-${tier.id}-${category.id}`"
                            v-model="draftsOf(tier)[category.id]"
                            rows="3"
                            class="rounded-[10px] border border-line-strong bg-white px-3 py-2 text-[15px] focus-visible:outline-2 focus-visible:outline-accent"
                            :aria-invalid="errorOf(tier, category) !== undefined"
                        />
                        <p
                            v-if="errorOf(tier, category)"
                            class="text-sm text-accent"
                        >
                            {{ errorOf(tier, category) }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        class="self-start"
                        :class="buttonClasses.primary"
                        :disabled="savingTier !== null"
                    >
                        {{ t('catalog.benefits.save', { tier: tier.name }) }}
                    </button>
                </form>

                <dl
                    v-else
                    class="flex flex-col gap-3 p-4"
                >
                    <div
                        v-for="category in tier.categories"
                        :key="category.id"
                    >
                        <dt class="text-sm font-medium text-ink">
                            {{ category.name }}
                        </dt>
                        <dd
                            class="mt-0.5 text-sm whitespace-pre-line"
                            :class="category.body ? 'text-ink-soft' : 'text-muted'"
                        >
                            {{ category.body || t('catalog.benefits.none') }}
                        </dd>
                    </div>
                </dl>
            </section>

            <OnTopTexts
                :title="t('catalog.on_top.title_club', { club: club.name })"
                :intro="t('catalog.on_top.intro_club')"
                :data="onTop"
                :can-manage="canManage"
            />
        </div>
    </AppLayout>
</template>
