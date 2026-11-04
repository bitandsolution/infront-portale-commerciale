<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { useTrans } from '@/composables/useTrans';
import type { PriceCell, PriceColumn, PriceRow } from '@/types';

/**
 * Grid club × value of the catalogue of a price list (M03 F-05, F-07, F-09; D-337, D-341; mockup
 * "Listino LED"): competition filter, one cell edited at a time (Enter or leaving the field saves a
 * changed amount, Esc cancels, an empty cell is "not available"). Amounts come formatted from the
 * server: the grid never computes them (CLAUDE.md §4).
 */
const props = defineProps<{
    kind: string;
    columns: PriceColumn[];
    rows: PriceRow[];
    competitions: { id: number; name: string }[];
    editable: boolean;
    updateUrl: string | null;
}>();

const { t } = useTrans();

const competition = ref<number | null>(null);

const visibleRows = computed(() => {
    const selected = competition.value;

    return selected === null ? props.rows : props.rows.filter((row) => row.competitionIds.includes(selected));
});

const editing = ref<{ clubId: number; columnId: number; initial: string } | null>(null);
// Cell of the indicative seasonal price under the LED grid (D-340): the last one edited or chosen.
const selected = ref<{ clubId: number; columnId: number } | null>(null);
const showsSeasonal = computed(() => props.kind === 'led');

function isSelected(row: PriceRow, columnId: number): boolean {
    return selected.value?.clubId === row.clubId && selected.value.columnId === columnId;
}

function select(row: PriceRow, columnId: number): void {
    selected.value = { clubId: row.clubId, columnId };
}

const seasonalHint = computed(() => {
    const cell = selected.value;
    if (!showsSeasonal.value || cell === null) {
        return t('catalog.price_list.seasonal_prompt');
    }
    const row = props.rows.find((item) => item.clubId === cell.clubId);
    const column = props.columns.find((item) => item.id === cell.columnId);
    const amount = row ? (row.prices[String(cell.columnId)] ?? null) : null;
    if (!row || !column || amount === null) {
        return t('catalog.price_list.seasonal_prompt');
    }
    if (!amount.seasonal) {
        return t('catalog.price_list.seasonal_none', { club: row.name, value: column.name, unit: amount.display });
    }

    return t('catalog.price_list.seasonal_hint', {
        club: row.name,
        value: column.name,
        unit: amount.display,
        total: amount.seasonal.display,
        matches: String(amount.seasonal.matches),
        source: t(amount.seasonal.fromCalendar ? 'catalog.price_list.seasonal_calendar' : 'catalog.price_list.seasonal_standard'),
    });
});
const draft = ref('');
const cellError = ref<string | null>(null);
const saving = ref(false);
const input = ref<HTMLInputElement[] | HTMLInputElement | null>(null);

function price(row: PriceRow, columnId: number): PriceCell | null {
    return row.prices[String(columnId)] ?? null;
}

function isEditing(row: PriceRow, columnId: number): boolean {
    return editing.value?.clubId === row.clubId && editing.value.columnId === columnId;
}

async function startEditing(row: PriceRow, columnId: number): Promise<void> {
    if (!props.editable || saving.value) {
        return;
    }
    const initial = price(row, columnId)?.display ?? '';
    select(row, columnId);
    editing.value = { clubId: row.clubId, columnId, initial };
    draft.value = initial;
    cellError.value = null;
    await nextTick();
    const element = Array.isArray(input.value) ? input.value[0] : input.value;
    element?.focus();
    element?.select();
}

function stopEditing(): void {
    editing.value = null;
    cellError.value = null;
}

