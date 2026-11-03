<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { ArrowLeft, Lock, Minus, Plus } from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData } from '@/types';

/**
 * Capacities of a club in a season (M04 F-01…F-03, RB-10; D-191, D-192, M04-CA10): for every
 * resource the flag "gestito" and the capacity (LED pools in minutes per match at steps of the
 * settings, partnership contracts, hospitality seats), with committed and residual from the server.
 * A capacity under the committed quantity is saved only after an explicit confirmation; the
 * negative residual stays highlighted.
 */
type Resource = 'pool' | 'partnership' | 'hospitality';

interface CapacityRow {
    valueId: number;
    name: string;
    isEnabled: boolean;
    quantity: string;
    capacity: string;
    committed: string;
    remaining: string;
    isBelowCommitted: boolean;
}

const props = defineProps<{
    layout: LayoutData;
    season: { id: number; code: string; readOnly: boolean };
    club: { id: number; name: string };
    step: { seconds: number; label: string; minutes: string };
    groups: { resource: Resource; rows: CapacityRow[] }[];
    saveUrl: string;
    backUrl: string;
}>();

const { t } = useTrans();

const fields: Record<Resource, { group: string; quantity: string }> = {
    pool: { group: 'pools', quantity: 'minutes' },
    partnership: { group: 'tiers', quantity: 'quantity' },
    hospitality: { group: 'variants', quantity: 'seats' },
};

type Draft = Record<Resource, Record<number, { enabled: boolean; quantity: string }>>;

function draftFromProps(): Draft {
    const draft: Draft = { pool: {}, partnership: {}, hospitality: {} };
    for (const group of props.groups) {
        for (const row of group.rows) {
            draft[group.resource][row.valueId] = { enabled: row.isEnabled, quantity: row.quantity };
        }
    }

    return draft;
}

const form = reactive<Draft>(draftFromProps());
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const confirmMessage = ref<string | null>(null);

// After a save the server sends the new capacities: the form starts again from them.
watch(() => props.groups, () => Object.assign(form, draftFromProps()));

function entry(resource: Resource, valueId: number): { enabled: boolean; quantity: string } {
    return form[resource][valueId] as { enabled: boolean; quantity: string };
}

function errorOf(resource: Resource, valueId: number): string | undefined {
    const { group, quantity } = fields[resource];

    return errors.value[`${group}.${valueId}.${quantity}`] ?? errors.value[`${group}.${valueId}.enabled`];
}

// The stepper only moves the text of the field by the step; the server validates and saves.
function stepMinutes(valueId: number, direction: 1 | -1): void {
    const item = entry('pool', valueId);
    const current = Number.parseFloat(item.quantity.replace(',', '.'));
    const seconds = Number.isFinite(current) ? Math.round(current * 60) : 0;
    const next = Math.max(0, Math.round(seconds / props.step.seconds) * props.step.seconds + direction * props.step.seconds);
    item.quantity = String(next / 60).replace('.', ',');
}

function payload(confirmed: boolean): Record<string, FormDataConvertible> {
    const data: Record<string, FormDataConvertible> = { season_id: props.season.id, confirmed };
    for (const resource of Object.keys(fields) as Resource[]) {
        const { group, quantity } = fields[resource];
        data[group] = Object.fromEntries(Object.entries(form[resource]).map(([valueId, item]) => [
            valueId,
            { enabled: item.enabled, [quantity]: item.quantity.trim() === '' ? '0' : item.quantity.trim() },
        ]));
    }

    return data;
}

