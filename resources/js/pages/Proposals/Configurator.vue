<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Building2, Check, CloudOff, Copy, CopyCheck, Plus, RefreshCw, Search, Trash2, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import SelectField from '@/components/forms/SelectField.vue';
import TextField from '@/components/forms/TextField.vue';
import NoticeDialog from '@/components/NoticeDialog.vue';
import ClubConfiguration from '@/components/proposals/ClubConfiguration.vue';
import ClubPicker from '@/components/proposals/ClubPicker.vue';
import CopyClubsDialog from '@/components/proposals/CopyClubsDialog.vue';
import DraftSummary from '@/components/proposals/DraftSummary.vue';
import { formatLedDuration } from '@/components/proposals/ledDuration';
import NewClientDialog from '@/components/proposals/NewClientDialog.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    LayoutData,
    ProposalClientOption,
    ProposalClubFields,
    ProposalClubOption,
    ProposalCopied,
    ProposalDraft,
    ProposalDuplicatedNotice,
    ProposalRepricing,
    ProposalSeasonOption,
    SectorOption,
} from '@/types';

/**
 * Configurator of a proposal (M06 §4, §5; D-165, D-372…D-375; mockups "Periodo e club" and
 * "Configurazione per club", "Riepilogo"). F4-02 built steps 1 (Cliente) and 2 (Stagione e periodo),
 * F4-03 steps 3 (Club) and 4 (Configurazione per club), F4-04 step 5 (Riepilogo, DraftSummary): it
 * opens only when every club has at least one element (D-373).
 *
 * - "Nuova proposta" has no draft: the draft is born when the agent chooses (or creates) the client.
 * - Automatic save (D-372): 2 seconds after the last change and at every change of step, with the lock
 *   version read from the server (D-216). The server checks client, season, period and clubs (M06 §8)
 *   and answers with the errors under the fields; in the same request it evaluates every club and
 *   calculates matches, club prices and total (CLAUDE.md §4): the page never calculates them.
 * - Offline (D-375): the changes stay in the page and are sent again when the network comes back;
 *   the banner says so and the total is marked "non aggiornato". Nothing is stored in the browser.
 * - The step is in the address (?passo=2): after an expired session the login brings the agent back
 *   to the same draft and step (D-375).
 * - Total always visible: fixed bar at the bottom below 1280 px, column on the right from 1280 px.
 * - Reopened draft (M06 RB-12, M03-CA6): the prices recalculated since the last save are shown in a
 *   banner ("da € X a € Y"); the prop comes only with the page, so the banner stays while the agent
 *   works, until it is closed.
 * - F4-05: "Copia su altri club" at step 4 (CopyClubsDialog): the pending changes are saved first,
 *   then the server copies and saves; the page is loaded again with the new clubs and the outcome
 *   club by club (prop "copied"). A draft just duplicated shows its origin, the prices recalculated
 *   and the clubs to correct (prop "duplicated").
 */
const props = defineProps<{
    layout: LayoutData;
    step: number;
    lastStep: number;
    draft: ProposalDraft | null;
    reopened: ProposalRepricing | null;
    copied: ProposalCopied | null;
    duplicated: ProposalDuplicatedNotice | null;
    clients: ProposalClientOption[];
    sectors: SectorOption[];
    seasons: ProposalSeasonOption[];
    defaultSeasonId: string;
    clubOptions: ProposalClubOption[];
    ledStepSeconds: number;
    hospitalityMaxSeats: number;
    createUrl: string;
    newClientUrl: string;
    mineUrl: string;
}>();

const { t } = useTrans();
const page = usePage();

const STEPS = ['client', 'period', 'clubs', 'configuration', 'summary'] as const;
const SAVE_DELAY_MS = 2000;
const MAX_SUGGESTIONS = 8;

const currentStep = ref(props.draft === null ? 1 : props.step);
const errors = computed(() => (page.props.errors ?? {}) as Record<string, string | undefined>);

// Fields of the draft as the agent edits them; the server's copy only gives the lock version and the
// time of the last save, so a slow answer never overwrites what the agent typed meanwhile.
const fields = reactive({
    client_id: props.draft?.clientId ?? '',
    season_id: props.draft?.seasonId ?? '',
    starts_on: props.draft?.startsOn ?? '',
    ends_on: props.draft?.endsOn ?? '',
    clubs: (props.draft?.clubs ?? []).map((club): ProposalClubFields => ({
        club_id: club.clubId,
        partnership_tier_id: club.tierId,
        led_format_id: club.ledFormatId,
        led_seconds: club.ledSeconds,
        seats: club.seats.map((item) => ({ variant_id: item.variantId, seats: item.seats })),
    })),
});
const lockVersion = ref(props.draft?.lockVersion ?? 0);
const savedAt = ref(props.draft?.savedAt ?? '');

type SaveState = 'saved' | 'pending' | 'saving' | 'error';

const saveState = ref<SaveState>('saved');
const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
let dirty = false;
let saving = false;
let queued = false;
let leaving = false;
let timer: ReturnType<typeof setTimeout> | undefined;