function save(): void {
    const cell = editing.value;
    if (cell === null || props.updateUrl === null || saving.value) {
        return;
    }
    if (draft.value.trim() === cell.initial) {
        stopEditing();
        return;
    }
    saving.value = true;
    router.put(props.updateUrl, {
        club_id: cell.clubId,
        column_id: cell.columnId,
        amount: draft.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => stopEditing(),
        onError: (errors) => {
            cellError.value = errors.amount ?? Object.values(errors)[0] ?? null;
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter') {
        event.preventDefault();
        save();
    } else if (event.key === 'Escape') {
        event.preventDefault();
        stopEditing();
    }
}

function onBlur(): void {
    // Escape has already closed the cell; a failed save keeps it open with its message.
    if (editing.value !== null && cellError.value === null) {
        save();
    }
}

function cellLabel(row: PriceRow, column: PriceColumn): string {
    const cell = price(row, column.id);

    return t('catalog.price_list.cell_label', {
        club: row.name,
        value: column.name,
        amount: cell ? `${cell.display} €` : t('catalog.price_list.not_available_label'),
    });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            v-if="competitions.length > 1"
            role="group"
            :aria-label="t('catalog.price_list.filter')"
            class="flex flex-wrap gap-2"
        >
            <button
                type="button"
                class="min-h-11 rounded-full px-4 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                :class="competition === null ? 'bg-ink text-white' : 'border border-line-strong bg-white text-ink'"
                :aria-pressed="competition === null"
                @click="competition = null"
            >
                {{ t('catalog.price_list.filter_all') }}
            </button>
            <button
                v-for="item in competitions"
                :key="item.id"
                type="button"
                class="min-h-11 rounded-full px-4 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                :class="competition === item.id ? 'bg-ink text-white' : 'border border-line-strong bg-white text-ink'"
                :aria-pressed="competition === item.id"
                @click="competition = item.id"
            >
                {{ item.name }}
            </button>
        </div>

        <p
            v-if="columns.length === 0"
            class="text-sm text-muted"
        >
            {{ t('catalog.price_list.no_columns') }}
        </p>

        <div
            v-else
            class="overflow-x-auto rounded-[14px] border border-line bg-white"
        >
            <table class="w-full min-w-[560px] border-collapse text-sm">
                <thead>
                    <tr class="bg-surface text-xs tracking-wide text-muted uppercase">
                        <th
                            scope="col"
                            class="px-4 py-2.5 text-left font-semibold"
                        >
                            {{ t('catalog.price_list.club') }}
                        </th>
                        <th
                            v-for="column in columns"
                            :key="column.id"
                            scope="col"
                            class="px-3 py-2.5 text-right font-semibold"
                        >
                            {{ column.name }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in visibleRows"
                        :key="row.clubId"
                        class="border-t border-skeleton"
                    >
                        <th
                            scope="row"
                            class="px-4 py-2 text-left font-semibold"
                        >
                            <Link
                                v-if="row.benefitsUrl"
                                :href="row.benefitsUrl"
                                class="inline-flex min-h-11 items-center text-ink underline-offset-2 hover:text-accent hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                                :aria-label="t('catalog.price_list.benefits_link', { club: row.name })"
                            >
                                {{ row.name }}
                            </Link>
                            <template v-else>
                                {{ row.name }}
                            </template>
                        </th>
                        <td
                            v-for="column in columns"
                            :key="column.id"
                            class="px-2 py-1 text-right font-mono"
                        >
                            <div
                                v-if="isEditing(row, column.id)"
                                class="flex flex-col items-end gap-1"
                            >
                                <label
                                    :for="`price-${kind}-${row.clubId}-${column.id}`"
                                    class="sr-only"
                                >
                                    {{ t(`catalog.price_list.kinds.${kind}.cell_input_label`, { club: row.name, value: column.name }) }}
                                </label>
                                <input
                                    :id="`price-${kind}-${row.clubId}-${column.id}`"
                                    ref="input"
                                    v-model="draft"
                                    type="text"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    class="h-11 w-32 rounded-lg border-2 border-accent bg-white px-2 text-right font-mono text-sm focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-accent"
                                    :aria-invalid="cellError !== null"
                                    :aria-describedby="cellError ? `price-error-${kind}-${row.clubId}-${column.id}` : undefined"
                                    :disabled="saving"
                                    @keydown="onKeydown"
                                    @blur="onBlur"
                                    @input="cellError = null"
                                >
                                <span
                                    v-if="saving"
                                    class="font-sans text-xs text-muted"
                                >
                                    {{ t('catalog.price_list.saving') }}
                                </span>
                                <span
                                    v-if="cellError"
                                    :id="`price-error-${kind}-${row.clubId}-${column.id}`"
                                    class="max-w-56 text-left font-sans text-xs text-accent"
                                >
                                    {{ cellError }}
                                </span>
                            </div>
                            <button
                                v-else-if="editable"
                                type="button"
                                class="inline-flex min-h-11 w-full items-center justify-end rounded-lg px-2 hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                                :class="[price(row, column.id) ? 'text-ink' : 'text-muted', showsSeasonal && isSelected(row, column.id) ? 'bg-accent-soft' : '']"
                                :aria-label="cellLabel(row, column)"
                                @click="startEditing(row, column.id)"
                            >
                                {{ price(row, column.id)?.display ?? t('catalog.price_list.not_available') }}
                            </button>
                            <button
                                v-else-if="showsSeasonal && price(row, column.id)"
                                type="button"
                                class="inline-flex min-h-11 w-full items-center justify-end rounded-lg px-2 text-ink hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                                :class="isSelected(row, column.id) ? 'bg-accent-soft' : ''"
                                :aria-label="t('catalog.price_list.select_cell', { club: row.name, value: column.name })"
                                :aria-pressed="isSelected(row, column.id)"
                                @click="select(row, column.id)"
                            >
                                {{ price(row, column.id)?.display }}
                            </button>
                            <span
                                v-else
                                class="inline-flex min-h-11 items-center px-2"
                                :class="price(row, column.id) ? 'text-ink' : 'text-muted'"
                                :aria-label="cellLabel(row, column)"
                            >
                                {{ price(row, column.id)?.display ?? t('catalog.price_list.not_available') }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p
            v-if="showsSeasonal && columns.length > 0 && rows.length > 0"
            class="text-[13px] text-muted"
            aria-live="polite"
        >
            {{ seasonalHint }}
        </p>
    </div>
</template>
