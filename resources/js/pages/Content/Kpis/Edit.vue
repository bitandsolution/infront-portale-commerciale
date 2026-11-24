<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { ArrowLeft, Lock, TriangleAlert } from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import { buttonClasses } from '@/components/buttons';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData } from '@/types';

/**
 * KPI and digital of a club in a season (M08 F-05, F-08, §9; D-491, D-492; F6-01): one section per
 * group with value, source and period of every KPI, the date of the last update and the warning for
 * a value not updated for more than 6 months. An empty value removes the KPI. The server validates,
 * saves and sends the new values back.
 */
interface KpiRow {
    definitionId: number;
    name: string;
    unit: string;
    networkAggregate: boolean;
    value: string;
    source: string;
    period: string;
    updatedAt: string | null;
    updatedBy: string | null;
    isStale: boolean;
}

const props = defineProps<{
    layout: LayoutData;
    season: { id: number; code: string; readOnly: boolean };
    club: { id: number; name: string };
    staleMonths: number;
    groups: { group: string; label: string; rows: KpiRow[] }[];
    saveUrl: string;
    backUrl: string;
}>();

const { t } = useTrans();

type Field = 'value' | 'source' | 'period';
type Draft = Record<number, Record<Field, string>>;

function draftFromProps(): Draft {
    const draft: Draft = {};
    for (const group of props.groups) {
        for (const row of group.rows) {
            draft[row.definitionId] = { value: row.value, source: row.source, period: row.period };
        }
    }

    return draft;
}

const form = reactive<Draft>(draftFromProps());
const errors = ref<Record<string, string>>({});
const saving = ref(false);

// After a save the server sends the saved values: the form starts again from them.
watch(() => props.groups, () => Object.assign(form, draftFromProps()));

function entry(definitionId: number): Record<Field, string> {
    return form[definitionId] as Record<Field, string>;
}

function errorOf(definitionId: number, field: Field): string | undefined {
    return errors.value[`kpis.${definitionId}.${field}`];
}

function status(row: KpiRow): string {
    if (row.updatedAt === null) {
        return t('content.kpis.never');
    }

    return row.updatedBy !== null
        ? t('content.kpis.updated_by', { date: row.updatedAt, name: row.updatedBy })
        : t('content.kpis.updated', { date: row.updatedAt });
}

function save(): void {
    const kpis: Record<string, FormDataConvertible> = {};
    for (const [id, item] of Object.entries(form)) {
        kpis[id] = { value: item.value.trim(), source: item.source.trim(), period: item.period.trim() };
    }

    saving.value = true;
    router.put(props.saveUrl, { season_id: props.season.id, kpis }, {
        preserveScroll: true,
        onSuccess: () => {
            errors.value = {};
        },
        onError: (received) => {
            errors.value = received;
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-5xl flex-col gap-5 p-4 md:p-6">
            <Link
                :href="backUrl"
                class="inline-flex min-h-11 items-center gap-2 self-start text-sm font-medium text-ink-soft hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
            >
                <ArrowLeft
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('content.kpis.back') }}
            </Link>

            <h1 class="text-xl font-semibold">
                {{ club.name }}
            </h1>
            <p class="text-sm leading-relaxed text-muted">
                {{ t('content.kpis.edit_intro', { club: club.name, season: season.code }) }}
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
                {{ t('content.kpis.closed', { season: season.code }) }}
            </p>

            <form
                class="flex flex-col gap-5"
                @submit.prevent="save()"
            >
                <section
                    v-for="group in groups"
                    :key="group.group"
                    class="rounded-[14px] border border-line bg-white"
                >
                    <header class="border-b border-line px-4 py-3">
                        <h2 class="text-base font-semibold">
                            {{ group.label }}
                        </h2>
                    </header>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] table-fixed border-collapse text-sm">
                            <colgroup>
                                <col class="w-[22%]">
                                <col class="w-[16%]">
                                <col class="w-[21%]">
                                <col class="w-[20%]">
                                <col class="w-[21%]">
                            </colgroup>
                            <thead>
                                <tr class="bg-surface text-xs tracking-wide text-muted uppercase">
                                    <th
                                        scope="col"
                                        class="px-4 py-2.5 text-left font-semibold"
                                    >
                                        {{ t('content.kpis.columns.kpi') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-left font-semibold"
                                    >
                                        {{ t('content.kpis.value') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-left font-semibold"
                                    >
                                        {{ t('content.kpis.source') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-left font-semibold"
                                    >
                                        {{ t('content.kpis.period') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-2.5 text-left font-semibold"
                                    >
                                        {{ t('content.kpis.columns.last_update') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in group.rows"
                                    :key="row.definitionId"
                                    class="border-t border-skeleton align-top"
                                >
                                    <th
                                        scope="row"
                                        class="px-4 py-3 text-left"
                                    >
                                        <span class="block font-semibold">{{ row.name }}</span>
                                        <span class="block text-xs font-normal text-muted">
                                            {{ row.unit }}<template v-if="row.networkAggregate"> · {{ t('content.kpis.network') }}</template>
                                        </span>
                                    </th>
                                    <td
                                        v-for="field in (['value', 'source', 'period'] as const)"
                                        :key="field"
                                        class="px-3 py-2"
                                    >
                                        <input
                                            :id="`kpi-${row.definitionId}-${field}`"
                                            v-model="entry(row.definitionId)[field]"
                                            type="text"
                                            :inputmode="field === 'value' ? 'numeric' : 'text'"
                                            autocomplete="off"
                                            class="h-11 w-full rounded-lg border border-line-strong bg-white px-2 text-sm focus-visible:outline-2 focus-visible:outline-accent disabled:bg-surface disabled:text-muted"
                                            :class="field === 'value' ? 'text-right font-mono' : ''"
                                            :placeholder="field === 'value' ? '' : t(`content.kpis.${field}_placeholder`)"
                                            :aria-label="field === 'value' ? t('content.kpis.value_label', { name: row.name, unit: row.unit }) : t(`content.kpis.${field}_label`, { name: row.name })"
                                            :aria-invalid="errorOf(row.definitionId, field) !== undefined"
                                            :aria-describedby="errorOf(row.definitionId, field) ? `kpi-error-${row.definitionId}-${field}` : undefined"
                                            :disabled="season.readOnly || saving"
                                        >
                                        <p
                                            v-if="errorOf(row.definitionId, field)"
                                            :id="`kpi-error-${row.definitionId}-${field}`"
                                            class="mt-1 text-xs text-accent"
                                        >
                                            {{ errorOf(row.definitionId, field) }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <span class="block text-ink-soft">{{ status(row) }}</span>
                                        <span
                                            v-if="row.isStale"
                                            class="mt-1 inline-flex items-center gap-1 font-semibold text-accent"
                                        >
                                            <TriangleAlert
                                                :size="14"
                                                :stroke-width="2"
                                                aria-hidden="true"
                                            />
                                            {{ t('content.kpis.stale', { months: String(staleMonths) }) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <button
                    v-if="!season.readOnly"
                    type="submit"
                    class="self-start"
                    :class="buttonClasses.primary"
                    :disabled="saving"
                >
                    {{ saving ? t('content.kpis.saving') : t('content.kpis.save') }}
                </button>
            </form>
        </div>
    </AppLayout>
</template>
