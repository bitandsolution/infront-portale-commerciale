<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { buttonClasses } from '@/components/buttons';
import CheckboxField from '@/components/forms/CheckboxField.vue';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CatalogValueRow, LayoutData } from '@/types';

/**
 * Page of a value of the catalogue (D-331): name, order and, where the kind has them, description
 * and active. The code and the fixed facts (pools consumed, free package) are read-only: they come
 * from the system data (D-332).
 */
const props = defineProps<{
    layout: LayoutData;
    value: CatalogValueRow;
    kindLabel: string;
    hasDescription: boolean;
    hasActiveFlag: boolean;
    saveUrl: string;
    indexUrl: string;
}>();

const { t } = useTrans();

const form = useForm({
    name: props.value.name,
    description: props.value.description ?? '',
    sort_order: String(props.value.sortOrder),
    is_active: props.value.isActive ?? true,
});

function save(): void {
    form.transform((data) => ({
        name: data.name,
        sort_order: data.sort_order,
        ...(props.hasDescription ? { description: data.description } : {}),
        ...(props.hasActiveFlag ? { is_active: data.is_active } : {}),
    })).put(props.saveUrl, { preserveScroll: true });
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
                {{ t('catalog.values.back') }}
            </Link>

            <form
                class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5 md:p-6"
                novalidate
                @submit.prevent="save"
            >
                <div>
                    <p class="text-sm text-muted">
                        {{ kindLabel }}
                    </p>
                    <h2 class="text-lg font-semibold">
                        {{ value.name }}
                    </h2>
                    <p class="mt-1 font-mono text-[13px] text-ink-soft">
                        {{ t('catalog.values.code_label', { code: value.code }) }}
                    </p>
                    <p class="mt-1 text-sm text-muted">
                        {{ t('catalog.values.code_help') }}
                    </p>
                    <ul
                        v-if="value.notes.length > 0"
                        class="mt-2 flex flex-col gap-1 text-sm text-ink-soft"
                    >
                        <li
                            v-for="note in value.notes"
                            :key="note"
                        >
                            {{ note }}
                        </li>
                    </ul>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_10rem]">
                    <TextField
                        v-model="form.name"
                        :label="t('catalog.values.fields.name')"
                        autocomplete="off"
                        :error="form.errors.name"
                    />
                    <TextField
                        v-model="form.sort_order"
                        :label="t('catalog.values.fields.sort_order')"
                        inputmode="numeric"
                        mono
                        :error="form.errors.sort_order"
                    />
                </div>
                <p class="-mt-2 text-sm text-muted">
                    {{ t('catalog.values.fields.sort_order_help') }}
                </p>

                <TextField
                    v-if="hasDescription"
                    v-model="form.description"
                    :label="t('catalog.values.fields.description')"
                    autocomplete="off"
                    :error="form.errors.description"
                />

                <CheckboxField
                    v-if="hasActiveFlag"
                    v-model="form.is_active"
                    :label="t('catalog.values.fields.is_active')"
                    :help="t('catalog.values.fields.is_active_help')"
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
                        {{ form.processing ? t('common.saving') : t('catalog.values.save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
