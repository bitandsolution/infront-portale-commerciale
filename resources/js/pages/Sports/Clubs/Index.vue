<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Plus, Search, Shield } from '@lucide/vue';
import { computed, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import SelectField from '@/components/forms/SelectField.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ClubRow, LayoutData, Option } from '@/types';

/**
 * Clubs page (M02 F-05; D-137: admin only): Infront clubs and opponents. About 90 clubs: the list
 * arrives from the server and the filters work in the browser.
 */
const props = defineProps<{
    layout: LayoutData;
    clubs: ClubRow[];
    createUrl: string;
}>();

const { t } = useTrans();

const search = ref('');
const kind = ref('');

const kindOptions = computed<Option[]>(() =>
    (['infront', 'opponents', 'inactive'] as const).map((value) => ({ value, label: t(`sports.clubs.filters.${value}`) })),
);

const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('it');

    return props.clubs.filter((club) => {
        const matchesKind =
            kind.value === '' ||
            (kind.value === 'infront' && club.isInfront && club.isActive) ||
            (kind.value === 'opponents' && !club.isInfront && club.isActive) ||
            (kind.value === 'inactive' && !club.isActive);
        const matchesTerm =
            term === '' ||
            club.name.toLocaleLowerCase('it').includes(term) ||
            club.shortName.toLocaleLowerCase('it').includes(term) ||
            club.code.toLocaleLowerCase('it').includes(term);

        return matchesKind && matchesTerm;
    });
});
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-muted">
                        {{ t('sports.clubs.count', { count: String(filtered.length) }) }}
                    </p>
                    <p class="mt-1 max-w-2xl text-sm leading-relaxed text-muted">
                        {{ t('sports.clubs.intro') }}
                    </p>
                </div>
                <Link
                    :href="createUrl"
                    :class="buttonClasses.primary"
                >
                    <Plus
                        :size="18"
                        :stroke-width="2.2"
                        aria-hidden="true"
                    />
                    {{ t('sports.clubs.new') }}
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-3 rounded-[14px] border border-line bg-white p-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div class="flex flex-col gap-1.5">
                    <label
                        for="clubs-search"
                        class="text-sm font-medium text-ink"
                    >{{ t('sports.clubs.search') }}</label>
                    <div class="relative">
                        <Search
                            :size="18"
                            :stroke-width="2"
                            class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted"
                            aria-hidden="true"
                        />
                        <input
                            id="clubs-search"
                            v-model="search"
                            type="search"
                            autocomplete="off"
                            class="min-h-11 w-full rounded-[10px] border border-line-strong bg-white pr-3.5 pl-10 text-[15px] text-ink outline-none focus:border-accent focus:ring-2 focus:ring-accent/20"
                        >
                    </div>
                </div>
                <SelectField
                    v-model="kind"
                    :label="t('sports.clubs.filter')"
                    :options="kindOptions"
                    :placeholder="t('sports.clubs.filters.all')"
                />
            </div>

            <div class="overflow-hidden rounded-[14px] border border-line bg-white">
                <EmptyState
                    v-if="filtered.length === 0"
                    :title="t('sports.clubs.empty_title')"
                    :message="t('sports.clubs.empty_message')"
                >
                    <template #icon>
                        <Shield
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
                        v-for="club in filtered"
                        :key="club.id"
                    >
                        <Link
                            :href="club.url"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                            :aria-label="t('sports.clubs.open', { club: club.name })"
                        >
                            <span class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-[10px] border border-line bg-white">
                                <img
                                    v-if="club.logoUrl"
                                    :src="club.logoUrl"
                                    alt=""
                                    class="max-h-full max-w-full object-contain"
                                >
                                <Shield
                                    v-else
                                    :size="20"
                                    :stroke-width="1.6"
                                    class="text-muted"
                                    aria-hidden="true"
                                />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold">
                                    {{ club.name }}
                                </div>
                                <div class="text-[13px] text-muted">
                                    <span class="font-mono">{{ club.code }}</span>
                                    <span v-if="club.homeStadium"> · {{ club.homeStadium }}</span>
                                </div>
                            </div>
                            <div class="hidden flex-wrap justify-end gap-1.5 sm:flex">
                                <StatusPill
                                    :label="club.isInfront ? t('sports.clubs.infront') : t('sports.clubs.opponent')"
                                    :tone="club.isInfront ? 'success' : 'muted'"
                                />
                                <StatusPill
                                    v-if="club.isTopMatchOpponent"
                                    :label="t('sports.clubs.top_match')"
                                    tone="notice"
                                />
                                <StatusPill
                                    v-if="!club.isActive"
                                    :label="t('sports.inactive')"
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
            </div>
        </div>
    </AppLayout>
</template>
