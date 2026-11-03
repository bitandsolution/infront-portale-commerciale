<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarDays, Lock, Tags, TriangleAlert } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData } from '@/types';

/**
 * Page "Catalogo › Listino LED" (M03 F-05, F-11; D-333, D-337; mockup "Listino LED"): grid club ×
 * format of the price list of the season of the top bar. The admin creates the price list, writes
 * the cost for 1′ per match cell by cell (empty cell = not available) and publishes it after a
 * confirmation; the manager only reads it. Prices come formatted from the server: the page never
 * computes amounts (CLAUDE.md §4).
 */
interface Price {
    value: string;
    display: string;
}

interface PriceRow {
    clubId: number;
    name: string;
    competitionIds: number[];
    prices: Record<string, Price | null>;
}

interface PriceListInfo {
    id: number;
    status: string;
    statusLabel: string;
    isPublished: boolean;
    publishedAt: string | null;
    publishedBy: string | null;
    updateUrl: string;
    publishUrl: string;
}

interface LedPage {
    season: { id: number; code: string; readOnly: boolean };
    canManage: boolean;
    priceList: PriceListInfo | null;
    createUrl: string;
    competitions: { id: number; name: string }[];
    formats: { id: number; code: string; name: string }[];
    rows: PriceRow[];
    withoutOffers: string[];
}

const props = defineProps<{
    layout: LayoutData;
    page: LedPage | null;
}>();

const { t } = useTrans();

const competition = ref<number | null>(null);

const visibleRows = computed(() => {
    const rows = props.page?.rows ?? [];
    const selected = competition.value;

    return selected === null ? rows : rows.filter((row) => row.competitionIds.includes(selected));
});

const editable = computed(() => props.page !== null && props.page.canManage && props.page.priceList !== null);

const publishedNote = computed(() => {
    const priceList = props.page?.priceList;
    if (!priceList?.isPublished) {
        return '';
    }

    return priceList.publishedBy
        ? t('catalog.led.published_note', { date: priceList.publishedAt ?? '', name: priceList.publishedBy })
        : t('catalog.led.published_note_system', { date: priceList.publishedAt ?? '' });
});

/* Creation of the price list. */

const creating = ref(false);

function createPriceList(): void {
    if (props.page === null) {
        return;
    }
    creating.value = true;
    router.post(props.page.createUrl, { season_id: props.page.season.id }, {
        preserveScroll: true,
        onFinish: () => {
            creating.value = false;
        },
    });
}

/* Editing of one cell at a time: Enter or leaving the field saves a changed value, Esc cancels. */

const editing = ref<{ clubId: number; formatId: number; initial: string } | null>(null);
const draft = ref('');
const cellError = ref<string | null>(null);
const saving = ref(false);
const input = ref<HTMLInputElement[] | HTMLInputElement | null>(null);

function isEditing(row: PriceRow, formatId: number): boolean {
    return editing.value?.clubId === row.clubId && editing.value.formatId === formatId;
}

