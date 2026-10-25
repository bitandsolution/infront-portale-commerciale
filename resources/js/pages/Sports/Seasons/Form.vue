<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Lock, Trash2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import SelectField from '@/components/forms/SelectField.vue';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { EditionRow, LayoutData, Option, SeasonDetail } from '@/types';

/**
 * New season or page of a season (M02 F-03; D-137: admin only): name, dates and status (D-317),
 * then the competitions of the season with their standard number of home matches (D-034).
 * The code is fixed after the creation (C3). In a closed season the competitions are read-only.
 */
const props = defineProps<{
    layout: LayoutData;
    season: SeasonDetail | null;
    statuses: Option[];
    saveUrl: string;
    indexUrl: string;
    editions: EditionRow[];
    availableCompetitions: Option[];
    addEditionUrl: string | null;
}>();

const { t } = useTrans();

const isNew = computed(() => props.season === null);
const readOnly = computed(() => props.season?.readOnly ?? false);

const form = useForm({
    code: props.season?.code ?? '',
    name: props.season?.name ?? '',
    starts_on: props.season?.startsOn ?? '',
    ends_on: props.season?.endsOn ?? '',
    status: props.season?.status ?? 'planned',
});

function save(): void {
    if (isNew.value) {
        form.post(props.saveUrl);
    } else {
        form.put(props.saveUrl, { preserveScroll: true });
    }
}

// Standard number of every edition, edited in place; realigned with the server after every save.
const standards = reactive<Record<number, string>>({});

watch(
    () => props.editions,
    (editions) => {
        for (const edition of editions) {
            standards[edition.id] = String(edition.standardHomeMatches);
        }
    },
    { immediate: true },
);
const rowForm = useForm({ standard_home_matches: '' });
const savingRow = ref<number | null>(null);

function saveEdition(edition: EditionRow): void {
    savingRow.value = edition.id;
    rowForm.standard_home_matches = standards[edition.id] ?? '';
    rowForm.put(edition.updateUrl, {
        preserveScroll: true,
        onFinish: () => {
            savingRow.value = null;
        },
    });
}

const addForm = useForm({ competition_id: '', standard_home_matches: '' });

function addEdition(): void {
    if (props.addEditionUrl !== null) {
        addForm.post(props.addEditionUrl, {
            preserveScroll: true,
            onSuccess: () => addForm.reset(),
        });
    }
}

const toRemove = ref<EditionRow | null>(null);
const removeForm = useForm({});