function markDirty(): void {
    if (props.draft === null) {
        return;
    }
    dirty = true;
    saveState.value = 'pending';
    clearTimeout(timer);
    timer = setTimeout(() => save(), SAVE_DELAY_MS);
}

/**
 * Sends the pending changes now. "after" runs once the changes are saved (or when nothing is pending).
 */
function save(after?: () => void): void {
    clearTimeout(timer);
    if (props.draft === null) {
        return;
    }
    if (!dirty) {
        after?.();

        return;
    }
    if (!online.value) {
        saveState.value = 'pending';

        return;
    }
    if (saving) {
        queued = true;

        return;
    }

    saving = true;
    dirty = false;
    saveState.value = 'saving';
    let failed = false;

    router.put(props.draft.saveUrl, { ...fields, lock_version: lockVersion.value }, {
        preserveState: true,
        preserveScroll: true,
        only: ['draft', 'clubOptions'],
        onSuccess: (response) => {
            const draft = response.props.draft as ProposalDraft | null;
            if (draft) {
                lockVersion.value = draft.lockVersion;
                savedAt.value = draft.savedAt;
            }
        },
        onError: () => {
            failed = true;
            saveState.value = 'error';
        },
        onNetworkError: () => {
            failed = true;
            dirty = true;
            online.value = false;
            saveState.value = 'pending';

            return false;
        },
        onFinish: () => {
            saving = false;
            if (queued || (dirty && online.value)) {
                queued = false;
                save(after);

                return;
            }
            if (!failed) {
                saveState.value = dirty ? 'pending' : 'saved';
                after?.();
            }
        },
    });
}

watch(fields, markDirty, { deep: true });

// A new season brings back the defaults of its period (D-375).
watch(
    () => fields.season_id,
    (seasonId) => {
        const season = props.seasons.find((option) => option.value === seasonId);
        if (season) {
            fields.starts_on = season.defaultStartsOn;
            fields.ends_on = season.defaultEndsOn;
        }
    },
);

function onOnline(): void {
    online.value = true;
    if (dirty) {
        save();
    }
}

function onOffline(): void {
    online.value = false;
}

function onBeforeUnload(event: BeforeUnloadEvent): void {
    if (dirty || saving) {
        event.preventDefault();
    }
}

let removeBeforeListener: VoidFunction | null = null;

onMounted(() => {
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);
    window.addEventListener('beforeunload', onBeforeUnload);
    // Leaving the page with changes still to save: save them first, then go on.
    removeBeforeListener = router.on('before', (event) => {
        const visit = event.detail.visit;
        if (leaving || visit.method !== 'get' || !dirty || !online.value || props.draft === null) {
            return;
        }
        event.preventDefault();
        leaving = true;
        save(() => router.visit(visit.url));
        setTimeout(() => (leaving = false), 0);
    });
});

onBeforeUnmount(() => {
    clearTimeout(timer);
    window.removeEventListener('online', onOnline);
    window.removeEventListener('offline', onOffline);
    window.removeEventListener('beforeunload', onBeforeUnload);
    removeBeforeListener?.();
});

const saveText = computed(() => {
    if (props.draft === null) {
        return t('proposals.save.not_saved_yet');
    }

    return {
        saved: t('proposals.save.saved_at', { time: savedAt.value }),
        pending: t('proposals.save.pending'),
        saving: t('proposals.save.saving'),
        error: t('proposals.save.error'),
    }[saveState.value];
});

// Steps (M06 §4).
function stepUrl(step: number): string {
    const url = new URL(window.location.href);
    url.searchParams.set('passo', String(step));

    return url.pathname + url.search;
}

function canOpen(step: number): boolean {
    if (step > props.lastStep || (step > 1 && (props.draft === null || fields.client_id === ''))) {
        return false;
    }
    if (step >= 3 && fields.season_id === '') {
        return false;
    }

    if (step >= 4 && fields.clubs.length === 0) {
        return false;
    }

    // D-373: the summary needs at least one element in every club (the server checks it again).
    return step < 5 || fields.clubs.every(clubHasElements);
}

/** RB-08, D-373: a package other than "Libera", the LED or at least one hospitality seat. */
function clubHasElements(club: ProposalClubFields): boolean {
    if (club.led_format_id !== null || club.seats.some((item) => item.seats > 0)) {
        return true;
    }
    if (club.partnership_tier_id === null) {
        return false;
    }
    const tier = optionsById.value.get(club.club_id)?.tiers.find((option) => option.id === club.partnership_tier_id);

    return tier === undefined || !tier.isFree;
}

function goTo(step: number): void {
    if (!canOpen(step) || step === currentStep.value) {
        return;
    }
    currentStep.value = step;
    router.replace({ url: stepUrl(step), preserveState: true, preserveScroll: true });
    save();
}

