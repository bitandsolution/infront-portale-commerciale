<script setup lang="ts">
import { AlertTriangle, Info } from '@lucide/vue';
import { computed } from 'vue';
import { buttonClasses } from '@/components/buttons';
import { useTrans } from '@/composables/useTrans';
import type { ProposalDraft } from '@/types';

/**
 * Step 5 of the configurator, "Riepilogo" (M06 §6, §9; D-093, D-373; mockup "Riepilogo"; F4-04): KPI of
 * the campaign, a row per club (package, format, minutes, matches of the period, spots per match =
 * minutes × 4, hospitality, list price), the list total and the warnings. Every number comes from the
 * server (DraftEvaluator, MatchCounter, PriceCalculator): the page only shows it. No discount field
 * (D-011, D-086). "Invia in approvazione" is in the configurator (DM-03a).
 */
const props = defineProps<{
    draft: ProposalDraft;
    clientName: string | null;
    sector: string | null;
    /** DM-03a: the detail of a group shows the summary without the configurator's actions (D-411). */
    readOnly?: boolean;
}>();

const emit = defineEmits<{ fix: [] }>();

const { t } = useTrans();

const documentKind = computed(() => (props.draft.clubs.length >= 2 ? t('proposals.summary.document_network') : t('proposals.summary.document_club')));
const errorWarnings = computed(() => props.draft.warnings.filter((warning) => warning.kind === 'error'));
const infoWarnings = computed(() => props.draft.warnings.filter((warning) => warning.kind === 'info'));
const campaign = computed(() => props.draft.campaign);

function hasProblems(errors: Record<string, string>): boolean {
    return Object.keys(errors).length > 0;
}
</script>

