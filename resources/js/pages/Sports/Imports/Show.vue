<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CircleCheck, Download, RefreshCw } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import SelectField from '@/components/forms/SelectField.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ImportOutcome, ImportPreviewRow, ImportRow, LayoutData, Option } from '@/types';

/**
 * Calendar import, steps 2 and 3 (M02 FL-02; D-319, D-325): preview of the rows with errors and
 * warnings, columns chosen by hand, unknown names associated with clubs, recalculation, confirmation
 * and summary. Everything is decided on the server: the page shows the preview and sends the choices.
 */
const props = defineProps<{
    layout: LayoutData;
    calendarImport: ImportRow;
    readOnly: boolean;
    counts: Record<ImportOutcome | 'warning', number>;
    rows: ImportPreviewRow[];
    unknownNames: { name: string; rows: number }[];
    missingMatches: string[];
    columns: {
        fields: { field: string; label: string; required: boolean; column: string }[];
        headers: Option[];
    };
    missingColumns: string[];
    clubs: Option[];
    enrollmentsUrl: string | null;
    matchesUrl: string;
    indexUrl: string;
    columnsUrl: string;
    resolveUrl: string;
    refreshUrl: string;
    confirmUrl: string;
    discardUrl: string;
}>();

const { t } = useTrans();

const isOpen = computed(() => props.calendarImport.status === 'preview' && !props.readOnly);
const needsColumns = computed(() => props.missingColumns.length > 0);
const toImport = computed(() => props.counts.new + props.counts.update);
const validRows = computed(() => toImport.value + props.counts.unchanged);

// Rows shown, by outcome; 100 at a time (a calendar has 380 to 2,000 rows, D-322).
type RowFilter = 'all' | 'error' | 'warning' | 'new' | 'update';
const filters: RowFilter[] = ['all', 'error', 'warning', 'new', 'update'];
const filter = ref<RowFilter>(props.counts.error > 0 ? 'error' : 'all');
const PAGE = 100;
const shown = ref(PAGE);

watch(filter, () => {
    shown.value = PAGE;
});

const filteredRows = computed(() =>
    props.rows.filter((row) => {
        if (filter.value === 'all') {
            return true;
        }

        return filter.value === 'warning' ? row.warnings.length > 0 : row.outcome === filter.value;
    }),
);

function outcomeLabel(row: ImportPreviewRow): string {
    return t(`sports.imports.outcomes.${row.unknown ? 'unknown' : row.outcome}`);
}

function outcomeClass(row: ImportPreviewRow): string {
    if (row.outcome === 'error') {
        return row.unknown ? 'text-notice' : 'text-accent-strong';
    }

    return row.outcome === 'unchanged' ? 'text-muted' : 'text-success';
}

// Columns of the file for each field of the layout (D-325).
const columnsForm = useForm<{ columns: Record<string, string> }>({
    columns: Object.fromEntries(props.columns.fields.map((field) => [field.field, field.column])),
});

function saveColumns(): void {
    columnsForm
        .transform((data) => ({
            columns: Object.fromEntries(Object.entries(data.columns).map(([field, column]) => [field, column === '' ? null : Number(column)])),
        }))
        .put(props.columnsUrl, { preserveScroll: true });
}

// Unknown names associated with a club, one at a time (FL-02 step 3).
const choices = reactive<Record<string, string>>({});
const resolveForm = useForm({ name: '', club_id: '' });

function associate(name: string): void {
    resolveForm.name = name;
    resolveForm.club_id = choices[name] ?? '';
    resolveForm.post(props.resolveUrl, { preserveScroll: true });
}

const actionForm = useForm({});

function refresh(): void {
    actionForm.post(props.refreshUrl, { preserveScroll: true });
}

const confirming = ref(false);
const discarding = ref(false);

function confirmImport(): void {
    actionForm.post(props.confirmUrl, {
        preserveScroll: true,
        onFinish: () => {
            confirming.value = false;
        },
    });
}

function discardImport(): void {
    actionForm.post(props.discardUrl, {
        onFinish: () => {
            discarding.value = false;
        },
    });
}

const step = computed(() => (props.calendarImport.status === 'confirmed' ? 3 : 2));

