<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { FileSpreadsheet, Lock, Upload } from '@lucide/vue';
import { ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import SelectField from '@/components/forms/SelectField.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ImportRow, LayoutData, Option } from '@/types';

/**
 * Calendar import, step 1 (M02 F-09, FL-02; D-137: admin and manager): upload of the Excel file for a
 * competition of the season of the top bar, and the imports of the season. The preview, the
 * corrections and the confirmation are in the page of each import.
 */
const props = defineProps<{
    layout: LayoutData;
    season: { code: string; readOnly: boolean } | null;
    editions: Option[];
    selectedEdition: string;
    imports: ImportRow[];
    uploadUrl: string;
}>();

const { t } = useTrans();

const form = useForm<{ competition_season_id: string; file: File | null }>({
    competition_season_id: props.selectedEdition,
    file: null,
});
const fileInput = ref<HTMLInputElement | null>(null);

function pickFile(event: Event): void {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function upload(): void {
    form.post(props.uploadUrl, { forceFormData: true });
}

function summary(row: ImportRow): string {
    return t('sports.imports.summary', {
        inserted: String(row.inserted),
        updated: String(row.updated),
        unchanged: String(row.unchanged),
        discarded: String(row.discarded),
    });
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-5xl flex-col gap-5 p-4 md:p-6">
            <EmptyState
                v-if="season === null || editions.length === 0"
                class="rounded-[14px] border border-line bg-white"
                :title="t('sports.imports.empty_title')"
                :message="t('sports.imports.no_season')"
            >
                <template #icon>
                    <FileSpreadsheet
                        :size="34"
                        :stroke-width="1.6"
                        aria-hidden="true"
                    />
                </template>
            </EmptyState>

            <template v-else>
                <p class="max-w-3xl text-sm leading-relaxed text-muted">
                    {{ t('sports.imports.intro', { season: season.code }) }}
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
                    {{ t('sports.imports.closed_note') }}
                </p>

                <form
                    v-else
                    class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5 md:p-6"
                    novalidate
                    @submit.prevent="upload"
                >
                    <h2 class="text-lg font-semibold">
                        {{ t('sports.imports.upload_title') }}
                    </h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:items-start">
                        <SelectField
                            v-model="form.competition_season_id"
                            :label="t('sports.imports.competition')"
                            :options="editions"
                            :placeholder="t('sports.imports.choose_competition')"
                            :error="form.errors.competition_season_id"
                        />
                        <div class="flex flex-col gap-1.5">
                            <label
                                for="calendar-file"
                                class="text-sm font-medium text-ink"
                            >{{ t('sports.imports.file') }}</label>
                            <input
                                id="calendar-file"
                                ref="fileInput"
                                type="file"
                                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                class="min-h-11 text-sm file:mr-3 file:min-h-11 file:rounded-[10px] file:border file:border-line-strong file:bg-white file:px-4 file:font-semibold file:text-ink"
                                :aria-invalid="form.errors.file ? 'true' : undefined"
                                :aria-describedby="form.errors.file ? 'calendar-file-error' : 'calendar-file-help'"
                                @change="pickFile"
                            >
                            <p
                                v-if="form.errors.file"
                                id="calendar-file-error"
                                class="text-[13px] leading-snug text-accent"
                            >
                                {{ form.errors.file }}
                            </p>
                        </div>
                    </div>
                    <p
                        id="calendar-file-help"
                        class="text-[13px] leading-snug text-muted"
                    >
                        {{ t('sports.imports.layout_help') }}
                    </p>
                    <div class="flex justify-end border-t border-line pt-4">
                        <button
                            type="submit"
                            :class="buttonClasses.primary"
                            :disabled="form.processing || form.file === null"
                        >
                            <Upload
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ form.processing ? t('sports.imports.uploading') : t('sports.imports.upload') }}
                        </button>
                    </div>
                </form>

                <section
                    class="overflow-hidden rounded-[14px] border border-line bg-white"
                    aria-labelledby="imports-title"
                >
                    <h2
                        id="imports-title"
                        class="border-b border-line px-5 py-4 text-lg font-semibold"
                    >
                        {{ t('sports.imports.list_title') }}
                    </h2>
                    <EmptyState
                        v-if="imports.length === 0"
                        :title="t('sports.imports.empty_title')"
                        :message="t('sports.imports.empty')"
                    >
                        <template #icon>
                            <FileSpreadsheet
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
                            v-for="row in imports"
                            :key="row.id"
                            class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center sm:gap-4"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold">
                                    {{ row.competition }}
                                    <span class="font-normal text-muted">· {{ row.fileName }}</span>
                                </div>
                                <div class="text-[13px] text-muted">
                                    {{ t('sports.imports.by', { name: row.uploadedBy, date: row.uploadedAt ?? '' }) }}
                                    <template v-if="row.status === 'confirmed'">
                                        · {{ summary(row) }}
                                    </template>
                                </div>
                            </div>
                            <StatusPill
                                :label="row.statusLabel"
                                :tone="row.statusTone"
                            />
                            <Link
                                :href="row.showUrl"
                                :class="buttonClasses.secondary"
                            >
                                {{ t('sports.imports.open') }}
                            </Link>
                        </li>
                    </ul>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
