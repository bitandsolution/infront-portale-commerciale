<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Lock, Shield, Trash2, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import CheckboxField from '@/components/forms/CheckboxField.vue';
import SelectField from '@/components/forms/SelectField.vue';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ClubAliasRow, ClubRow, LayoutData, Option } from '@/types';

/**
 * New club or page of a club (M02 F-05, F-11; D-137: admin only): data and flags, then logo and
 * aliases. The code is fixed after the creation (C3); a club is deactivated, never deleted (C4).
 */
const props = defineProps<{
    layout: LayoutData;
    club: ClubRow | null;
    stadiums: Option[];
    saveUrl: string;
    indexUrl: string;
    logoUrl: string | null;
    aliases: ClubAliasRow[];
    addAliasUrl: string | null;
}>();

const { t } = useTrans();

const isNew = computed(() => props.club === null);

const form = useForm({
    code: props.club?.code ?? '',
    name: props.club?.name ?? '',
    short_name: props.club?.shortName ?? '',
    home_stadium_id: props.club?.homeStadiumId === null || props.club === null ? '' : String(props.club.homeStadiumId),
    primary_color: props.club?.primaryColor ?? '',
    secondary_color: props.club?.secondaryColor ?? '',
    is_infront: props.club?.isInfront ?? false,
    is_top_match_opponent: props.club?.isTopMatchOpponent ?? false,
    is_active: props.club?.isActive ?? true,
});

function save(): void {
    if (isNew.value) {
        form.post(props.saveUrl);
    } else {
        form.put(props.saveUrl, { preserveScroll: true });
    }
}

function swatch(color: string): string | undefined {
    return /^#[0-9A-Fa-f]{6}$/.test(color) ? color : undefined;
}

// Logo: multipart upload of a single file.
const logoForm = useForm<{ logo: File | null }>({ logo: null });
const logoInput = ref<HTMLInputElement | null>(null);

function pickLogo(event: Event): void {
    logoForm.logo = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function uploadLogo(): void {
    if (props.logoUrl !== null && logoForm.logo !== null) {
        logoForm.post(props.logoUrl, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                logoForm.reset();
                if (logoInput.value) {
                    logoInput.value.value = '';
                }
            },
        });
    }
}

const aliasForm = useForm({ alias: '' });

function addAlias(): void {
    if (props.addAliasUrl !== null) {
        aliasForm.post(props.addAliasUrl, {
            preserveScroll: true,
            onSuccess: () => aliasForm.reset(),
        });
    }
}