const nextDisabled = computed(() => !canOpen(currentStep.value + 1));
const nextHint = computed(() => {
    if (!nextDisabled.value) {
        return '';
    }

    if (currentStep.value === 1) {
        return t('proposals.nav.choose_client_first');
    }
    if (currentStep.value === 2) {
        return t('proposals.nav.choose_season_first');
    }
    if (currentStep.value === 3) {
        return t('proposals.nav.choose_clubs_first');
    }

    return t('proposals.nav.summary_blocked');
});

// Step 1: client (M05 F-02, FL-01, FL-02).
const search = ref('');
const changingClient = ref(fields.client_id === '');
const creatingDraft = ref(false);
const newClientOpen = ref(false);

function normalized(value: string): string {
    return value.toLocaleLowerCase('it').normalize('NFD').replace(/[̀-ͯ]/g, '');
}

const matches = computed(() => {
    const term = normalized(search.value.trim());

    return term === ''
        ? props.clients
        : props.clients.filter((client) => normalized(client.label).includes(term) || (client.vatNumber ?? '').includes(term));
});
const suggestions = computed(() => matches.value.slice(0, MAX_SUGGESTIONS));

const chosenClient = computed(() => {
    const option = props.clients.find((client) => client.value === fields.client_id);
    if (option) {
        return { name: option.label, vatNumber: option.vatNumber, sector: option.sector, selectable: true };
    }

    return props.draft?.client && props.draft.clientId === fields.client_id ? props.draft.client : null;
});

function chooseClient(client: ProposalClientOption): void {
    if (props.draft === null) {
        creatingDraft.value = true;
        // A new draft is a new page: Inertia 3 keeps the state of the component after a POST by
        // default, which would leave the fields of "Nuova proposta" (no client, no season) on the
        // draft and send them with the first automatic save. preserveState: false remounts the page
        // with the fields of the draft (bug found in the test of F4-03).
        router.post(props.createUrl, { client_id: client.value }, {
            preserveState: false,
            onFinish: () => (creatingDraft.value = false),
        });

        return;
    }
    fields.client_id = client.value;
    changingClient.value = false;
    search.value = '';
}

function clientCreated(): void {
    // The page is reloaded by the server with the new client chosen (and the new lock version).
    newClientOpen.value = false;
}

// Step 2: season and period (M06 §4, §8; D-375).
const chosenSeason = computed(() => props.seasons.find((season) => season.value === fields.season_id) ?? null);

// Steps 3 and 4: clubs and their configuration (M06 §4, §5). Options, matches, prices and errors
// come from the server; the page keeps the choices of the agent.
const optionsById = computed(() => new Map(props.clubOptions.map((option) => [option.id, option])));
const serverClubs = computed(() => new Map((props.draft?.clubs ?? []).map((club) => [club.clubId, club])));
const selectedClubIds = computed(() => fields.clubs.map((club) => club.club_id));
const openClubs = ref<number[]>(fields.clubs.length > 0 ? [fields.clubs[0]?.club_id ?? 0] : []);

function toggleClub(clubId: number): void {
    const index = fields.clubs.findIndex((club) => club.club_id === clubId);
    if (index >= 0) {
        fields.clubs.splice(index, 1);
        openClubs.value = openClubs.value.filter((id) => id !== clubId);

        return;
    }
    fields.clubs.push({ club_id: clubId, partnership_tier_id: null, led_format_id: null, led_seconds: 0, seats: [] });
    openClubs.value = [...openClubs.value, clubId];
}

function updateClub(index: number, club: ProposalClubFields): void {
    fields.clubs.splice(index, 1, club);
}

function toggleOpen(clubId: number): void {
    openClubs.value = openClubs.value.includes(clubId)
        ? openClubs.value.filter((id) => id !== clubId)
        : [...openClubs.value, clubId];
}

/** Errors of a club: those of the refused save first (the field just changed), then the server's. */
function clubErrors(clubId: number): Record<string, string> {
    const result: Record<string, string> = { ...(serverClubs.value.get(clubId)?.errors ?? {}) };
    const prefix = `clubs.${clubId}.`;
    for (const [key, message] of Object.entries(errors.value)) {
        if (key.startsWith(prefix) && message !== undefined) {
            result[key.slice(prefix.length)] = message;
        }
    }

    return result;
}

// Draft changed elsewhere (lock version): reload instead of overwriting the other change.
const conflictMessage = computed(() => errors.value.conflict);

function reloadDraft(): void {
    leaving = true;
    dirty = false;
    window.location.reload();
}

// Deletion of the draft (M07 RB-08), with confirmation (ux-architettura §4.5).
const confirmingDelete = ref(false);
const deleting = ref(false);

function deleteDraft(): void {
    if (props.draft === null) {
        return;
    }
    leaving = true;
    dirty = false;
    clearTimeout(timer);
    deleting.value = true;
    router.delete(props.draft.deleteUrl, {
        onFinish: () => {
            deleting.value = false;
            confirmingDelete.value = false;
            leaving = false;
        },
    });
}

// "Copia su altri club" (M06 §5, M06-CA7; F4-05).
const copyOpen = ref(false);
const copying = ref(false);

