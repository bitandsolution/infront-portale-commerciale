<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Lock, Pencil, Plus } from '@lucide/vue';
import { reactive, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import SelectField from '@/components/forms/SelectField.vue';
import TextField from '@/components/forms/TextField.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, MatchRow, Option } from '@/types';

/**
 * Matches page (M02 F-08, FL-03; D-137: admin and manager): matches of one competition of the season
 * of the top bar (D-317), filtered on the server by club and matchday. Cancelled matches stay in the
 * list and can be restored (D-319). A closed season is read-only.
 */
const props = defineProps<{
    layout: LayoutData;
    season: { code: string; readOnly: boolean } | null;
    editions: Option[];
    filters: { edition: string; club: string; matchday: string };
    clubs: Option[];
    matches: MatchRow[];
    indexUrl: string;
    createUrl: string | null;
}>();

const { t } = useTrans();

const filter = reactive({ ...props.filters });

function apply(changedEdition = false): void {
    router.get(
        props.indexUrl,
        {
            edizione: filter.edition || undefined,
            club: changedEdition ? undefined : filter.club || undefined,
            giornata: changedEdition ? undefined : filter.matchday || undefined,
        },
        { preserveScroll: true },
    );
}

const toCancel = ref<MatchRow | null>(null);
const statusForm = useForm({});

function confirmCancel(): void {
    if (toCancel.value !== null) {
        statusForm.post(toCancel.value.cancelUrl, {
            preserveScroll: true,
            onFinish: () => {
                toCancel.value = null;
            },
        });
    }
}

function restore(match: MatchRow): void {
    statusForm.post(match.restoreUrl, { preserveScroll: true });
}

function matchName(match: MatchRow): string {
    return `${match.homeClub} – ${match.awayClub}`;
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <EmptyState
                v-if="season === null || editions.length === 0"
                class="rounded-[14px] border border-line bg-white"
                :title="t('sports.matches.empty_title')"
                :message="t('sports.matches.no_season')"
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
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-muted">
                            {{ t('sports.matches.count', { count: String(matches.length) }) }}
                        </p>
                        <p class="mt-1 max-w-2xl text-sm leading-relaxed text-muted">
                            {{ t('sports.matches.intro', { season: season.code }) }}
                        </p>
                    </div>
                    <Link
                        v-if="createUrl && !season.readOnly"
                        :href="createUrl"
                        :class="buttonClasses.primary"
                    >
                        <Plus
                            :size="18"
                            :stroke-width="2.2"
                            aria-hidden="true"
                        />
                        {{ t('sports.matches.new') }}
                    </Link>
                </div>

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
                    {{ t('sports.matches.closed_note') }}
                </p>

                <form
                    class="grid grid-cols-1 gap-3 rounded-[14px] border border-line bg-white p-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_minmax(0,1fr)_auto] sm:items-end"
                    @submit.prevent="apply()"
                >
                    <SelectField
                        v-model="filter.edition"
                        :label="t('sports.matches.competition')"
                        :options="editions"
                        @update:model-value="apply(true)"
                    />
                    <SelectField
                        v-model="filter.club"
                        :label="t('sports.matches.club')"
                        :options="clubs"
                        :placeholder="t('sports.matches.all_clubs')"
                    />
                    <TextField
                        v-model="filter.matchday"
                        :label="t('sports.matches.matchday')"
                        inputmode="numeric"
                    />
                    <button
                        type="submit"
                        :class="buttonClasses.secondary"
                    >
                        {{ t('sports.matches.apply') }}
                    </button>
                </form>

                <div class="overflow-hidden rounded-[14px] border border-line bg-white">
                    <EmptyState
                        v-if="matches.length === 0"
                        :title="t('sports.matches.empty_title')"
                        :message="t('sports.matches.empty')"
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
                        class="divide-y divide-line"
                    >
                        <li
                            v-for="match in matches"
                            :key="match.id"
                            class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:gap-4"
                            :class="match.status === 'cancelled' ? 'bg-surface/60' : ''"
                        >
                            <div class="w-24 shrink-0 text-[13px] text-muted">
                                {{ t('sports.matches.matchday_short', { number: String(match.matchday) }) }}
                                <span
                                    v-if="match.stage !== 'regular'"
                                    class="block"
                                >{{ match.stageLabel }}</span>
                            </div>
                            <div class="w-36 shrink-0 font-mono text-[13px]">
                                {{ match.date }}
                                <span class="block text-muted">{{ match.kickoff ?? t('sports.matches.time_unknown') }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div
                                    class="font-semibold"
                                    :class="match.status === 'cancelled' ? 'text-muted line-through' : ''"
                                >
                                    {{ matchName(match) }}
                                </div>
                                <div
                                    v-if="match.stadium"
                                    class="text-[13px] text-muted"
                                >
                                    {{ match.stadium }}
                                </div>
                            </div>
                            <StatusPill
                                :label="match.statusLabel"
                                :tone="match.status === 'cancelled' ? 'muted' : 'success'"
                            />
                            <div
                                v-if="!season.readOnly"
                                class="flex gap-2"
                            >
                                <Link
                                    :href="match.editUrl"
                                    class="flex size-11 items-center justify-center rounded-[10px] text-muted hover:bg-surface hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
                                    :aria-label="t('sports.matches.edit_label', { match: matchName(match) })"
                                >
                                    <Pencil
                                        :size="18"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                </Link>
                                <button
                                    v-if="match.status === 'scheduled'"
                                    type="button"
                                    :class="buttonClasses.danger"
                                    @click="toCancel = match"
                                >
                                    {{ t('sports.matches.cancel') }}
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    :class="buttonClasses.secondary"
                                    :disabled="statusForm.processing"
                                    @click="restore(match)"
                                >
                                    {{ t('sports.matches.restore') }}
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </template>
        </div>

        <ConfirmDialog
            :open="toCancel !== null"
            :title="t('sports.matches.confirm_cancel_title', { match: toCancel ? matchName(toCancel) : '' })"
            :confirm-label="t('sports.matches.cancel')"
            :processing="statusForm.processing"
            @confirm="confirmCancel"
            @cancel="toCancel = null"
        >
            <p>{{ t('sports.matches.confirm_cancel') }}</p>
        </ConfirmDialog>
    </AppLayout>
</template>
