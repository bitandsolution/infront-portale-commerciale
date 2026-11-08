<script setup lang="ts">
import { AlertTriangle, Check, Shield, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import EmptyState from '@/components/states/EmptyState.vue';
import { useTrans } from '@/composables/useTrans';
import type { ProposalClubOption, ProposalDraftClub } from '@/types';

/**
 * Step 3 of the configurator (M06 §4, RB-09; D-085; mockup "Periodo e club"): multiple choice among
 * the selectable clubs of the season (SellableOptions::selectableClubs), with a filter by league when
 * there is more than one. A club of the draft that is no longer selectable (list or enrolment
 * changed, M06 §9) stays listed with its reason and can only be removed.
 */
const props = defineProps<{
    options: ProposalClubOption[];
    selected: number[];
    draftClubs: ProposalDraftClub[];
}>();

const emit = defineEmits<{ toggle: [clubId: number] }>();

const { t } = useTrans();

const ALL = '';
const competition = ref(ALL);

const competitions = computed(() => {
    const names: string[] = [];
    for (const option of props.options) {
        if (option.competition !== null && !names.includes(option.competition)) {
            names.push(option.competition);
        }
    }

    return names;
});

const shown = computed(() => (competition.value === ALL
    ? props.options
    : props.options.filter((option) => option.competition === competition.value)));

const selectableIds = computed(() => new Set(props.options.map((option) => option.id)));
const serverClubs = computed(() => new Map(props.draftClubs.map((club) => [club.clubId, club])));
// Clubs of the draft that are not selectable any more (M06 §9): only removable.
const lost = computed(() => props.draftClubs.filter((club) => props.selected.includes(club.clubId) && !selectableIds.value.has(club.clubId)));

function isSelected(clubId: number): boolean {
    return props.selected.includes(clubId);
}

const documentKind = computed(() => (props.selected.length >= 2 ? t('proposals.clubs.document_network') : t('proposals.clubs.document_club')));
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5 md:p-6"
        aria-labelledby="step-clubs"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3
                    id="step-clubs"
                    class="text-base font-semibold"
                >
                    {{ t('proposals.clubs.title') }}
                </h3>
                <p class="mt-1 text-sm leading-relaxed text-muted">
                    {{ t('proposals.clubs.intro') }}
                </p>
            </div>
            <p
                class="text-sm text-muted"
                aria-live="polite"
            >
                {{ t('proposals.clubs.selected', { count: String(selected.length) }) }}
            </p>
        </div>

        <div
            v-for="club in lost"
            :key="`lost-${club.clubId}`"
            class="flex flex-col gap-3 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink sm:flex-row sm:items-center sm:justify-between"
            role="status"
        >
            <p class="flex items-start gap-2">
                <AlertTriangle
                    :size="18"
                    :stroke-width="2"
                    class="mt-0.5 shrink-0"
                    aria-hidden="true"
                />
                <span>{{ club.errors.club ?? t('proposals.clubs.not_selectable', { club: club.name }) }}</span>
            </p>
            <button
                type="button"
                :class="buttonClasses.danger"
                class="shrink-0"
                @click="emit('toggle', club.clubId)"
            >
                <Trash2
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('proposals.configuration.remove') }}
            </button>
        </div>

        <EmptyState
            v-if="options.length === 0"
            :title="t('proposals.clubs.empty_title')"
            :message="t('proposals.clubs.empty_message')"
        >
            <template #icon>
                <Shield
                    :size="34"
                    :stroke-width="1.6"
                    aria-hidden="true"
                />
            </template>
        </EmptyState>

        <template v-else>
            <div
                v-if="competitions.length > 1"
                class="flex flex-wrap gap-2"
                role="group"
                :aria-label="t('proposals.clubs.filter')"
            >
                <button
                    v-for="name in [ALL, ...competitions]"
                    :key="name || 'all'"
                    type="button"
                    class="min-h-11 rounded-full border px-4 text-sm focus-visible:outline-2 focus-visible:outline-accent"
                    :class="competition === name ? 'border-ink bg-ink text-white' : 'border-line-strong bg-white text-ink hover:bg-surface'"
                    :aria-pressed="competition === name"
                    @click="competition = name"
                >
                    {{ name === ALL ? t('proposals.clubs.all') : name }}
                </button>
            </div>

            <ul class="grid grid-cols-[repeat(auto-fill,minmax(200px,1fr))] gap-3">
                <li
                    v-for="club in shown"
                    :key="club.id"
                >
                    <button
                        type="button"
                        class="flex min-h-[76px] w-full items-center gap-3 rounded-[12px] px-3.5 py-2.5 text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        :class="isSelected(club.id) ? 'border-2 border-accent bg-accent-soft' : 'border border-line-strong bg-white hover:bg-surface'"
                        :aria-pressed="isSelected(club.id)"
                        @click="emit('toggle', club.id)"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full text-[11px] font-bold"
                            :class="isSelected(club.id) ? 'bg-accent text-white' : 'bg-surface text-ink-soft'"
                            aria-hidden="true"
                        >
                            <Check
                                v-if="isSelected(club.id)"
                                :size="18"
                                :stroke-width="2.4"
                            />
                            <template v-else>{{ club.initials }}</template>
                        </span>
                        <span class="min-w-0">
                            <span
                                class="block truncate text-[15px]"
                                :class="isSelected(club.id) ? 'font-semibold' : 'font-medium'"
                            >{{ club.name }}</span>
                            <span class="block truncate text-[12px] text-muted">
                                {{ isSelected(club.id) && typeof serverClubs.get(club.id)?.matches === 'number'
                                    ? t('proposals.clubs.matches', { count: String(serverClubs.get(club.id)?.matches) })
                                    : (club.competition ?? '') }}
                            </span>
                        </span>
                    </button>
                </li>
            </ul>
        </template>

        <p
            v-if="selected.length > 0"
            class="border-t border-line pt-3 text-sm text-muted"
        >
            {{ t('proposals.clubs.document', { kind: documentKind }) }}
        </p>
    </section>
</template>