async function startEditing(row: PriceRow, formatId: number): Promise<void> {
    if (!editable.value || saving.value) {
        return;
    }
    const initial = row.prices[String(formatId)]?.display ?? '';
    editing.value = { clubId: row.clubId, formatId, initial };
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
    const priceList = props.page?.priceList;
    if (cell === null || priceList === undefined || priceList === null || saving.value) {
        return;
    }
    if (draft.value.trim() === cell.initial) {
        stopEditing();
        return;
    }
    saving.value = true;
    router.put(priceList.updateUrl, {
        club_id: cell.clubId,
        led_format_id: cell.formatId,
        unit_price: draft.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => stopEditing(),
        onError: (errors) => {
            cellError.value = errors.unit_price ?? Object.values(errors)[0] ?? null;
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

function cellLabel(row: PriceRow, format: { id: number; name: string }): string {
    const price = row.prices[String(format.id)];

    return t('catalog.led.cell_label', {
        club: row.name,
        format: format.name,
        value: price ? `${price.display} €` : t('catalog.led.not_available_label'),
    });
}

/* Publication with an explicit confirmation (ux-architettura §4.5, "Pubblica listino"). */

const confirmingPublish = ref(false);
const publishing = ref(false);

function publish(): void {
    const priceList = props.page?.priceList;
    if (!priceList) {
        return;
    }
    publishing.value = true;
    router.post(priceList.publishUrl, {}, {
        preserveScroll: true,
        onFinish: () => {
            publishing.value = false;
            confirmingPublish.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <EmptyState
                v-if="page === null"
                class="rounded-[14px] border border-line bg-white"
                :title="t('catalog.led.no_season_title')"
                :message="t('catalog.led.no_season')"
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
                <div class="flex flex-wrap items-center gap-3">
                    <StatusPill
                        v-if="page.priceList"
                        :label="page.priceList.statusLabel"
                        :tone="page.priceList.isPublished ? 'success' : 'notice'"
                    />
                    <p class="min-w-0 flex-1 text-sm leading-relaxed text-muted">
                        {{ t('catalog.led.intro', { season: page.season.code }) }}
                    </p>
                    <button
                        v-if="page.canManage && page.priceList && !page.priceList.isPublished"
                        type="button"
                        :class="buttonClasses.primary"
                        @click="confirmingPublish = true"
                    >
                        {{ t('catalog.led.publish') }}
                    </button>
                </div>

                <p
                    v-if="page.season.readOnly"
                    class="flex items-start gap-2 rounded-[10px] bg-surface px-4 py-3 text-sm text-ink-soft"
                >
                    <Lock
                        :size="18"
                        :stroke-width="2"
                        class="mt-0.5 shrink-0"
                        aria-hidden="true"
                    />
                    {{ t('catalog.led.closed_note', { season: page.season.code }) }}
                </p>

                <EmptyState
                    v-if="page.priceList === null"
                    class="rounded-[14px] border border-line bg-white"
                    :title="t('catalog.led.missing_title')"
                    :message="page.canManage ? t('catalog.led.missing_admin', { season: page.season.code }) : t('catalog.led.missing_reader', { season: page.season.code })"
                >
                    <template #icon>
                        <Tags
                            :size="34"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                    </template>
                    <template
                        v-if="page.canManage"
                        #action
                    >
                        <button
                            type="button"
                            :class="buttonClasses.primary"
                            :disabled="creating"
                            @click="createPriceList"
                        >
                            {{ t('catalog.led.create', { season: page.season.code }) }}
                        </button>
                    </template>
                </EmptyState>

                <template v-else>
                    <p
                        v-if="page.priceList.isPublished"
                        class="text-sm leading-relaxed text-ink-soft"
                    >
                        {{ publishedNote }}
                    </p>
                    <p
                        v-else
                        class="text-sm leading-relaxed text-ink-soft"
                    >
                        {{ t('catalog.led.draft_note') }}
                    </p>
                    <p class="text-sm leading-relaxed text-muted">
                        {{ editable ? t('catalog.led.edit_hint') : (page.season.readOnly ? '' : t('catalog.led.read_only_note')) }}
                    </p>

                    <EmptyState
                        v-if="page.rows.length === 0"
                        class="rounded-[14px] border border-line bg-white"
                        :title="t('catalog.led.no_clubs_title')"
                        :message="t('catalog.led.no_clubs', { season: page.season.code })"
                    >
                        <template #icon>
                            <Tags
                                :size="34"
                                :stroke-width="1.6"
                                aria-hidden="true"
                            />
                        </template>
                    </EmptyState>

                    <template v-else>
                        <div
                            v-if="page.competitions.length > 1"
                            role="group"
                            :aria-label="t('catalog.led.filter')"
                            class="flex flex-wrap gap-2"
                        >
                            <button
                                type="button"
                                class="min-h-11 rounded-full px-4 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                                :class="competition === null ? 'bg-ink text-white' : 'border border-line-strong bg-white text-ink'"
                                :aria-pressed="competition === null"
                                @click="competition = null"
                            >
                                {{ t('catalog.led.filter_all') }}
                            </button>
                            <button
                                v-for="item in page.competitions"
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
                            v-if="page.formats.length === 0"
                            class="text-sm text-muted"
                        >
                            {{ t('catalog.led.no_formats') }}
                        </p>

                        <div
                            v-else
                            class="overflow-x-auto rounded-[14px] border border-line bg-white"
                        >
                            <table class="w-full min-w-[640px] border-collapse text-sm">
                                <thead>
                                    <tr class="bg-surface text-xs tracking-wide text-muted uppercase">
                                        <th
                                            scope="col"
                                            class="px-4 py-2.5 text-left font-semibold"
                                        >
                                            {{ t('catalog.led.club') }}
                                        </th>
                                        <th
                                            v-for="format in page.formats"
                                            :key="format.id"
                                            scope="col"
                                            class="px-3 py-2.5 text-right font-semibold"
                                        >
                                            {{ format.name }}
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
                                            {{ row.name }}
                                        </th>
                                        <td
                                            v-for="format in page.formats"
                                            :key="format.id"
                                            class="px-2 py-1 text-right font-mono"
                                        >
                                            <div
                                                v-if="isEditing(row, format.id)"
                                                class="flex flex-col items-end gap-1"
                                            >
                                                <label
                                                    :for="`price-${row.clubId}-${format.id}`"
                                                    class="sr-only"
                                                >
                                                    {{ t('catalog.led.cell_input_label', { club: row.name, format: format.name }) }}
                                                </label>
                                                <input
                                                    :id="`price-${row.clubId}-${format.id}`"
                                                    ref="input"
                                                    v-model="draft"
                                                    type="text"
                                                    inputmode="decimal"
                                                    autocomplete="off"
                                                    class="h-11 w-32 rounded-lg border-2 border-accent bg-white px-2 text-right font-mono text-sm focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-accent"
                                                    :aria-invalid="cellError !== null"
                                                    :aria-describedby="cellError ? `price-error-${row.clubId}-${format.id}` : undefined"
                                                    :disabled="saving"
                                                    @keydown="onKeydown"
                                                    @blur="onBlur"
                                                    @input="cellError = null"
                                                >
                                                <span
                                                    v-if="saving"
                                                    class="font-sans text-xs text-muted"
                                                >
                                                    {{ t('catalog.led.saving') }}
                                                </span>
                                                <span
                                                    v-if="cellError"
                                                    :id="`price-error-${row.clubId}-${format.id}`"
                                                    class="max-w-56 text-left font-sans text-xs text-accent"
                                                >
                                                    {{ cellError }}
                                                </span>
                                            </div>
                                            <button
                                                v-else-if="editable"
                                                type="button"
                                                class="inline-flex min-h-11 w-full items-center justify-end rounded-lg px-2 hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                                                :class="row.prices[String(format.id)] ? 'text-ink' : 'text-muted'"
                                                :aria-label="cellLabel(row, format)"
                                                @click="startEditing(row, format.id)"
                                            >
                                                {{ row.prices[String(format.id)]?.display ?? t('catalog.led.not_available') }}
                                            </button>
                                            <span
                                                v-else
                                                class="inline-flex min-h-11 items-center px-2"
                                                :class="row.prices[String(format.id)] ? 'text-ink' : 'text-muted'"
                                                :aria-label="cellLabel(row, format)"
                                            >
                                                {{ row.prices[String(format.id)]?.display ?? t('catalog.led.not_available') }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </template>
            </template>
        </div>

        <ConfirmDialog
            v-if="page && page.priceList"
            :open="confirmingPublish"
            :title="t('catalog.led.publish_title', { season: page.season.code })"
            :confirm-label="t('catalog.led.publish_confirm')"
            :processing="publishing"
            @confirm="publish"
            @cancel="confirmingPublish = false"
        >
            <p>{{ t('catalog.led.publish_body') }}</p>
            <div
                v-if="page.withoutOffers.length > 0"
                class="flex items-start gap-2 rounded-[10px] border border-notice-line bg-notice-soft p-3 text-notice"
            >
                <TriangleAlert
                    :size="18"
                    :stroke-width="2"
                    class="mt-0.5 shrink-0"
                    aria-hidden="true"
                />
                <div>
                    <p>{{ t('catalog.led.publish_warning') }}</p>
                    <ul class="mt-1 list-disc pl-5">
                        <li
                            v-for="club in page.withoutOffers"
                            :key="club"
                        >
                            {{ club }}
                        </li>
                    </ul>
                </div>
            </div>
        </ConfirmDialog>
    </AppLayout>
</template>
