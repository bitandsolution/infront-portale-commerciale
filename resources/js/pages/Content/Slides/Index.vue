<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Images, Plus, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import SelectField from '@/components/forms/SelectField.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ContentSeason, LayoutData, Option, SlideRow } from '@/types';

/**
 * "Contenuti › Libreria slide" (M08 F-01; DM-02, D-402…D-406): admin and manager. The slides of the
 * season of the top bar, grouped by section in the order of the documents; filters in the browser
 * (a season has at most a few hundred slides).
 */
const props = defineProps<{
    layout: LayoutData;
    season: ContentSeason | null;
    slides: SlideRow[];
    clubs: Option[];
    createUrl: string | null;
}>();

const { t } = useTrans();

const scope = ref('');
const documentType = ref('');
const status = ref('active');

const scopeOptions = computed<Option[]>(() => [{ value: 'infront', label: t('content.slides.filters.infront') }, ...props.clubs]);

const documentOptions = computed<Option[]>(() => [
    { value: 'network', label: t('content.slides.filters.network') },
    { value: 'club', label: t('content.slides.filters.club') },
]);

const statusOptions = computed<Option[]>(() => [
    { value: 'active', label: t('content.slides.filters.active_only') },
    { value: 'all', label: t('content.slides.filters.all_statuses') },
]);

const filtered = computed(() =>
    props.slides.filter((slide) => {
        if (scope.value === 'infront' && slide.scope !== 'infront') {
            return false;
        }
        if (scope.value !== '' && scope.value !== 'infront' && String(slide.clubId) !== scope.value) {
            return false;
        }
        if (documentType.value !== '' && slide.documentType !== documentType.value && slide.documentType !== 'both') {
            return false;
        }

        return status.value === 'all' || slide.isActive;
    }),
);