/** One line per club for the dialog: names from the options of the server. */
function clubSummary(club: ProposalClubFields): string {
    const option = optionsById.value.get(club.club_id);
    const parts: string[] = [];
    const tier = option?.tiers.find((item) => item.id === club.partnership_tier_id);
    const format = option?.formats.find((item) => item.id === club.led_format_id);
    const seats = club.seats.reduce((sum, item) => sum + item.seats, 0);
    if (tier) {
        parts.push(tier.name);
    }
    if (format) {
        parts.push(format.name, t('proposals.configuration.per_match', { duration: formatLedDuration(club.led_seconds) }));
    }
    if (seats > 0) {
        parts.push(t('proposals.configuration.seats_summary', { count: String(seats) }));
    }

    return parts.length > 0 ? parts.join(' · ') : t('proposals.copy.nothing_configured');
}

const copyClubs = computed(() => fields.clubs.map((club) => ({
    id: club.club_id,
    name: optionsById.value.get(club.club_id)?.name ?? serverClubs.value.get(club.club_id)?.name ?? '',
    summary: clubSummary(club),
})));

function copyConfiguration(sourceId: number, targetIds: number[]): void {
    if (props.draft === null || !online.value) {
        return;
    }
    const url = props.draft.copyUrl;
    copying.value = true;
    // The copy starts from what is saved: the pending changes go first. The answer brings new clubs,
    // so the page is mounted again with them (preserveState: false).
    save(() => router.post(url, { source_club_id: sourceId, target_club_ids: targetIds, lock_version: lockVersion.value }, {
        preserveState: false,
        preserveScroll: true,
        onFinish: () => {
            copying.value = false;
            copyOpen.value = false;
        },
    }));
    if (saveState.value === 'error') {
        copying.value = false;
    }
}

const showCopied = ref(props.copied !== null);
const showDuplicated = ref(props.duplicated !== null);

// Step 5 shows the total in its table: no separate total column or bar there.
const summaryStep = computed(() => currentStep.value === 5);

// Prices recalculated since the last save (RB-12), until the agent closes the banner.
const showReopened = ref(props.reopened !== null);

const totalStale = computed(() => !online.value && saveState.value === 'pending');
const listTotal = computed(() => props.draft?.listTotal ?? '0,00');
</script>

