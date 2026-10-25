<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import { buttonClasses } from '@/components/buttons';
import CheckboxField from '@/components/forms/CheckboxField.vue';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, StadiumRow } from '@/types';

/**
 * New stadium or page of a stadium (M02 F-07; D-137: admin and manager). Deactivated, never
 * deleted (C4).
 */
const props = defineProps<{
    layout: LayoutData;
    stadium: StadiumRow | null;
    saveUrl: string;
    indexUrl: string;
}>();

const { t } = useTrans();

const isNew = computed(() => props.stadium === null);

const form = useForm({
    name: props.stadium?.name ?? '',
    city: props.stadium?.city ?? '',
    capacity: props.stadium?.capacity === null || props.stadium === null ? '' : String(props.stadium.capacity),
    is_active: props.stadium?.isActive ?? true,
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
                {{ t('sports.stadiums.back') }}
            </Link>

            <form
                class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5 md:p-6"
                novalidate
                @submit.prevent="save"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ isNew ? t('sports.stadiums.create_title') : stadium?.name }}
                    </h2>
                    <p
                        v-if="stadium"
                        class="mt-1 text-sm text-muted"
                    >
                        {{ t('sports.stadiums.used_by', { count: String(stadium.clubs) }) }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <TextField
                        v-model="form.name"
                        :label="t('sports.stadiums.fields.name')"
                        autocomplete="off"
                        :error="form.errors.name"
                    />
                    <TextField
                        v-model="form.city"
                        :label="t('sports.stadiums.fields.city')"
                        autocomplete="off"
                        :error="form.errors.city"
                    />
                    <TextField
                        v-model="form.capacity"
                        :label="t('sports.stadiums.fields.capacity')"
                        inputmode="numeric"
                        :error="form.errors.capacity"
                    />
                </div>

                <CheckboxField
                    v-model="form.is_active"
                    :label="t('sports.stadiums.fields.is_active')"
                    :help="t('sports.stadiums.fields.is_active_help')"
                    :error="form.errors.is_active"
                />

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
                        {{ form.processing ? t('common.saving') : isNew ? t('sports.stadiums.create') : t('sports.stadiums.save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