/** Rows grouped by section, already in the order of the documents. */
const sections = computed(() => {
    const groups: { id: number; name: string; slides: SlideRow[] }[] = [];

    for (const slide of filtered.value) {
        const last = groups[groups.length - 1];
        if (last !== undefined && last.id === slide.sectionId) {
            last.slides.push(slide);
        } else {
            groups.push({ id: slide.sectionId, name: slide.sectionName, slides: [slide] });
        }
    }

    return groups;
});
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-7xl flex-col gap-5 p-4 md:p-6">
            <EmptyState
                v-if="season === null"
                :title="t('content.slides.empty_title')"
                :message="t('content.slides.no_season')"
            >
                <template #icon>
                    <Images
                        :size="34"
                        :stroke-width="1.6"
                        aria-hidden="true"
                    />
                </template>
            </EmptyState>

            <template v-else>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm text-muted">
                            {{ t('content.slides.count', { count: String(filtered.length) }) }}
                        </p>
                        <p class="mt-1 max-w-3xl text-sm leading-relaxed text-muted">
                            {{ t('content.slides.intro', { season: season.code }) }}
                        </p>
                        <p
                            v-if="season.closed"
                            role="note"
                            class="mt-2 rounded-[10px] bg-notice-soft px-3 py-2 text-sm text-notice"
                        >
                            {{ t('content.slides.closed', { season: season.code }) }}
                        </p>
                    </div>
                    <Link
                        v-if="createUrl"
                        :href="createUrl"
                        :class="buttonClasses.primary"
                    >
                        <Plus
                            :size="18"
                            :stroke-width="2.2"
                            aria-hidden="true"
                        />
                        {{ t('content.slides.new') }}
                    </Link>
                </div>

                <div class="grid grid-cols-1 gap-3 rounded-[14px] border border-line bg-white p-4 sm:grid-cols-3">
                    <SelectField
                        v-model="scope"
                        :label="t('content.slides.filters.scope')"
                        :options="scopeOptions"
                        :placeholder="t('content.slides.filters.all_scopes')"
                    />
                    <SelectField
                        v-model="documentType"
                        :label="t('content.slides.filters.document')"
                        :options="documentOptions"
                        :placeholder="t('content.slides.filters.all_documents')"
                    />
                    <SelectField
                        v-model="status"
                        :label="t('content.slides.filters.status')"
                        :options="statusOptions"
                    />
                </div>

                <div class="overflow-hidden rounded-[14px] border border-line bg-white">
                    <EmptyState
                        v-if="filtered.length === 0"
                        :title="t('content.slides.empty_title')"
                        :message="slides.length === 0 ? t('content.slides.empty_message') : t('content.slides.empty_filtered')"
                    >
                        <template #icon>
                            <Images
                                :size="34"
                                :stroke-width="1.6"
                                aria-hidden="true"
                            />
                        </template>
                    </EmptyState>

                    <table
                        v-else
                        class="hidden w-full text-left text-sm md:table"
                    >
                        <thead class="border-b border-line bg-surface text-[13px] text-muted">
                            <tr>
                                <th
                                    scope="col"
                                    class="w-16 px-4 py-3 font-medium"
                                >
                                    {{ t('content.slides.columns.order') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 py-3 font-medium"
                                >
                                    {{ t('content.slides.columns.slide') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 py-3 font-medium"
                                >
                                    {{ t('content.slides.columns.kind') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 py-3 font-medium"
                                >
                                    {{ t('content.slides.columns.document') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 py-3 font-medium"
                                >
                                    {{ t('content.slides.columns.conditions') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 py-3 font-medium"
                                >
                                    {{ t('content.slides.columns.status') }}
                                </th>
                                <th
                                    scope="col"
                                    class="w-12 px-2 py-3"
                                >
                                    <span class="sr-only">{{ t('content.slides.columns.slide') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            v-for="section in sections"
                            :key="section.id"
                        >
                            <tr class="border-b border-line bg-surface/60">
                                <th
                                    colspan="7"
                                    scope="colgroup"
                                    class="px-4 py-2 text-[13px] font-semibold tracking-wide text-ink-soft uppercase"
                                >
                                    {{ section.name }}
                                </th>
                            </tr>
                            <tr
                                v-for="slide in section.slides"
                                :key="slide.id"
                                class="border-b border-line last:border-b-0 hover:bg-surface/60"
                                :class="slide.isActive ? '' : 'text-muted'"
                            >
                                <td class="px-4 py-2 font-mono text-[13px] text-muted">
                                    <span class="inline-flex items-center gap-1">
                                        {{ slide.sortOrder }}
                                        <TriangleAlert
                                            v-if="slide.duplicateOrder"
                                            :size="15"
                                            :stroke-width="2"
                                            class="text-notice"
                                            :aria-label="t('content.slides.duplicate_order')"
                                        />
                                    </span>
                                </td>
                                <td class="px-2 py-2">
                                    <div class="flex items-center gap-3">
                                        <img
                                            v-if="slide.imageUrl"
                                            :src="slide.imageUrl"
                                            :alt="t('content.slides.thumbnail', { slide: slide.title })"
                                            loading="lazy"
                                            class="h-[45px] w-20 shrink-0 rounded border border-line bg-surface object-cover"
                                        >
                                        <span
                                            v-else
                                            class="flex h-[45px] w-20 shrink-0 items-center justify-center rounded border border-dashed border-line-strong text-center text-[11px] leading-tight text-muted"
                                        >{{ t('content.slides.no_image') }}</span>
                                        <div class="min-w-0">
                                            <Link
                                                :href="slide.url"
                                                class="font-semibold text-ink underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                                            >
                                                {{ slide.title }}
                                            </Link>
                                            <div
                                                v-if="slide.clubName"
                                                class="text-[13px] text-muted"
                                            >
                                                {{ slide.clubName }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-2 py-2">
                                    <span
                                        class="rounded-md px-2 py-0.5 text-[12px] font-semibold whitespace-nowrap"
                                        :class="slide.isDynamic ? 'bg-navy/10 text-navy' : 'bg-surface text-ink-soft'"
                                    >{{ slide.kindLabel }}</span>
                                </td>
                                <td class="px-2 py-2 text-[13px]">
                                    {{ slide.documentTypeLabel }}
                                </td>
                                <td class="px-2 py-2">
                                    <div
                                        v-if="slide.conditions.length > 0"
                                        class="flex flex-wrap gap-1.5"
                                    >
                                        <span
                                            v-for="condition in slide.conditions"
                                            :key="condition"
                                            class="rounded-full border border-line-strong px-2 py-0.5 text-[12px]"
                                        >{{ condition }}</span>
                                    </div>
                                    <span
                                        v-else
                                        class="text-[13px] text-muted"
                                    >{{ t('content.slides.always') }}</span>
                                </td>
                                <td class="px-2 py-2">
                                    <StatusPill
                                        :label="slide.isActive ? t('content.slides.active') : t('content.slides.inactive')"
                                        :tone="slide.isActive ? 'success' : 'muted'"
                                    />
                                </td>
                                <td class="px-2 py-2">
                                    <Link
                                        :href="slide.url"
                                        class="flex size-11 items-center justify-center rounded-[10px] text-muted hover:bg-surface hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
                                        :aria-label="t('content.slides.open', { slide: slide.title })"
                                    >
                                        <ChevronRight
                                            :size="18"
                                            :stroke-width="2"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="filtered.length > 0"
                        class="md:hidden"
                    >
                        <section
                            v-for="section in sections"
                            :key="section.id"
                        >
                            <h3 class="border-b border-line bg-surface/60 px-4 py-2 text-[13px] font-semibold text-ink-soft uppercase">
                                {{ section.name }}
                            </h3>
                            <ul class="divide-y divide-line">
                                <li
                                    v-for="slide in section.slides"
                                    :key="slide.id"
                                >
                                    <Link
                                        :href="slide.url"
                                        class="flex items-center gap-3 px-4 py-3 hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                                    >
                                        <img
                                            v-if="slide.imageUrl"
                                            :src="slide.imageUrl"
                                            :alt="t('content.slides.thumbnail', { slide: slide.title })"
                                            loading="lazy"
                                            class="h-[45px] w-20 shrink-0 rounded border border-line bg-surface object-cover"
                                        >
                                        <div class="min-w-0 flex-1">
                                            <div class="font-semibold">
                                                {{ slide.sortOrder }} · {{ slide.title }}
                                            </div>
                                            <div class="text-[13px] text-muted">
                                                {{ slide.kindLabel }} · {{ slide.documentTypeLabel }}<template v-if="slide.clubName">
                                                    · {{ slide.clubName }}
                                                </template>
                                            </div>
                                            <div class="mt-1 text-[13px] text-muted">
                                                {{ slide.conditions.length > 0 ? slide.conditions.join(' · ') : t('content.slides.always') }}
                                            </div>
                                            <StatusPill
                                                v-if="!slide.isActive"
                                                class="mt-1"
                                                :label="t('content.slides.inactive')"
                                                tone="muted"
                                            />
                                        </div>
                                        <ChevronRight
                                            :size="18"
                                            :stroke-width="2"
                                            class="shrink-0 text-muted"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </li>
                            </ul>
                        </section>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