const summary = computed(() =>
    t('sports.imports.summary', {
        inserted: String(props.calendarImport.inserted),
        updated: String(props.calendarImport.updated),
        unchanged: String(props.calendarImport.unchanged),
        discarded: String(props.calendarImport.discarded),
    }),
);
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 p-4 md:p-6">
            <Link
                :href="indexUrl"
                class="inline-flex min-h-11 items-center gap-2 self-start text-sm font-medium text-ink-soft hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
            >
                <ArrowLeft
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('sports.imports.back') }}
            </Link>

            <ol
                :aria-label="t('sports.imports.steps.label')"
                class="flex flex-wrap gap-2 text-[13px]"
            >
                <li class="rounded-[8px] px-3 py-1.5 font-semibold text-success">
                    {{ t('sports.imports.steps.uploaded') }}
                </li>
                <li
                    :aria-current="step === 2 ? 'step' : undefined"
                    class="rounded-[8px] px-3 py-1.5 font-semibold"
                    :class="step === 2 ? 'bg-ink text-white' : 'text-success'"
                >
                    {{ t('sports.imports.steps.preview') }}
                </li>
                <li
                    :aria-current="step === 3 ? 'step' : undefined"
                    class="rounded-[8px] px-3 py-1.5"
                    :class="step === 3 ? 'bg-ink font-semibold text-white' : 'text-muted'"
                >
                    {{ t('sports.imports.steps.confirm') }}
                </li>
            </ol>

            <div class="flex flex-col gap-3 rounded-[14px] border border-line bg-white px-5 py-4 text-sm lg:flex-row lg:flex-wrap lg:items-center lg:gap-5">
                <div class="flex min-w-0 items-center gap-1.5">
                    <span class="text-muted">{{ t('sports.imports.file_label') }}</span>
                    <a
                        :href="calendarImport.fileUrl"
                        class="inline-flex min-h-11 min-w-0 items-center gap-1.5 font-semibold text-ink underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                    >
                        <span class="truncate">{{ calendarImport.fileName }}</span>
                        <Download
                            :size="16"
                            :stroke-width="2"
                            class="shrink-0"
                            aria-hidden="true"
                        />
                    </a>
                </div>
                <div>
                    <span class="text-muted">{{ t('sports.imports.edition_label') }}</span>
                    <b class="ml-1.5">{{ calendarImport.edition }}</b>
                </div>
                <div>
                    <span class="text-muted">{{ t('sports.imports.uploaded_label') }}</span>
                    <span class="ml-1.5">{{ t('sports.imports.by', { name: calendarImport.uploadedBy, date: calendarImport.uploadedAt ?? '' }) }}</span>
                </div>
                <div class="flex-1" />
                <StatusPill
                    :label="calendarImport.statusLabel"
                    :tone="calendarImport.statusTone"
                />
            </div>

            <p
                v-if="calendarImport.status === 'confirmed'"
                class="flex flex-col gap-3 rounded-[14px] border border-success/30 bg-success-soft px-5 py-4 text-sm text-success sm:flex-row sm:items-center"
                role="status"
            >
                <span class="flex flex-1 items-start gap-2">
                    <CircleCheck
                        :size="18"
                        :stroke-width="2"
                        class="mt-0.5 shrink-0"
                        aria-hidden="true"
                    />
                    {{ t('sports.imports.confirmed_note', { date: calendarImport.confirmedAt ?? '', summary }) }}
                </span>
                <Link
                    :href="matchesUrl"
                    :class="buttonClasses.secondary"
                >
                    {{ t('sports.imports.to_matches') }}
                </Link>
            </p>

            <p
                v-else-if="calendarImport.status === 'discarded'"
                class="rounded-[10px] bg-surface px-4 py-3 text-sm text-ink-soft"
            >
                {{ t('sports.imports.discarded_note') }}
            </p>

            <template v-if="calendarImport.status !== 'discarded'">
                <div
                    v-if="!needsColumns"
                    class="flex flex-wrap gap-2"
                >
                    <span
                        v-if="counts.new > 0"
                        class="rounded-full bg-success-soft px-2.5 py-1 text-[13px] font-semibold text-success"
                    >{{ t('sports.imports.badges.new', { count: String(counts.new) }) }}</span>
                    <span
                        v-if="counts.update > 0"
                        class="rounded-full bg-success-soft px-2.5 py-1 text-[13px] font-semibold text-success"
                    >{{ t('sports.imports.badges.update', { count: String(counts.update) }) }}</span>
                    <span
                        v-if="counts.unchanged > 0"
                        class="rounded-full bg-surface px-2.5 py-1 text-[13px] font-semibold text-muted"
                    >{{ t('sports.imports.badges.unchanged', { count: String(counts.unchanged) }) }}</span>
                    <span
                        v-if="unknownNames.length > 0"
                        class="rounded-full bg-notice-soft px-2.5 py-1 text-[13px] font-semibold text-notice"
                    >{{ t('sports.imports.badges.unknown', { count: String(unknownNames.length) }) }}</span>
                    <span
                        v-if="counts.error > 0"
                        class="rounded-full bg-accent-soft px-2.5 py-1 text-[13px] font-semibold text-accent-strong"
                    >{{ t('sports.imports.badges.error', { count: String(counts.error) }) }}</span>
                    <span
                        v-if="counts.warning > 0"
                        class="rounded-full bg-notice-soft px-2.5 py-1 text-[13px] font-semibold text-notice"
                    >{{ t('sports.imports.badges.warning', { count: String(counts.warning) }) }}</span>
                </div>

                <details
                    v-if="isOpen"
                    class="rounded-[14px] border border-line bg-white"
                    :open="needsColumns"
                >
                    <summary class="flex min-h-11 cursor-pointer items-center px-5 py-3 font-semibold focus-visible:outline-2 focus-visible:outline-accent">
                        {{ t('sports.imports.columns_title') }}
                    </summary>
                    <form
                        class="flex flex-col gap-4 border-t border-line px-5 py-4"
                        novalidate
                        @submit.prevent="saveColumns"
                    >
                        <p
                            class="text-sm"
                            :class="needsColumns ? 'font-medium text-notice' : 'text-muted'"
                        >
                            {{ needsColumns ? t('sports.imports.columns_missing') : t('sports.imports.columns_help') }}
                        </p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <SelectField
                                v-for="field in columns.fields"
                                :key="field.field"
                                v-model="columnsForm.columns[field.field]!"
                                :label="field.required ? `${field.label} (${t('sports.imports.required')})` : field.label"
                                :options="columns.headers"
                                :placeholder="t('sports.imports.column_none')"
                            />
                        </div>
                        <p
                            v-if="columnsForm.errors.columns"
                            class="text-[13px] text-accent"
                        >
                            {{ columnsForm.errors.columns }}
                        </p>
                        <button
                            type="submit"
                            :class="buttonClasses.secondary"
                            class="self-start"
                            :disabled="columnsForm.processing"
                        >
                            {{ t('sports.imports.save_columns') }}
                        </button>
                    </form>
                </details>

                <div
                    v-if="!needsColumns"
                    class="flex flex-col gap-4 lg:flex-row lg:items-start"
                >
                    <section
                        class="min-w-0 flex-1 overflow-hidden rounded-[14px] border border-line bg-white"
                        aria-labelledby="rows-filter"
                    >
                        <div
                            id="rows-filter"
                            role="group"
                            :aria-label="t('sports.imports.filters.label')"
                            class="flex flex-wrap gap-2 border-b border-line px-4 py-3"
                        >
                            <button
                                v-for="option in filters"
                                :key="option"
                                type="button"
                                class="min-h-11 rounded-[10px] px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-accent"
                                :class="filter === option ? 'bg-ink text-white' : 'bg-surface text-ink-soft hover:text-ink'"
                                :aria-pressed="filter === option"
                                @click="filter = option"
                            >
                                {{ t(`sports.imports.filters.${option}`) }}
                            </button>
                        </div>

                        <p
                            v-if="filteredRows.length === 0"
                            class="px-5 py-8 text-center text-sm text-muted"
                        >
                            {{ t('sports.imports.no_rows') }}
                        </p>

                        <div
                            v-else
                            class="overflow-x-auto"
                        >
                            <table class="w-full min-w-[760px] border-collapse text-sm">
                                <thead>
                                    <tr class="bg-surface text-left text-[12px] tracking-wide text-muted uppercase">
                                        <th
                                            scope="col"
                                            class="px-4 py-2.5 font-semibold"
                                        >
                                            {{ t('sports.imports.table.line') }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-2 py-2.5 font-semibold"
                                        >
                                            {{ t('sports.imports.table.matchday') }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-2 py-2.5 font-semibold"
                                        >
                                            {{ t('sports.imports.table.date') }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-2 py-2.5 font-semibold"
                                        >
                                            {{ t('sports.imports.table.home') }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-2 py-2.5 font-semibold"
                                        >
                                            {{ t('sports.imports.table.away') }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-4 py-2.5 font-semibold"
                                        >
                                            {{ t('sports.imports.table.outcome') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in filteredRows.slice(0, shown)"
                                        :key="row.line"
                                        class="border-t border-line align-top"
                                        :class="row.outcome === 'error' ? (row.unknown ? 'bg-notice-soft/50' : 'bg-accent-soft/50') : ''"
                                    >
                                        <td class="px-4 py-2.5 text-muted">
                                            {{ row.line }}
                                        </td>
                                        <td class="px-2 py-2.5">
                                            {{ row.matchday ?? '—' }}
                                        </td>
                                        <td class="px-2 py-2.5 font-mono text-[13px] whitespace-nowrap">
                                            {{ row.date ?? '—' }}
                                            <span
                                                v-if="row.time"
                                                class="block text-muted"
                                            >{{ row.time }}</span>
                                        </td>
                                        <td class="px-2 py-2.5">
                                            {{ row.home || '—' }}
                                            <span
                                                v-if="row.stadium"
                                                class="block text-[13px] text-muted"
                                            >{{ row.stadium }}</span>
                                        </td>
                                        <td class="px-2 py-2.5">
                                            {{ row.away || '—' }}
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <span
                                                class="font-semibold"
                                                :class="outcomeClass(row)"
                                            >{{ outcomeLabel(row) }}</span>
                                            <ul
                                                v-if="row.errors.length + row.warnings.length + row.changes.length > 0"
                                                class="mt-1 flex flex-col gap-0.5 text-[13px] leading-snug"
                                            >
                                                <li
                                                    v-for="message in row.errors"
                                                    :key="`e-${message}`"
                                                    class="text-accent-strong"
                                                >
                                                    {{ message }}
                                                </li>
                                                <li
                                                    v-for="message in row.warnings"
                                                    :key="`w-${message}`"
                                                    class="text-notice"
                                                >
                                                    {{ message }}
                                                </li>
                                                <li
                                                    v-for="message in row.changes"
                                                    :key="`c-${message}`"
                                                    class="text-ink-soft"
                                                >
                                                    {{ message }}
                                                </li>
                                            </ul>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            v-if="filteredRows.length > shown"
                            class="border-t border-line px-4 py-3 text-center"
                        >
                            <button
                                type="button"
                                :class="buttonClasses.secondary"
                                @click="shown += PAGE"
                            >
                                {{ t('sports.imports.more', { count: String(Math.min(PAGE, filteredRows.length - shown)) }) }}
                            </button>
                        </div>
                    </section>

                    <aside
                        v-if="isOpen"
                        class="flex w-full flex-col gap-4 rounded-[14px] border border-line bg-white p-5 lg:w-[380px] lg:shrink-0"
                        :aria-label="t('sports.imports.unknown_title')"
                    >
                        <template v-if="unknownNames.length > 0">
                            <h2 class="text-base font-semibold">
                                {{ t('sports.imports.unknown_title') }}
                            </h2>
                            <p class="text-[13px] leading-snug text-muted">
                                {{ t('sports.imports.unknown_help') }}
                            </p>
                            <form
                                v-for="unknown in unknownNames"
                                :key="unknown.name"
                                class="flex flex-col gap-2"
                                novalidate
                                @submit.prevent="associate(unknown.name)"
                            >
                                <SelectField
                                    :model-value="choices[unknown.name] ?? ''"
                                    :label="`${t('sports.imports.unknown_label', { name: unknown.name })} · ${t('sports.imports.unknown_rows', { count: String(unknown.rows) })}`"
                                    :options="clubs"
                                    :placeholder="t('sports.imports.choose_club')"
                                    :error="resolveForm.name === unknown.name ? resolveForm.errors.club_id : undefined"
                                    @update:model-value="(value: string) => (choices[unknown.name] = value)"
                                />
                                <button
                                    type="submit"
                                    :class="buttonClasses.secondary"
                                    class="self-start"
                                    :disabled="resolveForm.processing || !choices[unknown.name]"
                                >
                                    {{ t('sports.imports.associate') }}
                                </button>
                            </form>
                            <p class="text-[13px] leading-snug text-muted">
                                {{ t('sports.imports.missing_club') }}
                            </p>
                        </template>

                        <p class="text-[13px] leading-snug text-muted">
                            <template v-if="enrollmentsUrl">
                                {{ t('sports.imports.enrollments_help') }}
                                <Link
                                    :href="enrollmentsUrl"
                                    class="font-semibold text-accent underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                                >
                                    {{ t('sports.imports.enrollments_link') }}
                                </Link>
                            </template>
                            <template v-else>
                                {{ t('sports.imports.enrollments_admin') }}
                            </template>
                        </p>

                        <button
                            type="button"
                            :class="buttonClasses.secondary"
                            :disabled="actionForm.processing"
                            @click="refresh"
                        >
                            <RefreshCw
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('sports.imports.refresh') }}
                        </button>

                        <details
                            v-if="missingMatches.length > 0"
                            class="text-sm"
                        >
                            <summary class="flex min-h-11 cursor-pointer items-center font-semibold focus-visible:outline-2 focus-visible:outline-accent">
                                {{ t('sports.imports.missing_title', { count: String(missingMatches.length) }) }}
                            </summary>
                            <p class="mb-2 text-[13px] leading-snug text-muted">
                                {{ t('sports.imports.missing_help') }}
                            </p>
                            <ul class="flex max-h-60 flex-col gap-1 overflow-y-auto text-[13px]">
                                <li
                                    v-for="label in missingMatches"
                                    :key="label"
                                >
                                    {{ label }}
                                </li>
                            </ul>
                        </details>

                        <p class="border-t border-line pt-4 text-[13px] leading-snug text-muted">
                            {{ t('sports.imports.discard_note') }}
                        </p>
                        <button
                            type="button"
                            :class="buttonClasses.primary"
                            :disabled="actionForm.processing || validRows === 0"
                            @click="confirming = true"
                        >
                            {{ toImport > 0 ? t('sports.imports.confirm', { count: String(toImport) }) : t('sports.imports.confirm_unchanged') }}
                        </button>
                        <button
                            type="button"
                            :class="buttonClasses.danger"
                            :disabled="actionForm.processing"
                            @click="discarding = true"
                        >
                            {{ t('sports.imports.discard') }}
                        </button>
                    </aside>
                </div>

                <div
                    v-else-if="isOpen"
                    class="flex justify-end"
                >
                    <button
                        type="button"
                        :class="buttonClasses.danger"
                        :disabled="actionForm.processing"
                        @click="discarding = true"
                    >
                        {{ t('sports.imports.discard') }}
                    </button>
                </div>
            </template>
        </div>

        <ConfirmDialog
            :open="confirming"
            :title="t('sports.imports.confirm_title', { edition: calendarImport.edition })"
            :confirm-label="toImport > 0 ? t('sports.imports.confirm', { count: String(toImport) }) : t('sports.imports.confirm_unchanged')"
            :processing="actionForm.processing"
            @confirm="confirmImport"
            @cancel="confirming = false"
        >
            <p>
                {{ t('sports.imports.confirm_body', { new: String(counts.new), update: String(counts.update), error: String(counts.error) }) }}
            </p>
        </ConfirmDialog>

        <ConfirmDialog
            :open="discarding"
            :title="t('sports.imports.discard_title')"
            :confirm-label="t('sports.imports.discard')"
            :processing="actionForm.processing"
            @confirm="discardImport"
            @cancel="discarding = false"
        >
            <p>{{ t('sports.imports.discard_body') }}</p>
        </ConfirmDialog>
    </AppLayout>
</template>