<template>
    <AppLayout :layout="layout">
        <div class="flex min-h-full flex-col">
            <div
                class="mx-auto grid w-full max-w-6xl flex-1 grid-cols-1 gap-5 p-4 md:p-6 xl:gap-6"
                :class="{ 'xl:grid-cols-[minmax(0,1fr)_300px]': !summaryStep }"
            >
                <div class="flex min-w-0 flex-col gap-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <Link
                            :href="mineUrl"
                            class="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-ink-soft hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
                        >
                            <ArrowLeft
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('proposals.back_mine') }}
                        </Link>
                        <p
                            class="text-sm text-muted"
                            :class="{ 'xl:hidden': !summaryStep }"
                            aria-live="polite"
                        >
                            {{ saveText }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-xl font-semibold">
                            {{ draft ? draft.title : t('proposals.new_title') }}
                        </h2>
                        <StatusPill
                            v-if="draft"
                            :label="t('proposals.draft_badge')"
                            tone="muted"
                        />
                    </div>

                    <div
                        v-if="!online"
                        class="flex items-start gap-2.5 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
                        role="status"
                    >
                        <CloudOff
                            :size="18"
                            :stroke-width="2"
                            class="mt-0.5 shrink-0"
                            aria-hidden="true"
                        />
                        <p>{{ t('proposals.offline') }}</p>
                    </div>

                    <div
                        v-if="reopened && showReopened"
                        class="flex items-start gap-2.5 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
                        role="status"
                    >
                        <RefreshCw
                            :size="18"
                            :stroke-width="2"
                            class="mt-0.5 shrink-0 text-notice"
                            aria-hidden="true"
                        />
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <p class="font-semibold">
                                {{ t('proposals.reopened.title') }}
                            </p>
                            <ul class="flex flex-col gap-0.5">
                                <li
                                    v-for="club in reopened.clubs"
                                    :key="club.name"
                                >
                                    {{ club.text }}
                                </li>
                                <li class="font-medium">
                                    {{ reopened.total }}
                                </li>
                            </ul>
                            <p class="text-[13px] text-muted">
                                {{ t('proposals.reopened.help') }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="-m-2 flex size-11 shrink-0 items-center justify-center rounded-[10px] text-ink-soft hover:bg-white/60 focus-visible:outline-2 focus-visible:outline-accent"
                            :aria-label="t('proposals.reopened.close')"
                            @click="showReopened = false"
                        >
                            <X
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                        </button>
                    </div>

                    <div
                        v-if="duplicated && showDuplicated"
                        class="flex items-start gap-2.5 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
                        role="status"
                    >
                        <Copy
                            :size="18"
                            :stroke-width="2"
                            class="mt-0.5 shrink-0 text-notice"
                            aria-hidden="true"
                        />
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <p class="font-semibold">
                                {{ duplicated.title }}
                            </p>
                            <p class="text-[13px] text-muted">
                                {{ t('proposals.duplicated.help') }}
                            </p>
                            <ul
                                v-if="duplicated.prices"
                                class="flex flex-col gap-0.5"
                            >
                                <li
                                    v-for="club in duplicated.prices.clubs"
                                    :key="club.name"
                                >
                                    {{ club.text }}
                                </li>
                                <li class="font-medium">
                                    {{ duplicated.prices.total }}
                                </li>
                            </ul>
                            <p
                                v-if="duplicated.invalidClubs"
                                class="font-medium text-accent"
                            >
                                {{ duplicated.invalidClubs }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="-m-2 flex size-11 shrink-0 items-center justify-center rounded-[10px] text-ink-soft hover:bg-white/60 focus-visible:outline-2 focus-visible:outline-accent"
                            :aria-label="t('proposals.duplicated.close')"
                            @click="showDuplicated = false"
                        >
                            <X
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                        </button>
                    </div>

                    <div
                        v-if="copied && showCopied"
                        class="flex items-start gap-2.5 rounded-[10px] px-3.5 py-3 text-sm text-ink"
                        :class="copied.skipped.length > 0 ? 'bg-notice-soft' : 'bg-success-soft'"
                        role="status"
                    >
                        <CopyCheck
                            :size="18"
                            :stroke-width="2"
                            class="mt-0.5 shrink-0"
                            :class="copied.skipped.length > 0 ? 'text-notice' : 'text-success'"
                            aria-hidden="true"
                        />
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <p class="font-semibold">
                                {{ copied.title }}
                            </p>
                            <p v-if="copied.copied">
                                {{ copied.copied }}
                            </p>
                            <template v-if="copied.skipped.length > 0">
                                <p class="font-medium">
                                    {{ t('proposals.copy.skipped_title') }}
                                </p>
                                <ul class="flex flex-col gap-1">
                                    <li
                                        v-for="club in copied.skipped"
                                        :key="club.club"
                                    >
                                        <span class="font-medium">{{ club.club }}:</span>
                                        {{ club.items.join(' ') }}
                                    </li>
                                </ul>
                                <p class="text-[13px] text-muted">
                                    {{ t('proposals.copy.skipped_help') }}
                                </p>
                            </template>
                        </div>
                        <button
                            type="button"
                            class="-m-2 flex size-11 shrink-0 items-center justify-center rounded-[10px] text-ink-soft hover:bg-white/60 focus-visible:outline-2 focus-visible:outline-accent"
                            :aria-label="t('proposals.copy.close')"
                            @click="showCopied = false"
                        >
                            <X
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                        </button>
                    </div>

                    <!-- Steps (M06 §4) -->
                    <nav :aria-label="t('proposals.steps.label')">
                        <p class="mb-2 text-sm text-muted md:hidden">
                            {{ t('proposals.steps.step', { number: String(currentStep), total: String(STEPS.length) }) }} ·
                            {{ t(`proposals.steps.${STEPS[currentStep - 1]}`) }}
                        </p>
                        <ol class="flex gap-1.5 md:gap-2">
                            <li
                                v-for="(key, index) in STEPS"
                                :key="key"
                                class="min-w-0 flex-auto"
                            >
                                <button
                                    type="button"
                                    class="flex min-h-11 w-full items-center gap-2 rounded-[10px] border px-2 py-2 text-left text-sm focus-visible:outline-2 focus-visible:outline-accent disabled:cursor-not-allowed md:px-3"
                                    :class="index + 1 === currentStep
                                        ? 'border-accent bg-accent-soft font-semibold text-ink'
                                        : canOpen(index + 1)
                                            ? 'border-line bg-white text-ink hover:bg-surface'
                                            : 'border-line bg-surface text-muted'"
                                    :disabled="!canOpen(index + 1)"
                                    :aria-current="index + 1 === currentStep ? 'step' : undefined"
                                    @click="goTo(index + 1)"
                                >
                                    <span
                                        class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                        :class="index + 1 === currentStep ? 'bg-accent text-white' : 'bg-surface text-ink-soft'"
                                        aria-hidden="true"
                                    >{{ index + 1 }}</span>
                                    <span class="hidden min-w-0 leading-tight md:inline">{{ t(`proposals.steps.${key}`) }}</span>
                                    <span class="sr-only md:hidden">{{ t(`proposals.steps.${key}`) }}</span>
                                    <span
                                        v-if="index + 1 > lastStep"
                                        class="sr-only"
                                    >{{ t('proposals.steps.not_yet') }}</span>
                                </button>
                            </li>
                        </ol>
                    </nav>

                    <!-- Step 1: client -->
                    <section
                        v-if="currentStep === 1"
                        class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5 md:p-6"
                        aria-labelledby="step-client"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3
                                    id="step-client"
                                    class="text-base font-semibold"
                                >
                                    {{ t('proposals.client.title') }}
                                </h3>
                                <p class="mt-1 text-sm leading-relaxed text-muted">
                                    {{ t('proposals.client.intro') }}
                                </p>
                            </div>
                            <button
                                type="button"
                                :class="buttonClasses.secondary"
                                :disabled="creatingDraft"
                                @click="newClientOpen = true"
                            >
                                <Plus
                                    :size="18"
                                    :stroke-width="2.2"
                                    aria-hidden="true"
                                />
                                {{ t('proposals.client.new') }}
                            </button>
                        </div>

                        <div
                            v-if="chosenClient && !changingClient"
                            class="flex flex-col gap-3 rounded-[10px] border border-line bg-surface p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex min-w-0 items-start gap-3">
                                <Check
                                    :size="20"
                                    :stroke-width="2.2"
                                    class="mt-0.5 shrink-0 text-success"
                                    aria-hidden="true"
                                />
                                <div class="min-w-0">
                                    <p class="text-[13px] text-muted">
                                        {{ t('proposals.client.chosen') }}
                                    </p>
                                    <p class="truncate font-semibold">
                                        {{ chosenClient.name }}
                                    </p>
                                    <p class="text-sm text-muted">
                                        {{ chosenClient.vatNumber ? t('proposals.client.vat', { vat: chosenClient.vatNumber }) : t('proposals.client.no_vat') }}
                                        · {{ chosenClient.sector }}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                :class="buttonClasses.secondary"
                                class="shrink-0"
                                @click="changingClient = true"
                            >
                                {{ t('proposals.client.change') }}
                            </button>
                        </div>
                        <p
                            v-if="chosenClient && !chosenClient.selectable"
                            class="rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
                        >
                            {{ t('proposals.client.not_selectable') }}
                        </p>

                        <template v-if="changingClient || !chosenClient">
                            <EmptyState
                                v-if="clients.length === 0"
                                :title="t('proposals.client.empty_title')"
                                :message="t('proposals.client.empty_message')"
                            >
                                <template #icon>
                                    <Building2
                                        :size="34"
                                        :stroke-width="1.6"
                                        aria-hidden="true"
                                    />
                                </template>
                                <template #action>
                                    <button
                                        type="button"
                                        :class="buttonClasses.primary"
                                        @click="newClientOpen = true"
                                    >
                                        {{ t('proposals.client.new') }}
                                    </button>
                                </template>
                            </EmptyState>

                            <template v-else>
                                <div>
                                    <label
                                        for="proposal-client-search"
                                        class="text-sm font-medium text-ink"
                                    >{{ t('proposals.client.search') }}</label>
                                    <div class="relative mt-1.5">
                                        <Search
                                            :size="18"
                                            :stroke-width="2"
                                            class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted"
                                            aria-hidden="true"
                                        />
                                        <input
                                            id="proposal-client-search"
                                            v-model="search"
                                            type="search"
                                            autocomplete="off"
                                            aria-controls="proposal-client-suggestions"
                                            class="min-h-11 w-full rounded-[10px] border border-line-strong bg-white pr-3.5 pl-10 text-[15px] text-ink outline-none focus:border-accent focus:ring-2 focus:ring-accent/20"
                                        >
                                    </div>
                                    <p
                                        v-if="errors.client_id"
                                        class="mt-1.5 text-[13px] leading-snug text-accent"
                                    >
                                        {{ errors.client_id }}
                                    </p>
                                </div>

                                <p
                                    v-if="matches.length === 0"
                                    class="text-sm text-muted"
                                >
                                    {{ t('proposals.client.no_results') }}
                                </p>
                                <ul
                                    v-else
                                    id="proposal-client-suggestions"
                                    class="flex flex-col divide-y divide-line overflow-hidden rounded-[10px] border border-line"
                                    :aria-label="t('proposals.client.suggestions', { count: String(matches.length) })"
                                >
                                    <li
                                        v-for="client in suggestions"
                                        :key="client.value"
                                    >
                                        <button
                                            type="button"
                                            class="flex min-h-14 w-full items-center justify-between gap-3 px-4 py-2.5 text-left hover:bg-surface focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent disabled:opacity-60"
                                            :class="client.value === fields.client_id ? 'bg-accent-soft' : 'bg-white'"
                                            :aria-label="t('proposals.client.choose', { client: client.label })"
                                            :disabled="creatingDraft"
                                            @click="chooseClient(client)"
                                        >
                                            <span class="min-w-0">
                                                <span class="block truncate font-medium">{{ client.label }}</span>
                                                <span class="block truncate text-[13px] text-muted">
                                                    {{ client.vatNumber ? t('proposals.client.vat', { vat: client.vatNumber }) : t('proposals.client.no_vat') }}
                                                    · {{ client.sector }}
                                                </span>
                                            </span>
                                            <ArrowRight
                                                :size="18"
                                                :stroke-width="2"
                                                class="shrink-0 text-muted"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </li>
                                </ul>
                                <p
                                    v-if="matches.length > suggestions.length"
                                    class="text-[13px] text-muted"
                                >
                                    {{ t('proposals.client.more') }}
                                </p>
                                <p
                                    v-if="creatingDraft"
                                    class="text-sm text-muted"
                                    role="status"
                                >
                                    {{ t('proposals.client.creating') }}
                                </p>
                            </template>
                        </template>
                    </section>

                    <!-- Step 2: season and period -->
                    <section
                        v-if="currentStep === 2"
                        class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5 md:p-6"
                        aria-labelledby="step-period"
                    >
                        <div>
                            <h3
                                id="step-period"
                                class="text-base font-semibold"
                            >
                                {{ t('proposals.period.title') }}
                            </h3>
                            <p class="mt-1 text-sm leading-relaxed text-muted">
                                {{ t('proposals.period.intro') }}
                            </p>
                        </div>

                        <p
                            v-if="seasons.length === 0"
                            class="rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
                        >
                            {{ t('proposals.period.no_seasons') }}
                        </p>

                        <template v-else>
                            <div class="flex flex-col gap-1.5">
                                <SelectField
                                    v-model="fields.season_id"
                                    :label="t('proposals.period.season')"
                                    :options="seasons"
                                    :placeholder="fields.season_id === '' ? t('proposals.period.season_placeholder') : undefined"
                                    :error="errors.season_id"
                                />
                                <p class="text-[13px] text-muted">
                                    <template v-if="chosenSeason">
                                        {{ chosenSeason.range }} ·
                                    </template>
                                    {{ t('proposals.period.season_help') }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="flex flex-col gap-1.5">
                                    <TextField
                                        v-model="fields.starts_on"
                                        :label="t('proposals.period.starts_on')"
                                        type="date"
                                        :disabled="fields.season_id === ''"
                                        :error="errors.starts_on"
                                    />
                                    <p class="text-[13px] text-muted">
                                        {{ t('proposals.period.starts_on_help') }}
                                    </p>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <TextField
                                        v-model="fields.ends_on"
                                        :label="t('proposals.period.ends_on')"
                                        type="date"
                                        :disabled="fields.season_id === ''"
                                        :error="errors.ends_on"
                                    />
                                    <p class="text-[13px] text-muted">
                                        {{ t('proposals.period.ends_on_help') }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </section>

                    <!-- Step 3: clubs -->
                    <ClubPicker
                        v-if="currentStep === 3"
                        :options="clubOptions"
                        :selected="selectedClubIds"
                        :draft-clubs="draft?.clubs ?? []"
                        @toggle="toggleClub"
                    />

                    <!-- Step 4: configuration by club -->
                    <section
                        v-if="currentStep === 4"
                        class="flex flex-col gap-3"
                        aria-labelledby="step-configuration"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <h3
                                    id="step-configuration"
                                    class="text-base font-semibold"
                                >
                                    {{ t('proposals.configuration.title') }}
                                </h3>
                                <p class="mt-1 text-sm leading-relaxed text-muted">
                                    {{ t('proposals.configuration.intro') }}
                                </p>
                            </div>
                            <button
                                type="button"
                                :class="buttonClasses.secondary"
                                class="shrink-0"
                                :disabled="fields.clubs.length < 2 || copying || !online"
                                :aria-describedby="fields.clubs.length < 2 ? 'proposal-copy-hint' : undefined"
                                @click="copyOpen = true"
                            >
                                <Copy
                                    :size="18"
                                    :stroke-width="2"
                                    aria-hidden="true"
                                />
                                {{ t('proposals.copy.action') }}
                            </button>
                        </div>
                        <p
                            v-if="fields.clubs.length < 2"
                            id="proposal-copy-hint"
                            class="text-[13px] text-muted"
                        >
                            {{ t('proposals.copy.need_two') }}
                        </p>
                        <p
                            v-if="errors.clubs"
                            class="rounded-[10px] bg-accent-soft px-3.5 py-3 text-sm text-ink"
                            role="alert"
                        >
                            {{ errors.clubs }}
                        </p>
                        <ClubConfiguration
                            v-for="(club, index) in fields.clubs"
                            :key="club.club_id"
                            :fields="club"
                            :option="optionsById.get(club.club_id) ?? null"
                            :server="serverClubs.get(club.club_id)"
                            :errors="clubErrors(club.club_id)"
                            :open="openClubs.includes(club.club_id)"
                            :led-step-seconds="ledStepSeconds"
                            :hospitality-max-seats="hospitalityMaxSeats"
                            @update="(value: ProposalClubFields) => updateClub(index, value)"
                            @toggle="toggleOpen(club.club_id)"
                            @remove="toggleClub(club.club_id)"
                        />
                        <button
                            type="button"
                            :class="buttonClasses.secondary"
                            class="self-start"
                            @click="goTo(3)"
                        >
                            <Plus
                                :size="18"
                                :stroke-width="2.2"
                                aria-hidden="true"
                            />
                            {{ t('proposals.configuration.add_clubs') }}
                        </button>
                    </section>

                    <!-- Step 5: summary -->
                    <DraftSummary
                        v-if="currentStep === 5 && draft"
                        :draft="draft"
                        :client-name="chosenClient?.name ?? null"
                        :sector="chosenClient?.sector ?? null"
                        @fix="goTo(4)"
                    />

                    <!-- Navigation between the steps -->
                    <div
                        v-if="draft"
                        class="flex flex-col gap-3"
                    >
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <button
                                type="button"
                                :class="buttonClasses.danger"
                                @click="confirmingDelete = true"
                            >
                                <Trash2
                                    :size="18"
                                    :stroke-width="2"
                                    aria-hidden="true"
                                />
                                {{ t('proposals.delete.action') }}
                            </button>
                            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                                <button
                                    v-if="currentStep > 1"
                                    type="button"
                                    :class="buttonClasses.secondary"
                                    @click="goTo(currentStep - 1)"
                                >
                                    <ArrowLeft
                                        :size="18"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                    {{ t('proposals.nav.back') }}
                                </button>
                                <button
                                    v-if="currentStep < lastStep"
                                    type="button"
                                    :class="buttonClasses.primary"
                                    :disabled="nextDisabled"
                                    :aria-describedby="nextHint ? 'proposal-next-hint' : undefined"
                                    @click="goTo(currentStep + 1)"
                                >
                                    {{ t('proposals.nav.next') }}
                                    <ArrowRight
                                        :size="18"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                        </div>
                        <p
                            v-if="nextHint && currentStep < lastStep"
                            id="proposal-next-hint"
                            class="text-right text-[13px] text-muted"
                        >
                            {{ nextHint }}
                        </p>
                    </div>
                </div>

                <!-- Desktop ≥ 1280 px: total in the right column -->
                <aside
                    v-if="!summaryStep"
                    class="hidden xl:block"
                >
                    <div class="sticky top-6 flex flex-col gap-3 rounded-[14px] border border-line bg-white p-5">
                        <p class="text-sm font-medium text-ink-soft">
                            {{ t('proposals.total.label') }}
                        </p>
                        <p class="font-mono text-2xl font-semibold">
                            € {{ listTotal }}
                        </p>
                        <p
                            v-if="totalStale"
                            class="text-[13px] font-medium text-accent"
                        >
                            {{ t('proposals.total.not_updated') }}
                        </p>
                        <p
                            v-if="fields.clubs.length > 0"
                            class="text-sm text-ink-soft"
                        >
                            {{ t('proposals.total.clubs', { count: String(fields.clubs.length) }) }}
                        </p>
                        <p class="text-[13px] leading-relaxed text-muted">
                            {{ t('proposals.total.help') }}
                        </p>
                        <p
                            class="border-t border-line pt-3 text-sm text-muted"
                            aria-live="polite"
                        >
                            {{ saveText }}
                        </p>
                    </div>
                </aside>
            </div>

            <!-- Tablet and smartphone: fixed total bar -->
            <div
                v-if="!summaryStep"
                class="sticky bottom-0 z-10 border-t border-line bg-white px-4 py-3 shadow-[0_-4px_12px_rgba(0,0,0,0.04)] md:px-6 xl:hidden"
            >
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[13px] text-muted">
                            {{ t('proposals.total.label') }}
                            <span v-if="fields.clubs.length > 0">· {{ t('proposals.total.clubs', { count: String(fields.clubs.length) }) }}</span>
                            <span
                                v-if="totalStale"
                                class="font-medium text-accent"
                            >· {{ t('proposals.total.not_updated') }}</span>
                        </p>
                        <p class="font-mono text-lg font-semibold">
                            € {{ listTotal }}
                        </p>
                    </div>
                    <p class="max-w-[50%] text-right text-[13px] leading-snug text-muted">
                        {{ t('proposals.total.help') }}
                    </p>
                </div>
            </div>
        </div>

        <NewClientDialog
            :open="newClientOpen"
            :url="draft ? draft.newClientUrl : newClientUrl"
            :sectors="sectors"
            :lock-version="draft ? lockVersion : null"
            @close="newClientOpen = false"
            @created="clientCreated"
        />

        <CopyClubsDialog
            :open="copyOpen"
            :clubs="copyClubs"
            :initial-source="openClubs[0] ?? null"
            :processing="copying"
            @cancel="copyOpen = false"
            @confirm="copyConfiguration"
        />

        <ConfirmDialog
            :open="confirmingDelete"
            :title="t('proposals.delete.confirm_title', { number: String(draft?.number ?? '') })"
            :confirm-label="t('proposals.delete.confirm')"
            :processing="deleting"
            @cancel="confirmingDelete = false"
            @confirm="deleteDraft"
        >
            <p>{{ t('proposals.delete.confirm_text', { number: String(draft?.number ?? '') }) }}</p>
        </ConfirmDialog>

        <NoticeDialog
            :open="conflictMessage !== undefined"
            :title="t('proposals.conflict_title')"
            :close-label="t('proposals.conflict_reload')"
            @close="reloadDraft"
        >
            <p>{{ conflictMessage }}</p>
        </NoticeDialog>
    </AppLayout>
</template>
