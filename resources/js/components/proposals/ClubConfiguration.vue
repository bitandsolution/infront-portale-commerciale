<script setup lang="ts">
import { AlertTriangle, ChevronDown, ChevronUp, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import NumberStepper from '@/components/proposals/NumberStepper.vue';
import { formatLedDuration } from '@/components/proposals/ledDuration';
import { useTrans } from '@/composables/useTrans';
import type { ProposalClubFields, ProposalClubOption, ProposalDraftClub } from '@/types';

/**
 * One club at step 4 of the configurator (M06 §5; D-051…D-055, D-063…D-066, D-071, D-085, D-088,
 * D-147; mockup "Configurazione per club"): a section that opens and closes, with the package
 * ("Libera" or a tier with the partnerships left), one LED format with the minutes left per match,
 * the minutes in steps of 30″ up to the residual, the hospitality seats up to the maximum per club and
 * up to the residual. Only available and sold-out options are shown (sold out: visible, not
 * selectable); territory and visibility type stay hidden (D-085).
 *
 * The page only limits the steppers to what the server sent; matches, prices and errors are always
 * the server's (DraftEvaluator), and the server checks everything again at the save.
 */
const props = defineProps<{
    fields: ProposalClubFields;
    option: ProposalClubOption | null;
    server: ProposalDraftClub | undefined;
    errors: Record<string, string>;
    open: boolean;
    ledStepSeconds: number;
    hospitalityMaxSeats: number;
}>();

const emit = defineEmits<{
    update: [fields: ProposalClubFields];
    toggle: [];
    remove: [];
}>();

const { t } = useTrans();

/** At most a whole match per format (D-345): 90′. */
const MAX_LED_SECONDS = 5400;

const name = computed(() => props.option?.name ?? props.server?.name ?? '');
const initials = computed(() => props.option?.initials ?? props.server?.initials ?? '');
const sectionId = computed(() => `club-config-${props.fields.club_id}`);

function update(changes: Partial<ProposalClubFields>): void {
    emit('update', { ...props.fields, ...changes });
}

// Package (D-052, D-071): choosing the selected one again removes the choice.
function chooseTier(tierId: number): void {
    update({ partnership_tier_id: props.fields.partnership_tier_id === tierId ? null : tierId });
}

// LED format (one per club, D-088); a new format starts from one step, never beyond the residual.
const chosenFormat = computed(() => props.option?.formats.find((format) => format.id === props.fields.led_format_id) ?? null);

function chooseFormat(formatId: number | null): void {
    if (formatId === null) {
        update({ led_format_id: null, led_seconds: 0 });

        return;
    }
    const seconds = props.fields.led_format_id === formatId && props.fields.led_seconds > 0 ? props.fields.led_seconds : props.ledStepSeconds;
    update({ led_format_id: formatId, led_seconds: seconds });
}

const ledMax = computed(() => Math.min(chosenFormat.value?.remaining ?? 0, MAX_LED_SECONDS));

// Hospitality (D-055, D-147): at most the maximum per club in total and the residual of the variant.
function seatsOf(variantId: number): number {
    return props.fields.seats.find((item) => item.variant_id === variantId)?.seats ?? 0;
}

const totalSeats = computed(() => props.fields.seats.reduce((sum, item) => sum + item.seats, 0));

function seatsMax(variantId: number, remaining: number | null): number {
    const others = totalSeats.value - seatsOf(variantId);

    return Math.max(0, Math.min(remaining ?? 0, props.hospitalityMaxSeats - others));
}

function setSeats(variantId: number, seats: number): void {
    const rest = props.fields.seats.filter((item) => item.variant_id !== variantId);
    update({ seats: seats > 0 ? [...rest, { variant_id: variantId, seats }] : rest });
}

// Errors of the club as a whole (not of a single field) go at the top of the section.
const CLUB_LEVEL = ['club', 'elements', 'matches', 'seats'];
const clubErrors = computed(() => CLUB_LEVEL.map((field) => props.errors[field]).filter((message): message is string => message !== undefined));
const errorCount = computed(() => Object.keys(props.errors).length);

// Seats of a variant no longer shown (not managed or without a price any more, M06 §9): the error
// stays at the top with the way to take the seats away.
const orphanSeats = computed(() => (props.option === null ? [] : props.fields.seats
    .filter((item) => !props.option?.variants.some((variant) => variant.id === item.variant_id))
    .map((item) => ({ variantId: item.variant_id, message: props.errors[`seats.${item.variant_id}`] ?? t('proposals.configuration.seats_orphan') }))));

// Collapsed: the configuration in one line (names from the options, numbers from the server).
const summary = computed(() => {
    const parts: string[] = [];
    const tier = props.option?.tiers.find((item) => item.id === props.fields.partnership_tier_id);
    if (tier) {
        parts.push(tier.name);
    }
    if (chosenFormat.value) {
        parts.push(chosenFormat.value.name, t('proposals.configuration.per_match', { duration: formatLedDuration(props.fields.led_seconds) }));
    }
    if (totalSeats.value > 0) {
        parts.push(t('proposals.configuration.seats_summary', { count: String(totalSeats.value) }));
    }
    if (props.server?.matches !== null && props.server?.matches !== undefined && chosenFormat.value) {
        parts.push(t('proposals.configuration.matches_short', { count: String(props.server.matches) }));
    }
    if (props.server?.spots !== null && props.server?.spots !== undefined) {
        parts.push(t('proposals.configuration.spots', { count: String(props.server.spots) }));
    }

    return parts.length > 0 ? parts.join(' · ') : t('proposals.configuration.nothing_yet');
});

const subtitle = computed(() => [props.server?.competition ?? props.option?.competition, props.server?.matchesLabel]
    .filter((part): part is string => typeof part === 'string' && part !== '')
    .join(' · '));

const optionClass = (selected: boolean, disabled: boolean): string => {
    if (disabled) {
        return 'cursor-not-allowed border border-dashed border-line-strong bg-surface text-muted';
    }

    return selected ? 'border-2 border-accent bg-accent-soft' : 'border border-line-strong bg-white hover:bg-surface';
};
</script>

<template>
    <section
        class="overflow-hidden rounded-[14px] border bg-white"
        :class="errorCount > 0 ? 'border-accent/50' : 'border-line'"
        :aria-label="name"
    >
        <button
            type="button"
            class="flex w-full items-center gap-3.5 px-4 py-3 text-left focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent md:px-5"
            :aria-expanded="open"
            :aria-controls="sectionId"
            @click="emit('toggle')"
        >
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-surface text-[12px] font-bold text-ink-soft"
                aria-hidden="true"
            >{{ initials }}</span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-base font-semibold">{{ name }}</span>
                <span
                    class="block text-[13px] text-muted"
                    :class="open ? '' : 'truncate'"
                >{{ open ? subtitle : summary }}</span>
            </span>
            <span
                v-if="errorCount > 0"
                class="hidden shrink-0 rounded-full bg-accent-soft px-2.5 py-0.5 text-[13px] font-medium text-accent sm:inline"
            >{{ t('proposals.configuration.errors_count', { count: String(errorCount) }) }}</span>
            <span class="shrink-0 text-right font-mono text-base font-medium md:text-[17px]">
                {{ server?.price !== null && server?.price !== undefined ? `€ ${server.price}` : '—' }}
            </span>
            <component
                :is="open ? ChevronUp : ChevronDown"
                :size="20"
                :stroke-width="2"
                class="shrink-0 text-muted"
                aria-hidden="true"
            />
            <span class="sr-only">{{ open ? t('proposals.configuration.collapse') : t('proposals.configuration.expand') }}</span>
        </button>

        <div
            v-if="open"
            :id="sectionId"
            class="flex flex-col gap-5 border-t border-line px-4 py-4 md:px-5"
        >
            <ul
                v-if="clubErrors.length > 0 || orphanSeats.length > 0"
                class="flex flex-col gap-1.5 rounded-[10px] bg-accent-soft px-3.5 py-3 text-sm text-ink"
                role="alert"
            >
                <li
                    v-for="message in clubErrors"
                    :key="message"
                    class="flex items-start gap-2"
                >
                    <AlertTriangle
                        :size="16"
                        :stroke-width="2"
                        class="mt-0.5 shrink-0 text-accent"
                        aria-hidden="true"
                    />
                    <span>{{ message }}</span>
                </li>
                <li
                    v-for="item in orphanSeats"
                    :key="`orphan-${item.variantId}`"
                    class="flex flex-wrap items-center gap-2"
                >
                    <AlertTriangle
                        :size="16"
                        :stroke-width="2"
                        class="shrink-0 text-accent"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1">{{ item.message }}</span>
                    <button
                        type="button"
                        class="min-h-11 rounded-[10px] border border-line-strong bg-white px-3 text-sm font-medium hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                        @click="setSeats(item.variantId, 0)"
                    >
                        {{ t('proposals.configuration.seats_clear') }}
                    </button>
                </li>
            </ul>

            <template v-if="option">
                <!-- Package (D-052, D-071) -->
                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-2 text-[12px] font-semibold tracking-[0.6px] text-muted uppercase">
                        {{ t('proposals.configuration.package') }}
                    </legend>
                    <div class="grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-2.5">
                        <button
                            v-for="tier in option.tiers"
                            :key="tier.id"
                            type="button"
                            class="min-h-16 rounded-[10px] px-3 py-2.5 text-left text-sm focus-visible:outline-2 focus-visible:outline-accent"
                            :class="optionClass(fields.partnership_tier_id === tier.id, tier.status !== 'available')"
                            :aria-pressed="fields.partnership_tier_id === tier.id"
                            :disabled="tier.status !== 'available' && fields.partnership_tier_id !== tier.id"
                            @click="chooseTier(tier.id)"
                        >
                            <span class="block font-semibold">{{ tier.name }}</span>
                            <span
                                class="block text-[12px]"
                                :class="tier.status === 'available' && !tier.isFree ? 'text-success' : 'text-muted'"
                            >
                                <template v-if="tier.isFree">{{ t('proposals.configuration.free_package') }}</template>
                                <template v-else-if="tier.status !== 'available'">{{ tier.statusLabel }}</template>
                                <template v-else>{{ t('proposals.configuration.tier_left', { count: String(tier.remaining ?? 0), price: tier.price ?? '' }) }}</template>
                            </span>
                        </button>
                    </div>
                    <p
                        v-if="errors.tier"
                        class="text-[13px] leading-snug text-accent"
                    >
                        {{ errors.tier }}
                    </p>
                </fieldset>

                <!-- LED (D-051, D-065, D-066, D-088) -->
                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-2 text-[12px] font-semibold tracking-[0.6px] text-muted uppercase">
                        {{ t('proposals.configuration.led') }}
                        <span class="font-normal tracking-normal normal-case">· {{ t('proposals.configuration.led_optional') }}</span>
                    </legend>
                    <div class="grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-2.5">
                        <button
                            type="button"
                            class="min-h-14 rounded-[10px] px-3 py-2 text-left text-sm font-semibold focus-visible:outline-2 focus-visible:outline-accent"
                            :class="optionClass(fields.led_format_id === null, false)"
                            :aria-pressed="fields.led_format_id === null"
                            @click="chooseFormat(null)"
                        >
                            {{ t('proposals.configuration.no_led') }}
                        </button>
                        <button
                            v-for="format in option.formats"
                            :key="format.id"
                            type="button"
                            class="min-h-14 rounded-[10px] px-3 py-2 text-left text-sm focus-visible:outline-2 focus-visible:outline-accent"
                            :class="optionClass(fields.led_format_id === format.id, format.status !== 'available')"
                            :aria-pressed="fields.led_format_id === format.id"
                            :disabled="format.status !== 'available' && fields.led_format_id !== format.id"
                            @click="chooseFormat(format.id)"
                        >
                            <span class="block font-semibold">{{ format.name }}</span>
                            <span
                                class="block text-[12px]"
                                :class="format.status === 'available' ? 'text-success' : 'text-muted'"
                            >
                                <template v-if="format.status !== 'available'">{{ format.statusLabel }}</template>
                                <template v-else>{{ t('proposals.configuration.led_left', { duration: format.remainingLabel ?? '' }) }}</template>
                            </span>
                        </button>
                    </div>
                    <p
                        v-if="errors.led_format"
                        class="text-[13px] leading-snug text-accent"
                    >
                        {{ errors.led_format }}
                    </p>

                    <div
                        v-if="fields.led_format_id !== null"
                        class="mt-2 flex flex-col gap-1.5"
                    >
                        <p class="text-sm font-medium text-ink">
                            {{ t('proposals.configuration.minutes') }}
                        </p>
                        <NumberStepper
                            :model-value="fields.led_seconds"
                            :min="ledStepSeconds"
                            :max="ledMax"
                            :step="ledStepSeconds"
                            :text="formatLedDuration(fields.led_seconds)"
                            :label="t('proposals.configuration.minutes_of', { club: name })"
                            :decrease-label="t('proposals.configuration.minutes_less')"
                            :increase-label="t('proposals.configuration.minutes_more')"
                            :described-by="`${sectionId}-minutes-help`"
                            :disabled="chosenFormat === null"
                            @update:model-value="(seconds: number) => update({ led_seconds: seconds })"
                        />
                        <p
                            :id="`${sectionId}-minutes-help`"
                            class="text-[13px] text-muted"
                        >
                            {{ t('proposals.configuration.minutes_help', {
                                step: formatLedDuration(ledStepSeconds),
                                remaining: chosenFormat?.remainingLabel ?? '—',
                            }) }}
                        </p>
                        <p
                            v-if="errors.led_seconds"
                            class="text-[13px] leading-snug text-accent"
                        >
                            {{ errors.led_seconds }}
                        </p>
                    </div>
                </fieldset>

                <!-- Hospitality (D-055, D-147) -->
                <div
                    v-if="option.variants.length > 0"
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                >
                    <div
                        v-for="variant in option.variants"
                        :key="variant.id"
                        class="flex flex-col gap-1.5"
                    >
                        <p class="text-[12px] font-semibold tracking-[0.6px] text-muted uppercase">
                            {{ t('proposals.configuration.hospitality', { variant: variant.name }) }}
                            <span class="font-normal tracking-normal normal-case">
                                · {{ variant.status === 'available'
                                    ? t('proposals.configuration.seats_left', { count: String(variant.remaining ?? 0) })
                                    : variant.statusLabel }}
                            </span>
                        </p>
                        <NumberStepper
                            :model-value="seatsOf(variant.id)"
                            :min="0"
                            :max="variant.status === 'available' ? seatsMax(variant.id, variant.remaining) : 0"
                            :step="1"
                            :text="String(seatsOf(variant.id))"
                            :label="t('proposals.configuration.seats_of', { variant: variant.name, club: name })"
                            :decrease-label="t('proposals.configuration.seats_less', { variant: variant.name })"
                            :increase-label="t('proposals.configuration.seats_more', { variant: variant.name })"
                            @update:model-value="(seats: number) => setSeats(variant.id, seats)"
                        />
                        <p
                            v-if="errors[`seats.${variant.id}`]"
                            class="text-[13px] leading-snug text-accent"
                        >
                            {{ errors[`seats.${variant.id}`] }}
                        </p>
                    </div>
                    <p class="text-[13px] text-muted sm:col-span-2">
                        {{ t('proposals.configuration.seats_help', { max: String(hospitalityMaxSeats) }) }}
                    </p>
                </div>
            </template>

            <div class="flex justify-end border-t border-line pt-3">
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center gap-2 rounded-[10px] px-3 text-sm font-medium text-accent hover:bg-accent-soft focus-visible:outline-2 focus-visible:outline-accent"
                    @click="emit('remove')"
                >
                    <Trash2
                        :size="18"
                        :stroke-width="2"
                        aria-hidden="true"
                    />
                    {{ t('proposals.configuration.remove_club', { club: name }) }}
                </button>
            </div>
        </div>
    </section>
</template>