const toRemove = ref<ClubAliasRow | null>(null);
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
                {{ t('sports.clubs.back') }}
            </Link>

            <form
                class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5 md:p-6"
                novalidate
                @submit.prevent="save"
            >
                <h2 class="text-lg font-semibold">
                    {{ isNew ? t('sports.clubs.create_title') : club?.name }}
                </h2>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <TextField
                        v-if="isNew"
                        v-model="form.code"
                        :label="t('sports.clubs.fields.code')"
                        autocomplete="off"
                        :error="form.errors.code"
                    />
                    <div
                        v-else
                        class="flex flex-col gap-1.5"
                    >
                        <span class="text-sm font-medium text-ink">{{ t('sports.clubs.fields.code_fixed') }}</span>
                        <span class="flex min-h-11 items-center gap-2 font-mono text-[15px]">
                            <Lock
                                :size="16"
                                :stroke-width="2"
                                class="text-muted"
                                aria-hidden="true"
                            />
                            {{ club?.code }}
                        </span>
                        <span class="text-[13px] leading-snug text-muted">{{ t('sports.clubs.fields.code_help') }}</span>
                    </div>
                    <TextField
                        v-model="form.name"
                        :label="t('sports.clubs.fields.name')"
                        autocomplete="off"
                        :error="form.errors.name"
                    />
                    <TextField
                        v-model="form.short_name"
                        :label="t('sports.clubs.fields.short_name')"
                        autocomplete="off"
                        :error="form.errors.short_name"
                    />
                    <SelectField
                        v-model="form.home_stadium_id"
                        :label="t('sports.clubs.fields.home_stadium')"
                        :options="stadiums"
                        :placeholder="t('sports.clubs.fields.no_stadium')"
                        :error="form.errors.home_stadium_id"
                    />
                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <TextField
                                v-model="form.primary_color"
                                :label="t('sports.clubs.fields.primary_color')"
                                autocomplete="off"
                                :error="form.errors.primary_color"
                            />
                        </div>
                        <span
                            class="mb-0.5 size-11 shrink-0 rounded-[10px] border border-line"
                            :style="{ backgroundColor: swatch(form.primary_color) }"
                            role="img"
                            :aria-label="t('sports.clubs.fields.color_preview')"
                        />
                    </div>
                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <TextField
                                v-model="form.secondary_color"
                                :label="t('sports.clubs.fields.secondary_color')"
                                autocomplete="off"
                                :error="form.errors.secondary_color"
                            />
                        </div>
                        <span
                            class="mb-0.5 size-11 shrink-0 rounded-[10px] border border-line"
                            :style="{ backgroundColor: swatch(form.secondary_color) }"
                            role="img"
                            :aria-label="t('sports.clubs.fields.color_preview')"
                        />
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <CheckboxField
                        v-model="form.is_infront"
                        :label="t('sports.clubs.fields.is_infront')"
                        :help="t('sports.clubs.fields.is_infront_help')"
                        :error="form.errors.is_infront"
                    />
                    <CheckboxField
                        v-model="form.is_top_match_opponent"
                        :label="t('sports.clubs.fields.is_top_match_opponent')"
                        :help="t('sports.clubs.fields.is_top_match_opponent_help')"
                        :error="form.errors.is_top_match_opponent"
                    />
                    <CheckboxField
                        v-model="form.is_active"
                        :label="t('sports.clubs.fields.is_active')"
                        :help="t('sports.clubs.fields.is_active_help')"
                        :error="form.errors.is_active"
                    />
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
                        {{ form.processing ? t('common.saving') : isNew ? t('sports.clubs.create') : t('sports.clubs.save') }}
                    </button>
                </div>
            </form>

            <p
                v-if="isNew"
                class="text-sm text-muted"
            >
                {{ t('sports.clubs.save_first') }}
            </p>

            <template v-else>
                <section
                    class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5 md:p-6"
                    aria-labelledby="club-logo"
                >
                    <div>
                        <h2
                            id="club-logo"
                            class="text-lg font-semibold"
                        >
                            {{ t('sports.clubs.logo.title') }}
                        </h2>
                        <p class="mt-1 text-sm leading-relaxed text-muted">
                            {{ t('sports.clubs.logo.intro') }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="flex size-28 shrink-0 items-center justify-center overflow-hidden rounded-[10px] border border-line bg-white p-2">
                            <img
                                v-if="club?.logoUrl"
                                :src="club.logoUrl"
                                :alt="t('sports.clubs.logo.current', { club: club.name })"
                                class="max-h-full max-w-full object-contain"
                            >
                            <Shield
                                v-else
                                :size="36"
                                :stroke-width="1.4"
                                class="text-muted"
                                :aria-label="t('sports.clubs.logo.none')"
                            />
                        </div>
                        <form
                            class="flex flex-1 flex-col gap-2"
                            novalidate
                            @submit.prevent="uploadLogo"
                        >
                            <label
                                for="club-logo-file"
                                class="text-sm font-medium text-ink"
                            >{{ t('sports.clubs.logo.choose') }}</label>
                            <input
                                id="club-logo-file"
                                ref="logoInput"
                                type="file"
                                accept="image/png,image/jpeg"
                                class="min-h-11 text-sm file:mr-3 file:min-h-11 file:rounded-[10px] file:border file:border-line-strong file:bg-white file:px-4 file:font-semibold file:text-ink"
                                :aria-invalid="logoForm.errors.logo ? 'true' : undefined"
                                @change="pickLogo"
                            >
                            <p
                                v-if="logoForm.errors.logo"
                                class="text-[13px] leading-snug text-accent"
                            >
                                {{ logoForm.errors.logo }}
                            </p>
                            <button
                                type="submit"
                                :class="buttonClasses.secondary"
                                class="self-start"
                                :disabled="logoForm.processing || logoForm.logo === null"
                            >
                                <Upload
                                    :size="18"
                                    :stroke-width="2"
                                    aria-hidden="true"
                                />
                                {{ logoForm.processing ? t('sports.clubs.logo.uploading') : t('sports.clubs.logo.upload') }}
                            </button>
                        </form>
                    </div>
                </section>

                <section
                    class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5 md:p-6"
                    aria-labelledby="club-aliases"
                >
                    <div>
                        <h2
                            id="club-aliases"
                            class="text-lg font-semibold"
                        >
                            {{ t('sports.clubs.aliases.title') }}
                        </h2>
                        <p class="mt-1 text-sm leading-relaxed text-muted">
                            {{ t('sports.clubs.aliases.intro') }}
                        </p>
                    </div>

                    <p
                        v-if="aliases.length === 0"
                        class="text-sm text-muted"
                    >
                        {{ t('sports.clubs.aliases.empty') }}
                    </p>
                    <ul
                        v-else
                        class="divide-y divide-line rounded-[10px] border border-line"
                    >
                        <li
                            v-for="alias in aliases"
                            :key="alias.id"
                            class="flex items-center gap-3 px-4 py-2"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="font-medium">
                                    {{ alias.alias }}
                                </div>
                                <div class="text-[13px] text-muted">
                                    {{ alias.sourceLabel }}
                                </div>
                            </div>
                            <button
                                type="button"
                                class="flex size-11 items-center justify-center rounded-[10px] text-accent hover:bg-accent-soft focus-visible:outline-2 focus-visible:outline-accent"
                                :aria-label="t('sports.clubs.aliases.remove_label', { alias: alias.alias })"
                                @click="toRemove = alias"
                            >
                                <Trash2
                                    :size="18"
                                    :stroke-width="2"
                                    aria-hidden="true"
                                />
                            </button>
                        </li>
                    </ul>

                    <form
                        class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
                        novalidate
                        @submit.prevent="addAlias"
                    >
                        <TextField
                            v-model="aliasForm.alias"
                            :label="t('sports.clubs.aliases.alias')"
                            autocomplete="off"
                            :error="aliasForm.errors.alias"
                        />
                        <button
                            type="submit"
                            :class="buttonClasses.primary"
                            :disabled="aliasForm.processing"
                        >
                            {{ t('sports.clubs.aliases.add') }}
                        </button>
                    </form>
                </section>
            </template>
        </div>

        <ConfirmDialog
            :open="toRemove !== null"
            :title="t('sports.clubs.aliases.confirm_remove_title', { alias: toRemove?.alias ?? '' })"
            :confirm-label="t('sports.clubs.aliases.remove')"
            :processing="removeForm.processing"
            @confirm="confirmRemove"
            @cancel="toRemove = null"
        >
            <p>{{ t('sports.clubs.aliases.confirm_remove') }}</p>
        </ConfirmDialog>
    </AppLayout>
</template>
