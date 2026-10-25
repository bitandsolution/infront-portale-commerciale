<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Lock } from '@lucide/vue';
import EmptyState from '@/components/states/EmptyState.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData } from '@/types';

/**
 * Calendars page (M02 F-08; D-137: admin and manager): competitions of the season of the top bar
 * (D-317) with enrolled clubs, matches and state of the calendar.
 */
interface CalendarRow {
    id: number;
    competition: string;
    enrollments: number;
    scheduled: number;
    cancelled: number;
    standardHomeMatches: number;
    calendarLoadedAt: string | null;
    matchesUrl: string;
    enrollmentsUrl: string | null;
}

defineProps<{
    layout: LayoutData;
    season: { code: string; readOnly: boolean } | null;
    editions: CalendarRow[];
}>();

const { t } = useTrans();
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <EmptyState
                v-if="season === null"
                class="rounded-[14px] border border-line bg-white"
                :title="t('sports.calendars.no_season_title')"
                :message="t('sports.calendars.no_season')"
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
                <p class="max-w-3xl text-sm leading-relaxed text-muted">
                    {{ t('sports.calendars.intro', { season: season.code }) }}
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
                    {{ t('sports.calendars.closed_note') }}
                </p>

                <EmptyState
                    v-if="editions.length === 0"
                    class="rounded-[14px] border border-line bg-white"
                    :title="t('sports.calendars.empty_title')"
                    :message="t('sports.calendars.empty')"
                >
                    <template #icon>
                        <CalendarDays
                            :size="34"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                    </template>
                </EmptyState>

                <ul
                    v-else
                    class="grid grid-cols-1 gap-4 lg:grid-cols-2"
                >
                    <li
                        v-for="edition in editions"
                        :key="edition.id"
                        class="flex flex-col gap-3 rounded-[14px] border border-line bg-white p-5"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <h2 class="text-lg font-semibold">
                                {{ edition.competition }}
                            </h2>
                            <StatusPill
                                :label="edition.calendarLoadedAt ? t('sports.calendars.calendar_loaded', { date: edition.calendarLoadedAt }) : t('sports.calendars.calendar_missing')"
                                :tone="edition.calendarLoadedAt ? 'success' : 'notice'"
                            />
                        </div>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">
                            <div>
                                <dt class="text-muted">
                                    {{ t('sports.calendars.enrollments') }}
                                </dt>
                                <dd class="font-semibold">
                                    {{ edition.enrollments }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted">
                                    {{ t('sports.calendars.scheduled') }}
                                </dt>
                                <dd class="font-semibold">
                                    {{ edition.scheduled }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted">
                                    {{ t('sports.calendars.cancelled') }}
                                </dt>
                                <dd class="font-semibold">
                                    {{ edition.cancelled }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted">
                                    {{ t('sports.calendars.standard') }}
                                </dt>
                                <dd class="font-semibold">
                                    {{ edition.standardHomeMatches }}
                                </dd>
                            </div>
                        </dl>
                        <div class="flex flex-wrap gap-3 border-t border-line pt-3">
                            <Link
                                :href="edition.matchesUrl"
                                class="inline-flex min-h-11 items-center text-sm font-semibold text-accent underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                            >
                                {{ t('sports.calendars.matches') }}
                            </Link>
                            <Link
                                v-if="edition.enrollmentsUrl"
                                :href="edition.enrollmentsUrl"
                                class="inline-flex min-h-11 items-center text-sm font-semibold text-accent underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                            >
                                {{ t('sports.calendars.enrollments_link') }}
                            </Link>
                        </div>
                    </li>
                </ul>
            </template>
        </div>
    </AppLayout>
</template>
