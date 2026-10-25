<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Lock } from '@lucide/vue';
import { computed } from 'vue';
import { buttonClasses } from '@/components/buttons';
import CheckboxField from '@/components/forms/CheckboxField.vue';
import SelectField from '@/components/forms/SelectField.vue';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CompetitionRow, LayoutData, Option } from '@/types';

/**
 * New competition or page of a competition (M02 F-02; D-137: admin only). The code is fixed after
 * the creation (C3); a competition is deactivated, never deleted (C4).
 */
const props = defineProps<{
    layout: LayoutData;
    competition: CompetitionRow | null;
    types: Option[];
    saveUrl: string;
    indexUrl: string;
}>();

const { t } = useTrans();

const isNew = computed(() => props.competition === null);

const form = useForm({
    code: props.competition?.code ?? '',
    name: props.competition?.name ?? '',
    short_name: props.competition?.shortName ?? '',
    type: props.competition?.type ?? 'league',
    tier: String(props.competition?.tier ?? 3),
    has_top_match_kpi: props.competition?.hasTopMatchKpi ?? false,
    is_active: props.competition?.isActive ?? true,
    sort_order: String(props.competition?.sortOrder ?? 100),
});

function save(): void {
    if (isNew.value) {
        form.post(props.saveUrl);
    } else {
        form.put(props.saveUrl, { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-3xl flex-col gap-5 p-4 md:p-6">
            <Link
                :href="indexUrl"
                class="inline-flex min-h-11 items-center gap-2 self-start text-sm font-medium text-ink-soft hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
            >
                <ArrowLeft
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('sports.competitions.back') }}
            </Link>

            <form
                class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5 md:p-6"
                novalidate
                @submit.prevent="save"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ isNew ? t('sports.competitions.create_title') : t('sports.competitions.edit_title', { competition: competition?.name ?? '' }) }}
                    </h2>
                    <p
                        v-if="competition"
                        class="mt-1 text-sm text-muted"
                    >
                        {{ t('sports.competitions.used_in', { count: String(competition.seasons) }) }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <TextField
                        v-if="isNew"
                        v-model="form.code"
                        :label="t('sports.competitions.fields.code')"
                        autocomplete="off"
                        :error="form.errors.code"
                    />
                    <div
                        v-else
                        class="flex flex-col gap-1.5"
                    >
                        <span class="text-sm font-medium text-ink">{{ t('sports.competitions.fields.code_fixed') }}</span>
                        <span class="flex min-h-11 items-center gap-2 font-mono text-[15px]">
                            <Lock
                                :size="16"
                                :stroke-width="2"
                                class="text-muted"
                                aria-hidden="true"
                            />
                            {{ competition?.code }}
                        </span>
                        <span class="text-[13px] leading-snug text-muted">{{ t('sports.competitions.fields.code_help') }}</span>
                    </div>
                    <TextField
                        v-model="form.name"
                        :label="t('sports.competitions.fields.name')"
                        autocomplete="off"
                        :error="form.errors.name"
                    />
                    <TextField
                        v-model="form.short_name"
                        :label="t('sports.competitions.fields.short_name')"
                        autocomplete="off"
                        :error="form.errors.short_name"
                    />
                    <SelectField
                        v-model="form.type"
                        :label="t('sports.competitions.fields.type')"
                        :options="types"
                        :error="form.errors.type"
                    />
                    <TextField
                        v-model="form.tier"
                        :label="t('sports.competitions.fields.tier')"
                        inputmode="numeric"
                        :error="form.errors.tier"
                    />
                    <TextField
                        v-model="form.sort_order"
                        :label="t('sports.competitions.fields.sort_order')"
                        inputmode="numeric"
                        :error="form.errors.sort_order"
                    />
                </div>

                <div class="flex flex-col gap-2">
                    <CheckboxField
                        v-model="form.has_top_match_kpi"
                        :label="t('sports.competitions.fields.has_top_match_kpi')"
                        :help="t('sports.competitions.fields.has_top_match_kpi_help')"
                        :error="form.errors.has_top_match_kpi"
                    />
                    <CheckboxField
                        v-model="form.is_active"
                        :label="t('sports.competitions.fields.is_active')"
                        :help="t('sports.competitions.fields.is_active_help')"
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
                        {{ form.processing ? t('common.saving') : isNew ? t('sports.competitions.create') : t('sports.competitions.save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
