<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarDays, FileImage, Info, Layers, Lock, Plus, Shield, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CheckboxField from '@/components/forms/CheckboxField.vue';
import SelectField from '@/components/forms/SelectField.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, Option } from '@/types';

/**
 * Page "Contenuti › Simulatore" (M08 F-04, FL-01, CA5; D-494…D-498): admin and manager choose the
 * clubs of a proposal with package, LED format and hospitality, and the mode of the document; the
 * server composes it with DocumentComposer and answers with the slides in order, the pages, the
 * warnings and the slides excluded by a condition. Nothing is saved: the choices live in the address
 * (a GET with only "choice" and "result" reloaded), so the link can be shared.
 */
interface SimulatorClub {
    id: number;
    tier: string | null;
    format: string | null;
    hospitality: boolean;
}

interface SimulatorChoice {
    clubs: SimulatorClub[];
    mode: 'complete' | 'summary';
}

interface SimulatorOptions {
    clubs: { id: number; name: string; competition: string | null }[];
    tiers: { code: string; name: string }[];
    formats: { code: string; name: string }[];
    modes: { value: 'complete' | 'summary'; label: string; description: string }[];
}

interface SimulatorSlide {
    id: number;
    title: string;
    kind: 'static' | 'cover' | 'recap' | 'led' | 'custom';
    note: string | null;
    conditions: string[];
    mandatory: boolean;
    included: boolean;
    pages: number;
    pagesLabel: string | null;
    thumbnailUrl: string | null;
}

interface SimulatorResult {
    documentLabel: string | null;
    includedSlides: number;
    totalSlides: number;
    pages: number;
    warnings: string[];
    sections: { name: string; slides: SimulatorSlide[] }[];
    excluded: { id: number; title: string; section: string; reasons: string[]; thumbnailUrl: string | null }[];
}

const props = defineProps<{
    layout: LayoutData;
    season: { id: number; code: string; closed: boolean } | null;
    options: SimulatorOptions | null;
    choice: SimulatorChoice | null;
    result: SimulatorResult | null;
    url: string;
}>();

const { t } = useTrans();

const clubs = ref<SimulatorClub[]>((props.choice?.clubs ?? []).map((club) => ({ ...club })));
const mode = ref<'complete' | 'summary'>(props.choice?.mode ?? 'complete');
const state = ref<'idle' | 'loading' | 'error'>('idle');
const adding = ref('');

const clubNames = computed(() => new Map((props.options?.clubs ?? []).map((club) => [club.id, club])));

const addOptions = computed<Option[]>(() => (props.options?.clubs ?? [])
    .filter((club) => !clubs.value.some((chosen) => chosen.id === club.id))
    .map((club) => ({ value: String(club.id), label: club.name })));

const tierOptions = computed<Option[]>(() => (props.options?.tiers ?? []).map((tier) => ({ value: tier.code, label: tier.name })));
const formatOptions = computed<Option[]>(() => (props.options?.formats ?? []).map((format) => ({ value: format.code, label: format.name })));

function nameOf(id: number): string {
    return clubNames.value.get(id)?.name ?? String(id);
}

function competitionOf(id: number): string {
    const competition = clubNames.value.get(id)?.competition ?? null;

    return competition !== null ? t('content.simulator.competition', { name: competition }) : t('content.simulator.competition_none');
}

watch(adding, (value) => {
    if (value === '') {
        return;
    }
    clubs.value = [...clubs.value, { id: Number(value), tier: null, format: null, hospitality: false }];
    adding.value = '';
});

function remove(index: number): void {
    clubs.value = clubs.value.filter((_, position) => position !== index);
}

function setTier(club: SimulatorClub, value: string): void {
    club.tier = value === '' ? null : value;
}

function setFormat(club: SimulatorClub, value: string): void {
    club.format = value === '' ? null : value;
}

let timer: ReturnType<typeof setTimeout> | undefined;