<template>
    <section
        class="flex flex-col gap-4"
        aria-labelledby="step-summary"
    >
        <div class="flex flex-col gap-1">
            <h3
                id="step-summary"
                class="text-base font-semibold"
            >
                {{ t('proposals.summary.title') }}
                <span class="font-normal text-ink-soft">· {{ clientName }} · {{ documentKind }}</span>
            </h3>
            <p class="text-sm text-muted">
                <template v-if="draft.periodLabel">
                    {{ draft.periodLabel }}
                </template>
                <template v-if="sector">
                    · {{ t('proposals.summary.sector', { sector }) }}
                </template>
            </p>
            <p
                v-if="!readOnly"
                class="text-sm leading-relaxed text-muted"
            >
                {{ t('proposals.summary.intro') }}
            </p>
        </div>

        <!-- D-373: reached only without clubs lacking elements; kept as a fallback. -->
        <div
            v-if="draft.blocksSummary"
            class="flex flex-col items-start gap-3 rounded-[10px] bg-accent-soft px-3.5 py-3 text-sm text-ink"
            role="alert"
        >
            <p>{{ t('proposals.summary.blocked') }}</p>
            <button
                v-if="!readOnly"
                type="button"
                :class="buttonClasses.secondary"
                @click="emit('fix')"
            >
                {{ t('proposals.summary.warnings.fix') }}
            </button>
        </div>

        <!-- KPI of the campaign (D-093) -->
        <div :aria-label="t('proposals.summary.kpi.label')">
            <dl
                v-if="campaign"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3"
            >
                <div class="rounded-[12px] border border-line bg-white px-3.5 py-3">
                    <dt class="text-[13px] text-muted">
                        {{ t('proposals.summary.kpi.clubs') }}
                    </dt>
                    <dd class="text-2xl font-semibold">
                        {{ campaign.clubs }}
                    </dd>
                </div>
                <div class="rounded-[12px] border border-line bg-white px-3.5 py-3">
                    <dt class="text-[13px] text-muted">
                        {{ t('proposals.summary.kpi.matches') }}
                    </dt>
                    <dd class="text-2xl font-semibold">
                        {{ campaign.totalMatches }}
                    </dd>
                </div>
                <div class="rounded-[12px] border border-line bg-white px-3.5 py-3">
                    <dt class="text-[13px] text-muted">
                        {{ t('proposals.summary.kpi.led_matches') }}
                    </dt>
                    <dd class="text-2xl font-semibold">
                        {{ campaign.ledMatches }}
                    </dd>
                </div>
                <div class="rounded-[12px] border border-line bg-white px-3.5 py-3">
                    <dt class="text-[13px] text-muted">
                        {{ t('proposals.summary.kpi.spots') }}
                    </dt>
                    <dd class="text-2xl font-semibold">
                        {{ campaign.totalSpots }}
                    </dd>
                    <dd class="text-[12px] text-muted">
                        {{ t('proposals.summary.kpi.spots_help') }}
                    </dd>
                </div>
                <div class="rounded-[12px] border border-line bg-white px-3.5 py-3">
                    <dt class="text-[13px] text-muted">
                        {{ t('proposals.summary.kpi.months') }}
                    </dt>
                    <dd class="text-2xl font-semibold">
                        {{ campaign.months }}
                    </dd>
                    <dd
                        v-if="campaign.range"
                        class="text-[12px] text-muted"
                    >
                        {{ campaign.range }}
                    </dd>
                </div>
                <div
                    v-if="campaign.topMatchesApplicable"
                    class="rounded-[12px] border border-line bg-white px-3.5 py-3"
                >
                    <dt class="text-[13px] text-muted">
                        {{ t('proposals.summary.kpi.top_matches') }}
                    </dt>
                    <dd class="text-2xl font-semibold">
                        {{ campaign.topMatches }}
                    </dd>
                    <dd class="text-[12px] text-muted">
                        {{ t('proposals.summary.kpi.top_help') }}
                    </dd>
                </div>
            </dl>
            <p
                v-else
                class="rounded-[10px] border border-line bg-white px-3.5 py-3 text-sm text-muted"
            >
                {{ t('proposals.summary.kpi.empty') }}
            </p>
        </div>

        <!-- Warnings (M06 §6, §9) -->
        <div
            v-if="errorWarnings.length > 0"
            class="flex flex-col gap-2 rounded-[10px] bg-accent-soft px-3.5 py-3 text-sm text-ink"
            role="alert"
        >
            <p class="flex items-center gap-2 font-semibold">
                <AlertTriangle
                    :size="18"
                    :stroke-width="2"
                    class="shrink-0 text-accent"
                    aria-hidden="true"
                />
                {{ t('proposals.summary.warnings.errors_title') }}
            </p>
            <ul class="flex list-disc flex-col gap-1 pl-5">
                <li
                    v-for="(warning, index) in errorWarnings"
                    :key="`e${index}`"
                >
                    {{ warning.text }}
                </li>
            </ul>
            <button
                v-if="!readOnly"
                type="button"
                :class="buttonClasses.secondary"
                class="self-start"
                @click="emit('fix')"
            >
                {{ t('proposals.summary.warnings.fix') }}
            </button>
        </div>
        <div
            v-if="infoWarnings.length > 0"
            class="flex gap-2.5 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
            role="status"
        >
            <Info
                :size="18"
                :stroke-width="2"
                class="mt-0.5 shrink-0 text-notice"
                aria-hidden="true"
            />
            <div class="flex flex-col gap-1">
                <p class="font-semibold">
                    {{ t('proposals.summary.warnings.title') }}
                </p>
                <ul class="flex flex-col gap-1">
                    <li
                        v-for="(warning, index) in infoWarnings"
                        :key="`i${index}`"
                    >
                        {{ warning.text }}
                    </li>
                </ul>
            </div>
        </div>

        <!-- Detail by club -->
        <div class="overflow-x-auto rounded-[14px] border border-line bg-white">
            <table class="w-full min-w-[640px] border-collapse text-[13px] md:min-w-0">
                <caption class="sr-only">
                    {{ t('proposals.summary.table.label') }}
                </caption>
                <thead>
                    <tr class="bg-surface text-left text-[12px] tracking-wide text-muted uppercase">
                        <th
                            scope="col"
                            class="px-3 py-2.5 font-semibold"
                        >
                            {{ t('proposals.summary.table.club') }}
                        </th>
                        <th
                            scope="col"
                            class="px-1.5 py-2.5 font-semibold"
                        >
                            {{ t('proposals.summary.table.package') }}
                        </th>
                        <th
                            scope="col"
                            class="px-1.5 py-2.5 font-semibold"
                        >
                            {{ t('proposals.summary.table.format') }}
                        </th>
                        <th
                            scope="col"
                            class="px-1.5 py-2.5 text-right font-semibold"
                        >
                            {{ t('proposals.summary.table.minutes') }}
                        </th>
                        <th
                            scope="col"
                            class="px-1.5 py-2.5 text-right font-semibold"
                        >
                            {{ t('proposals.summary.table.matches') }}
                        </th>
                        <th
                            scope="col"
                            class="px-1.5 py-2.5 text-right font-semibold"
                        >
                            {{ t('proposals.summary.table.spots') }}
                        </th>
                        <th
                            scope="col"
                            class="px-1.5 py-2.5 font-semibold"
                        >
                            {{ t('proposals.summary.table.hospitality') }}
                        </th>
                        <th
                            scope="col"
                            class="px-3 py-2.5 text-right font-semibold"
                        >
                            {{ t('proposals.summary.table.price') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="club in draft.clubs"
                        :key="club.clubId"
                        class="border-t border-skeleton align-top"
                    >
                        <td class="px-3 py-3">
                            <span class="font-semibold">{{ club.name }}</span>
                            <span
                                v-if="club.competition"
                                class="block text-[12px] text-muted"
                            >{{ club.competition }}</span>
                            <span
                                v-if="hasProblems(club.errors)"
                                class="mt-0.5 flex items-center gap-1 text-[12px] font-medium text-accent"
                            >
                                <AlertTriangle
                                    :size="13"
                                    :stroke-width="2.2"
                                    aria-hidden="true"
                                />
                                {{ t('proposals.configuration.errors_count', { count: String(Object.keys(club.errors).length) }) }}
                            </span>
                        </td>
                        <td class="px-1.5 py-3">
                            {{ club.tierName ?? '—' }}
                        </td>
                        <td
                            class="px-1.5 py-3"
                            :class="club.formatName ? '' : 'text-muted'"
                        >
                            {{ club.formatName ?? '—' }}
                        </td>
                        <td
                            class="px-1.5 py-3 text-right"
                            :class="club.ledLabel ? '' : 'text-muted'"
                        >
                            {{ club.ledLabel ?? '—' }}
                        </td>
                        <td
                            class="px-1.5 py-3 text-right"
                            :title="club.matchesLabel ?? undefined"
                        >
                            {{ club.matches ?? '—' }}
                        </td>
                        <td
                            class="px-1.5 py-3 text-right"
                            :class="club.spotsPerMatch !== null ? '' : 'text-muted'"
                        >
                            {{ club.spotsPerMatch ?? '—' }}
                        </td>
                        <td
                            class="px-1.5 py-3"
                            :class="club.hospitalityLabel ? '' : 'text-muted'"
                        >
                            {{ club.hospitalityLabel ?? '—' }}
                        </td>
                        <td class="px-3 py-3 text-right font-mono whitespace-nowrap">
                            <template v-if="club.price !== null">
                                € {{ club.price }}
                            </template>
                            <span
                                v-else
                                class="font-sans text-muted"
                            >{{ t('proposals.summary.table.not_priced') }}</span>
                        </td>
                    </tr>
                    <tr class="border-t-2 border-ink">
                        <td
                            colspan="7"
                            class="px-3 py-3 font-semibold"
                        >
                            {{ t('proposals.summary.table.total') }}
                            <span class="font-normal text-muted">· {{ t('proposals.summary.table.vat') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono text-lg font-medium whitespace-nowrap">
                            € {{ draft.listTotal }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="flex gap-2.5 rounded-[10px] border border-line bg-white px-3.5 py-3 text-[13px] leading-relaxed text-ink-soft">
            <Info
                :size="18"
                :stroke-width="2"
                class="mt-0.5 shrink-0 text-muted"
                aria-hidden="true"
            />
            {{ t('proposals.summary.note') }}
        </p>
    </section>
</template>
