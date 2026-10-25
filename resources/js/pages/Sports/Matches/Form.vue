<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, watch } from 'vue';
import { buttonClasses } from '@/components/buttons';
import SelectField from '@/components/forms/SelectField.vue';
import TextField from '@/components/forms/TextField.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, Option } from '@/types';

/**
 * New match or change of a match (M02 F-08, FL-03; D-137: admin and manager). Only the clubs
 * enrolled in the chosen competition can be chosen; enrollments, season dates and duplicates are
 * checked again by the server (SaveMatch).
 */
interface MatchDetail {
    id: number;
    label: string;
    competitionSeasonId: number;
    stage: string;
    matchday: number;
    matchDate: string;
    kickoffTime: string | null;
    homeClubId: number;
    awayClubId: number;
    stadiumId: number | null;
    status: string;
    statusLabel: string;
    cancelUrl: string;
    restoreUrl: string;
}

const props = defineProps<{
    layout: LayoutData;
    match: MatchDetail | null;
    season: { code: string; startsOn: string; endsOn: string; readOnly: boolean };
    editionId: string;
    editions: Option[];
    clubsByEdition: Record<string, Option[]>;
    stages: Option[];
    stadiums: Option[];
    saveUrl: string;
    indexUrl: string;
}>();

const { t } = useTrans();

const isNew = computed(() => props.match === null);

const form = useForm({
    competition_season_id: props.editionId,
    stage: props.match?.stage ?? 'regular',
    matchday: props.match ? String(props.match.matchday) : '',
    match_date: props.match?.matchDate ?? '',
    kickoff_time: props.match?.kickoffTime ?? '',
    home_club_id: props.match ? String(props.match.homeClubId) : '',
    away_club_id: props.match ? String(props.match.awayClubId) : '',
    stadium_id: props.match?.stadiumId ? String(props.match.stadiumId) : '',
});

const clubs = computed<Option[]>(() => props.clubsByEdition[form.competition_season_id] ?? []);

watch(
    () => form.competition_season_id,
    () => {
        const ids = clubs.value.map((club) => club.value);
        if (!ids.includes(form.home_club_id)) {
            form.home_club_id = '';
        }
        if (!ids.includes(form.away_club_id)) {
            form.away_club_id = '';
        }
    },
);

function save(): void {
    if (isNew.value) {
        form.post(props.saveUrl);
    } else {
        form.put(props.saveUrl, { preserveScroll: true });
    }
}

function dateText(iso: string): string {
    const [year, month, day] = iso.split('-');

    return `${day}/${month}/${year}`;
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-3xl flex-col gap-5 p-4 md:p-6">
            <Link
                :href="indexUrl"
                class="inline-flex min-h-11 items-center gap-2 self-start text-sm font-medium text-ink-soft hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
            >
                <ArrowLeft
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('sports.matches.back') }}
            </Link>

            <form
                class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5 md:p-6"
                novalidate
                @submit.prevent="save"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-semibold">
                            {{ isNew ? t('sports.matches.create_title') : match?.label }}
                        </h2>
                        <p class="mt-1 text-sm text-muted">
                            {{ t('sports.matches.season_range', { season: season.code, start: dateText(season.startsOn), end: dateText(season.endsOn) }) }}
                        </p>
                    </div>
                    <StatusPill
                        v-if="match"
                        :label="match.statusLabel"
                        :tone="match.status === 'cancelled' ? 'muted' : 'success'"
                    />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <SelectField
                        v-model="form.competition_season_id"
                        :label="t('sports.matches.fields.competition')"
                        :options="editions"
                        :error="form.errors.competition_season_id"
                        :disabled="season.readOnly"
                    />
                    <div class="grid grid-cols-2 gap-4">
                        <SelectField
                            v-model="form.stage"
                            :label="t('sports.matches.fields.stage')"
                            :options="stages"
                            :error="form.errors.stage"
                            :disabled="season.readOnly"
                        />
                        <TextField
                            v-model="form.matchday"
                            :label="t('sports.matches.fields.matchday')"
                            inputmode="numeric"
                            :error="form.errors.matchday"
                            :disabled="season.readOnly"
                        />
                    </div>
                    <TextField
                        v-model="form.match_date"
                        type="date"
                        :label="t('sports.matches.fields.match_date')"
                        :error="form.errors.match_date"
                        :disabled="season.readOnly"
                    />
                    <TextField
                        v-model="form.kickoff_time"
                        type="time"
                        :label="t('sports.matches.fields.kickoff_time')"
                        :error="form.errors.kickoff_time"
                        :disabled="season.readOnly"
                    />
                    <SelectField
                        v-model="form.home_club_id"
                        :label="t('sports.matches.fields.home_club')"
                        :options="clubs"
                        :placeholder="t('sports.matches.fields.choose_club')"
                        :error="form.errors.home_club_id"
                        :disabled="season.readOnly"
                    />
                    <SelectField
                        v-model="form.away_club_id"
                        :label="t('sports.matches.fields.away_club')"
                        :options="clubs"
                        :placeholder="t('sports.matches.fields.choose_club')"
                        :error="form.errors.away_club_id"
                        :disabled="season.readOnly"
                    />
                    <SelectField
                        v-model="form.stadium_id"
                        :label="t('sports.matches.fields.stadium')"
                        :options="stadiums"
                        :placeholder="t('sports.matches.fields.home_stadium')"
                        :error="form.errors.stadium_id"
                        :disabled="season.readOnly"
                    />
                </div>
                <p class="text-[13px] text-muted">
                    {{ t('sports.matches.only_enrolled') }}
                </p>

                <div
                    v-if="!season.readOnly"
                    class="flex flex-col-reverse gap-2 border-t border-line pt-5 sm:flex-row sm:justify-end"
                >
                    <Link
                        :href="indexUrl"
                        :class="buttonClasses.secondary"
                    >
                        {{ t('common.cancel') }}
                    </Link>
                    <button
                        type="submit"
                        :class="buttonClasses.primary"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? t('common.saving') : isNew ? t('sports.matches.create') : t('sports.matches.save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