function save(confirmed = false): void {
    saving.value = true;
    router.put(props.saveUrl, payload(confirmed), {
        preserveScroll: true,
        onSuccess: () => {
            errors.value = {};
            confirmMessage.value = null;
        },
        onError: (received) => {
            if (received.confirm !== undefined && Object.keys(received).length === 1) {
                errors.value = {};
                confirmMessage.value = received.confirm;
            } else {
                errors.value = received;
                confirmMessage.value = null;
            }
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-4xl flex-col gap-5 p-4 md:p-6">
            <Link
                :href="backUrl"
                class="inline-flex min-h-11 items-center gap-2 self-start text-sm font-medium text-ink-soft hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
            >
                <ArrowLeft
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('inventory.back') }}
            </Link>

            <h1 class="text-xl font-semibold">
                {{ club.name }}
            </h1>
            <p class="text-sm leading-relaxed text-muted">
                {{ t('inventory.edit_intro', { club: club.name, season: season.code }) }}
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
                {{ t('inventory.closed_note', { season: season.code }) }}
            </p>

            <form
                class="flex flex-col gap-5"
                @submit.prevent="save()"
            >
                <section
                    v-for="group in groups"
                    :key="group.resource"
                    class="rounded-[14px] border border-line bg-white"
                >
                    <header class="border-b border-line px-4 py-3">
                        <h2 class="text-base font-semibold">
                            {{ t(`inventory.resource_tabs.${group.resource}`) }}
                        </h2>
                        <p
                            v-if="group.resource === 'pool'"
                            class="mt-0.5 text-sm text-muted"
                        >
                            {{ t('inventory.step_hint', { step: step.label }) }}
                        </p>
                    </header>

                    <p
                        v-if="group.rows.length === 0"
                        class="p-4 text-sm text-muted"
                    >
                        {{ t('inventory.empty_group') }}
                    </p>

                    <div
                        v-else
                        class="overflow-x-auto"
                    >
                        <table class="w-full min-w-[640px] table-fixed border-collapse text-sm">
                            <colgroup>
                                <col class="w-[26%]">
                                <col class="w-[12%]">
                                <col class="w-[30%]">
                                <col class="w-[16%]">
                                <col class="w-[16%]">
                            </colgroup>
                            <thead>
                                <tr class="bg-surface text-xs tracking-wide text-muted uppercase">
                                    <th
                                        scope="col"
                                        class="px-4 py-2.5 text-left font-semibold"
                                    >
                                        {{ t('inventory.columns.resource') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-center font-semibold"
                                    >
                                        {{ t('inventory.columns.managed') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-left font-semibold"
                                    >
                                        {{ t('inventory.columns.capacity') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-right font-semibold"
                                    >
                                        {{ t('inventory.columns.committed') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-2.5 text-right font-semibold"
                                    >
                                        {{ t('inventory.columns.remaining') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in group.rows"
                                    :key="row.valueId"
                                    class="border-t border-skeleton align-top"
                                >
                                    <th
                                        scope="row"
                                        class="px-4 py-3 text-left font-semibold"
                                    >
                                        {{ row.name }}
                                    </th>
                                    <td class="px-3 py-2 text-center">
                                        <input
                                            :id="`enabled-${group.resource}-${row.valueId}`"
                                            v-model="entry(group.resource, row.valueId).enabled"
                                            type="checkbox"
                                            class="size-5 accent-accent"
                                            :aria-label="t('inventory.managed_label', { name: row.name })"
                                            :disabled="season.readOnly || saving"
                                        >
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-1">
                                            <button
                                                v-if="group.resource === 'pool'"
                                                type="button"
                                                class="inline-flex size-11 items-center justify-center rounded-lg border border-line-strong bg-white hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent disabled:opacity-40"
                                                :aria-label="t('inventory.decrease', { step: step.label, name: row.name })"
                                                :disabled="season.readOnly || saving || !entry(group.resource, row.valueId).enabled"
                                                @click="stepMinutes(row.valueId, -1)"
                                            >
                                                <Minus
                                                    :size="16"
                                                    :stroke-width="2"
                                                    aria-hidden="true"
                                                />
                                            </button>
                                            <input
                                                :id="`quantity-${group.resource}-${row.valueId}`"
                                                v-model="entry(group.resource, row.valueId).quantity"
                                                type="text"
                                                :inputmode="group.resource === 'pool' ? 'decimal' : 'numeric'"
                                                autocomplete="off"
                                                class="h-11 w-24 rounded-lg border border-line-strong bg-white px-2 text-right font-mono text-sm focus-visible:outline-2 focus-visible:outline-accent disabled:bg-surface disabled:text-muted"
                                                :aria-label="t('inventory.quantity_label', { name: row.name, unit: t(`inventory.units.${group.resource}`) })"
                                                :aria-invalid="errorOf(group.resource, row.valueId) !== undefined"
                                                :aria-describedby="errorOf(group.resource, row.valueId) ? `error-${group.resource}-${row.valueId}` : undefined"
                                                :disabled="season.readOnly || saving || !entry(group.resource, row.valueId).enabled"
                                            >
                                            <button
                                                v-if="group.resource === 'pool'"
                                                type="button"
                                                class="inline-flex size-11 items-center justify-center rounded-lg border border-line-strong bg-white hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent disabled:opacity-40"
                                                :aria-label="t('inventory.increase', { step: step.label, name: row.name })"
                                                :disabled="season.readOnly || saving || !entry(group.resource, row.valueId).enabled"
                                                @click="stepMinutes(row.valueId, 1)"
                                            >
                                                <Plus
                                                    :size="16"
                                                    :stroke-width="2"
                                                    aria-hidden="true"
                                                />
                                            </button>
                                        </div>
                                        <p
                                            v-if="errorOf(group.resource, row.valueId)"
                                            :id="`error-${group.resource}-${row.valueId}`"
                                            class="mt-1 max-w-64 text-xs text-accent"
                                        >
                                            {{ errorOf(group.resource, row.valueId) }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono">
                                        {{ row.committed }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono"
                                        :class="row.isBelowCommitted ? 'font-semibold text-accent' : 'text-ink'"
                                    >
                                        {{ row.isEnabled ? row.remaining : t('inventory.not_managed') }}
                                        <span
                                            v-if="row.isBelowCommitted"
                                            class="sr-only"
                                        >({{ t('inventory.below_committed') }})</span>
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
                    {{ saving ? t('inventory.saving') : t('inventory.save') }}
                </button>
            </form>
        </div>

        <ConfirmDialog
            :open="confirmMessage !== null"
            :title="t('inventory.confirm_title')"
            :confirm-label="t('inventory.confirm_save')"
            :processing="saving"
            @confirm="save(true)"
            @cancel="confirmMessage = null"
        >
            <p>{{ confirmMessage }}</p>
        </ConfirmDialog>
    </AppLayout>
</template>