function confirmRemove(): void {
    if (toRemove.value !== null) {
        removeForm.delete(toRemove.value.destroyUrl, {
            preserveScroll: true,
            onFinish: () => {
                toRemove.value = null;
            },
        });
    }
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-4xl flex-col gap-5 p-4 md:p-6">
            <Link
                :href="indexUrl"
                class="inline-flex min-h-11 items-center gap-2 self-start text-sm font-medium text-ink-soft hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
            >
                <ArrowLeft
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('sports.seasons.back') }}
            </Link>

            <form
                class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5 md:p-6"
                novalidate
                @submit.prevent="save"
            >
                <h2 class="text-lg font-semibold">
                    {{ isNew ? t('sports.seasons.create_title') : t('sports.seasons.edit_title', { season: season?.code ?? '' }) }}
                </h2>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <TextField
                        v-if="isNew"
                        v-model="form.code"
                        :label="t('sports.seasons.fields.code')"
                        autocomplete="off"
                        :error="form.errors.code"
                    />
                    <div
                        v-else
                        class="flex flex-col gap-1.5"
                    >
                        <span class="text-sm font-medium text-ink">{{ t('sports.seasons.fields.code_fixed') }}</span>
                        <span class="flex min-h-11 items-center gap-2 font-mono text-[15px]">
                            <Lock
                                :size="16"
                                :stroke-width="2"
                                class="text-muted"
                                aria-hidden="true"
                            />
                            {{ season?.code }}
                        </span>
                        <span class="text-[13px] leading-snug text-muted">{{ t('sports.seasons.fields.code_help') }}</span>
                    </div>
                    <TextField
                        v-model="form.name"
                        :label="t('sports.seasons.fields.name')"
                        autocomplete="off"
                        :error="form.errors.name"
                    />
                    <TextField
                        v-model="form.starts_on"
                        type="date"
                        :label="t('sports.seasons.fields.starts_on')"
                        :error="form.errors.starts_on"
                    />
                    <TextField
                        v-model="form.ends_on"
                        type="date"
                        :label="t('sports.seasons.fields.ends_on')"
                        :error="form.errors.ends_on"
                    />
                    <div class="flex flex-col gap-1.5">
                        <SelectField
                            v-model="form.status"
                            :label="t('sports.seasons.fields.status')"
                            :options="statuses"
                            :error="form.errors.status"
                        />
                        <p class="text-[13px] leading-snug text-muted">
                            {{ t('sports.seasons.status_help') }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-line pt-5 sm:flex-row sm:justify-end">
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
                        {{ form.processing ? t('common.saving') : isNew ? t('sports.seasons.create') : t('sports.seasons.save') }}
                    </button>
                </div>
            </form>

            <section
                class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5 md:p-6"
                aria-labelledby="season-editions"
            >
                <div>
                    <h2
                        id="season-editions"
                        class="text-lg font-semibold"
                    >
                        {{ t('sports.editions.title') }}
                    </h2>
                    <p class="mt-1 text-sm leading-relaxed text-muted">
                        {{ isNew ? t('sports.editions.save_first') : t('sports.editions.intro') }}
                    </p>
                </div>

                <template v-if="!isNew">
                    <p
                        v-if="readOnly"
                        class="flex items-start gap-2 rounded-[10px] bg-surface px-4 py-3 text-sm text-ink-soft"
                    >
                        <Lock
                            :size="18"
                            :stroke-width="2"
                            class="mt-0.5 shrink-0"
                            aria-hidden="true"
                        />
                        {{ t('sports.seasons.closed_note') }}
                    </p>

                    <p
                        v-if="editions.length === 0"
                        class="text-sm text-muted"
                    >
                        {{ t('sports.editions.empty') }}
                    </p>

                    <ul
                        v-else
                        class="divide-y divide-line rounded-[10px] border border-line"
                    >
                        <li
                            v-for="edition in editions"
                            :key="edition.id"
                            class="flex flex-col gap-3 p-4 sm:flex-row sm:items-end"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold">
                                    {{ edition.competitionName }}
                                    <span
                                        v-if="!edition.competitionActive"
                                        class="text-[13px] font-normal text-muted"
                                    >({{ t('sports.editions.inactive_competition') }})</span>
                                </div>
                                <div class="text-[13px] text-muted">
                                    {{ t('sports.editions.calendar') }}:
                                    {{ edition.calendarLoadedAt ? t('sports.editions.calendar_loaded', { date: edition.calendarLoadedAt }) : t('sports.editions.calendar_missing') }}
                                </div>
                                <Link
                                    :href="edition.showUrl"
                                    class="mt-1 inline-flex min-h-11 items-center text-sm font-medium text-accent underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                                >
                                    {{ t('sports.editions.enrollments_link', { count: String(edition.enrollments) }) }}
                                </Link>
                            </div>
                            <div class="w-full sm:w-44">
                                <TextField
                                    :model-value="standards[edition.id] ?? ''"
                                    :label="t('sports.editions.standard')"
                                    inputmode="numeric"
                                    :disabled="readOnly"
                                    :error="savingRow === edition.id ? rowForm.errors.standard_home_matches : undefined"
                                    @update:model-value="(value: string) => (standards[edition.id] = value)"
                                />
                            </div>
                            <div
                                v-if="!readOnly"
                                class="flex gap-2"
                            >
                                <button
                                    type="button"
                                    :class="buttonClasses.secondary"
                                    :disabled="rowForm.processing || standards[edition.id] === String(edition.standardHomeMatches)"
                                    @click="saveEdition(edition)"
                                >
                                    {{ t('sports.editions.save') }}
                                </button>
                                <button
                                    type="button"
                                    :class="buttonClasses.danger"
                                    :aria-label="t('sports.editions.remove_label', { competition: edition.competitionName })"
                                    @click="toRemove = edition"
                                >
                                    <Trash2
                                        :size="18"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                    <span class="sm:sr-only">{{ t('sports.editions.remove') }}</span>
                                </button>
                            </div>
                        </li>
                    </ul>

                    <form
                        v-if="!readOnly"
                        class="flex flex-col gap-3 border-t border-line pt-4"
                        novalidate
                        @submit.prevent="addEdition"
                    >
                        <h3 class="text-[15px] font-semibold">
                            {{ t('sports.editions.add_title') }}
                        </h3>
                        <p
                            v-if="availableCompetitions.length === 0"
                            class="text-sm text-muted"
                        >
                            {{ t('sports.editions.none_available') }}
                        </p>
                        <div
                            v-else
                            class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto] sm:items-end"
                        >
                            <SelectField
                                v-model="addForm.competition_id"
                                :label="t('sports.editions.competition')"
                                :options="availableCompetitions"
                                :placeholder="t('sports.editions.choose')"
                                :error="addForm.errors.competition_id"
                            />
                            <TextField
                                v-model="addForm.standard_home_matches"
                                :label="t('sports.editions.standard')"
                                inputmode="numeric"
                                :error="addForm.errors.standard_home_matches"
                            />
                            <button
                                type="submit"
                                :class="buttonClasses.primary"
                                :disabled="addForm.processing"
                            >
                                {{ t('sports.editions.add') }}
                            </button>
                        </div>
                    </form>
                </template>
            </section>
        </div>

        <ConfirmDialog
            :open="toRemove !== null"
            :title="t('sports.editions.confirm_remove_title', { competition: toRemove?.competitionName ?? '' })"
            :confirm-label="t('sports.editions.remove')"
            :processing="removeForm.processing"
            @confirm="confirmRemove"
            @cancel="toRemove = null"
        >
            <p>{{ t('sports.editions.confirm_remove') }}</p>
        </ConfirmDialog>
    </AppLayout>
</template>