watch([clubs, mode], () => {
    clearTimeout(timer);
    timer = setTimeout(compose, 300);
}, { deep: true });

onBeforeUnmount(() => clearTimeout(timer));

function compose(): void {
    timer = undefined;
    state.value = 'loading';
    let failed = false;
    router.get(props.url, {
        club: clubs.value.map((club) => ({
            id: String(club.id),
            pacchetto: club.tier ?? undefined,
            format: club.format ?? undefined,
            hospitality: club.hospitality ? '1' : undefined,
        })),
        modalita: mode.value === 'complete' ? undefined : mode.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['choice', 'result'],
        queryStringArrayFormat: 'indices',
        onError: () => {
            failed = true;
        },
        onNetworkError: () => {
            failed = true;

            return false;
        },
        onFinish: () => {
            state.value = failed ? 'error' : 'idle';
        },
    });
}

const summary = computed(() => {
    const result = props.result;
    if (result === null) {
        return '';
    }
    const values = {
        document: result.documentLabel ?? '',
        included: String(result.includedSlides),
        total: String(result.totalSlides),
        pages: String(result.pages),
    };

    return t(result.pages === 1 ? 'content.simulator.summary_one_page' : 'content.simulator.summary', values);
});

function modeClass(selected: boolean): string {
    return selected ? 'border-2 border-accent bg-accent-soft' : 'border border-line-strong bg-white hover:bg-surface';
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-7xl flex-col gap-5 p-4 md:p-6">
            <EmptyState
                v-if="season === null || options === null"
                class="rounded-[14px] border border-line bg-white"
                :title="t('content.simulator.no_season_title')"
                :message="t('content.simulator.no_season')"
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
                <p class="text-sm leading-relaxed text-muted">
                    {{ t('content.simulator.intro', { season: season.code }) }}
                </p>
                <p
                    v-if="season.closed"
                    class="flex items-start gap-2 rounded-[10px] bg-surface px-4 py-3 text-sm text-ink-soft"
                >
                    <Lock
                        :size="18"
                        :stroke-width="2"
                        class="mt-0.5 shrink-0"
                        aria-hidden="true"
                    />
                    {{ t('content.simulator.closed', { season: season.code }) }}
                </p>

                <EmptyState
                    v-if="options.clubs.length === 0"
                    class="rounded-[14px] border border-line bg-white"
                    :title="t('content.simulator.no_clubs_title')"
                    :message="t('content.simulator.no_clubs', { season: season.code })"
                >
                    <template #icon>
                        <Shield
                            :size="34"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                    </template>
                </EmptyState>

                <div
                    v-else
                    class="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)]"
                >
                    <!-- Proposal to simulate -->
                    <section
                        class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5"
                        :aria-label="t('content.simulator.proposal')"
                    >
                        <h2 class="text-base font-semibold text-ink">
                            {{ t('content.simulator.proposal') }}
                        </h2>

                        <ul
                            v-if="clubs.length > 0"
                            class="flex flex-col gap-3"
                        >
                            <li
                                v-for="(club, index) in clubs"
                                :key="club.id"
                                class="flex flex-col gap-3 rounded-[12px] border border-line-strong p-3.5"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex min-w-0 flex-col">
                                        <span class="font-semibold text-ink">{{ nameOf(club.id) }}</span>
                                        <span class="text-[13px] text-muted">{{ competitionOf(club.id) }}</span>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex size-11 shrink-0 items-center justify-center rounded-[10px] text-muted hover:bg-surface hover:text-accent focus-visible:outline-2 focus-visible:outline-accent"
                                        :aria-label="t('content.simulator.remove_club', { club: nameOf(club.id) })"
                                        @click="remove(index)"
                                    >
                                        <Trash2
                                            :size="18"
                                            :stroke-width="2"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <SelectField
                                        :label="t('content.simulator.tier')"
                                        :options="tierOptions"
                                        :placeholder="t('content.simulator.tier_none')"
                                        :model-value="club.tier ?? ''"
                                        @update:model-value="setTier(club, $event)"
                                    />
                                    <SelectField
                                        :label="t('content.simulator.format')"
                                        :options="formatOptions"
                                        :placeholder="t('content.simulator.format_none')"
                                        :model-value="club.format ?? ''"
                                        @update:model-value="setFormat(club, $event)"
                                    />
                                </div>
                                <CheckboxField
                                    v-model="club.hospitality"
                                    :label="t('content.simulator.hospitality')"
                                />
                            </li>
                        </ul>

                        <SelectField
                            v-if="addOptions.length > 0"
                            v-model="adding"
                            :label="t('content.simulator.add_club')"
                            :options="addOptions"
                            :placeholder="t('content.simulator.add_club_placeholder')"
                        />

                        <div class="flex flex-col gap-2">
                            <p
                                id="simulator-mode"
                                class="text-sm font-medium text-ink"
                            >
                                {{ t('content.simulator.mode') }}
                            </p>
                            <div
                                role="radiogroup"
                                aria-labelledby="simulator-mode"
                                class="grid grid-cols-1 gap-2"
                            >
                                <button
                                    v-for="option in options.modes"
                                    :key="option.value"
                                    type="button"
                                    role="radio"
                                    class="min-h-14 rounded-[10px] px-3.5 py-2 text-left text-sm focus-visible:outline-2 focus-visible:outline-accent"
                                    :class="modeClass(mode === option.value)"
                                    :aria-checked="mode === option.value"
                                    @click="mode = option.value"
                                >
                                    <span class="block font-semibold text-ink">{{ option.label }}</span>
                                    <span class="block text-[13px] text-muted">{{ option.description }}</span>
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- Result -->
                    <section
                        class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5"
                        :aria-label="t('content.simulator.result')"
                        :aria-busy="state === 'loading'"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="flex items-center gap-2 text-base font-semibold text-ink">
                                <Layers
                                    :size="18"
                                    :stroke-width="2"
                                    aria-hidden="true"
                                />
                                {{ t('content.simulator.result') }}
                            </h2>
                            <p
                                class="text-[13px]"
                                :class="state === 'error' ? 'text-accent' : 'text-muted'"
                                aria-live="polite"
                            >
                                {{ state === 'loading' ? t('content.simulator.loading') : state === 'error' ? t('content.simulator.error') : '' }}
                            </p>
                        </div>

                        <EmptyState
                            v-if="result === null"
                            :title="t('content.simulator.empty_title')"
                            :message="t('content.simulator.empty')"
                        >
                            <template #icon>
                                <Plus
                                    :size="34"
                                    :stroke-width="1.6"
                                    aria-hidden="true"
                                />
                            </template>
                        </EmptyState>

                        <template v-else>
                            <p class="text-sm font-medium text-ink">
                                {{ summary }}
                            </p>

                            <div
                                v-if="result.warnings.length > 0"
                                class="flex flex-col gap-2 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
                                role="status"
                            >
                                <p class="flex items-center gap-2 font-semibold">
                                    <Info
                                        :size="18"
                                        :stroke-width="2"
                                        class="shrink-0 text-notice"
                                        aria-hidden="true"
                                    />
                                    {{ t('content.simulator.warnings') }}
                                </p>
                                <ul class="flex list-disc flex-col gap-1 pl-5">
                                    <li
                                        v-for="(warning, index) in result.warnings"
                                        :key="index"
                                    >
                                        {{ warning }}
                                    </li>
                                </ul>
                            </div>

                            <p
                                v-if="result.sections.length === 0"
                                class="text-sm text-muted"
                            >
                                {{ t('content.simulator.no_slides') }}
                            </p>

                            <div
                                v-for="section in result.sections"
                                :key="section.name"
                                class="flex flex-col gap-2.5"
                            >
                                <h3 class="text-[13px] font-semibold tracking-wide text-muted uppercase">
                                    {{ section.name }}
                                </h3>
                                <ol class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                    <li
                                        v-for="slide in section.slides"
                                        :key="slide.id"
                                        class="flex flex-col overflow-hidden rounded-[12px] border"
                                        :class="slide.included ? 'border-line-strong bg-white' : 'border-dashed border-line-strong bg-surface opacity-60'"
                                    >
                                        <span class="relative block aspect-video w-full bg-surface">
                                            <img
                                                v-if="slide.thumbnailUrl"
                                                :src="slide.thumbnailUrl"
                                                alt=""
                                                loading="lazy"
                                                class="absolute inset-0 size-full object-cover"
                                            >
                                            <span
                                                v-else
                                                class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 px-3 text-center text-[13px] text-muted"
                                            >
                                                <FileImage
                                                    :size="28"
                                                    :stroke-width="1.6"
                                                    aria-hidden="true"
                                                />
                                                {{ t('proposals.slides.no_thumbnail') }}
                                            </span>
                                        </span>
                                        <span class="flex flex-col gap-1 p-3 text-sm">
                                            <span class="font-semibold text-ink">{{ slide.title }}</span>
                                            <span
                                                v-if="slide.note"
                                                class="text-[13px] text-ink-soft"
                                            >{{ slide.note }}</span>
                                            <span
                                                v-if="slide.conditions.length === 0"
                                                class="text-[13px] text-muted"
                                            >{{ t('content.simulator.always') }}</span>
                                            <span
                                                v-for="condition in slide.conditions"
                                                :key="condition"
                                                class="text-[13px] text-ink-soft"
                                            >{{ condition }}</span>
                                            <span
                                                v-if="slide.pagesLabel"
                                                class="text-[13px] text-muted"
                                            >{{ slide.pagesLabel }}</span>
                                            <span
                                                v-if="slide.mandatory"
                                                class="inline-flex items-center gap-1 text-[12px] text-muted"
                                            >
                                                <Lock
                                                    :size="13"
                                                    :stroke-width="2"
                                                    aria-hidden="true"
                                                />
                                                {{ t('content.simulator.mandatory') }}
                                            </span>
                                            <span
                                                v-else-if="!slide.included"
                                                class="text-[12px] font-medium text-muted"
                                            >{{ t('content.simulator.not_in_summary') }}</span>
                                        </span>
                                    </li>
                                </ol>
                            </div>

                            <!-- Slides excluded by a condition (D-495) -->
                            <div class="flex flex-col gap-2.5 border-t border-line pt-4">
                                <h3 class="text-sm font-semibold text-ink">
                                    {{ t('content.simulator.excluded', { count: String(result.excluded.length) }) }}
                                </h3>
                                <p class="text-[13px] text-muted">
                                    {{ result.excluded.length > 0 ? t('content.simulator.excluded_hint') : t('content.simulator.excluded_none') }}
                                </p>
                                <ul
                                    v-if="result.excluded.length > 0"
                                    class="flex flex-col divide-y divide-skeleton rounded-[12px] border border-line"
                                >
                                    <li
                                        v-for="slide in result.excluded"
                                        :key="slide.id"
                                        class="flex items-start gap-3 px-3.5 py-2.5"
                                    >
                                        <span class="relative hidden aspect-video w-24 shrink-0 overflow-hidden rounded bg-surface sm:block">
                                            <img
                                                v-if="slide.thumbnailUrl"
                                                :src="slide.thumbnailUrl"
                                                alt=""
                                                loading="lazy"
                                                class="absolute inset-0 size-full object-cover"
                                            >
                                        </span>
                                        <span class="flex min-w-0 flex-col gap-0.5 text-sm">
                                            <span class="font-semibold text-ink">{{ slide.title }}</span>
                                            <span class="text-[13px] text-muted">{{ slide.section }}</span>
                                            <span
                                                v-for="reason in slide.reasons"
                                                :key="reason"
                                                class="text-[13px] text-ink-soft"
                                            >{{ reason }}</span>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </template>
                    </section>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
