<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Lock, Search, Shield, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, Option } from '@/types';

/**
 * Clubs enrolled in an edition (M02 F-06; D-137, D-321: admin only). One league per club and season
 * is checked by the server; a closed season is read-only (D-317).
 */
interface EnrollmentRow {
    id: number;
    clubName: string;
    clubCode: string;
    isInfront: boolean;
    logoUrl: string | null;
    destroyUrl: string;
}

const props = defineProps<{
    layout: LayoutData;
    edition: { id: number; label: string; standardHomeMatches: number; readOnly: boolean };
    enrollments: EnrollmentRow[];
    availableClubs: Option[];
    enrollUrl: string;
    seasonUrl: string;
}>();

const { t } = useTrans();

const search = ref('');
const form = useForm<{ club_ids: string[] }>({ club_ids: [] });

const visibleClubs = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('it');

    return props.availableClubs.filter((club) => term === '' || club.label.toLocaleLowerCase('it').includes(term));
});

function enroll(): void {
    form.post(props.enrollUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

const toRemove = ref<EnrollmentRow | null>(null);
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
                :href="seasonUrl"
                class="inline-flex min-h-11 items-center gap-2 self-start text-sm font-medium text-ink-soft hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
            >
                <ArrowLeft
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('sports.enrollments.back') }}
            </Link>

            <section class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5 md:p-6">
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ edition.label }}
                    </h2>
                    <p class="mt-1 text-sm leading-relaxed text-muted">
                        {{ t('sports.enrollments.intro') }}
                    </p>
                    <p class="mt-1 text-sm text-muted">
                        {{ t('sports.enrollments.standard', { count: String(edition.standardHomeMatches) }) }} ·
                        {{ t('sports.enrollments.count', { count: String(enrollments.length) }) }}
                    </p>
                </div>

                <p
                    v-if="edition.readOnly"
                    class="flex items-start gap-2 rounded-[10px] bg-surface px-4 py-3 text-sm text-ink-soft"
                >
                    <Lock
                        :size="18"
                        :stroke-width="2"
                        class="mt-0.5 shrink-0"
                        aria-hidden="true"
                    />
                    {{ t('sports.enrollments.closed_note') }}
                </p>

                <p
                    v-if="enrollments.length === 0"
                    class="text-sm text-muted"
                >
                    {{ t('sports.enrollments.empty') }}
                </p>
                <ul
                    v-else
                    class="grid grid-cols-1 gap-2 sm:grid-cols-2"
                >
                    <li
                        v-for="enrollment in enrollments"
                        :key="enrollment.id"
                        class="flex items-center gap-3 rounded-[10px] border border-line px-3 py-2"
                    >
                        <span class="flex size-10 shrink-0 items-center justify-center overflow-hidden">
                            <img
                                v-if="enrollment.logoUrl"
                                :src="enrollment.logoUrl"
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
                            <div class="truncate font-medium">
                                {{ enrollment.clubName }}
                            </div>
                            <div class="font-mono text-[13px] text-muted">
                                {{ enrollment.clubCode }}
                            </div>
                        </div>
                        <StatusPill
                            v-if="enrollment.isInfront"
                            :label="t('sports.enrollments.infront')"
                            tone="success"
                        />
                        <button
                            v-if="!edition.readOnly"
                            type="button"
                            class="flex size-11 shrink-0 items-center justify-center rounded-[10px] text-accent hover:bg-accent-soft focus-visible:outline-2 focus-visible:outline-accent"
                            :aria-label="t('sports.enrollments.remove_label', { club: enrollment.clubName })"
                            @click="toRemove = enrollment"
                        >
                            <Trash2
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                        </button>
                    </li>
                </ul>
            </section>

            <form
                v-if="!edition.readOnly"
                class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5 md:p-6"
                novalidate
                @submit.prevent="enroll"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ t('sports.enrollments.add_title') }}
                    </h2>
                </div>
                <p
                    v-if="availableClubs.length === 0"
                    class="text-sm text-muted"
                >
                    {{ t('sports.enrollments.none_available') }}
                </p>
                <template v-else>
                    <div class="relative">
                        <Search
                            :size="18"
                            :stroke-width="2"
                            class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted"
                            aria-hidden="true"
                        />
                        <input
                            v-model="search"
                            type="search"
                            autocomplete="off"
                            :aria-label="t('sports.enrollments.clubs')"
                            class="min-h-11 w-full rounded-[10px] border border-line-strong bg-white pr-3.5 pl-10 text-[15px] text-ink outline-none focus:border-accent focus:ring-2 focus:ring-accent/20"
                        >
                    </div>
                    <fieldset class="max-h-80 overflow-y-auto rounded-[10px] border border-line p-2">
                        <legend class="sr-only">
                            {{ t('sports.enrollments.clubs') }}
                        </legend>
                        <label
                            v-for="club in visibleClubs"
                            :key="club.value"
                            class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 hover:bg-surface"
                        >
                            <input
                                v-model="form.club_ids"
                                type="checkbox"
                                :value="club.value"
                                class="size-5 shrink-0 accent-accent"
                            >
                            <span class="text-[15px]">{{ club.label }}</span>
                        </label>
                    </fieldset>
                    <p
                        v-if="form.errors.club_ids"
                        class="text-[13px] text-accent"
                    >
                        {{ form.errors.club_ids }}
                    </p>
                    <button
                        type="submit"
                        :class="buttonClasses.primary"
                        class="self-start"
                        :disabled="form.processing || form.club_ids.length === 0"
                    >
                        {{ t('sports.enrollments.add') }} ({{ form.club_ids.length }})
                    </button>
                </template>
            </form>
        </div>

        <ConfirmDialog
            :open="toRemove !== null"
            :title="t('sports.enrollments.confirm_remove_title', { club: toRemove?.clubName ?? '' })"
            :confirm-label="t('sports.enrollments.remove')"
            :processing="removeForm.processing"
            @confirm="confirmRemove"
            @cancel="toRemove = null"
        >
            <p>{{ t('sports.enrollments.confirm_remove') }}</p>
        </ConfirmDialog>
    </AppLayout>
</template>
