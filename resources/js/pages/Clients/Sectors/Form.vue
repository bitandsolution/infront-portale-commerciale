<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import { buttonClasses } from '@/components/buttons';
import CheckboxField from '@/components/forms/CheckboxField.vue';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CommoditySectorRow, LayoutData } from '@/types';

/**
 * New commodity sector or page of a sector (M05 F-05; D-357: admin only). Name, order and active;
 * the code comes from the name and never changes; deactivated, never deleted (RB-08).
 */
const props = defineProps<{
    layout: LayoutData;
    sector: CommoditySectorRow | null;
    nextSortOrder: number;
    saveUrl: string;
    indexUrl: string;
}>();

const { t } = useTrans();

const isNew = computed(() => props.sector === null);

const form = useForm({
    name: props.sector?.name ?? '',
    sort_order: String(props.sector?.sortOrder ?? props.nextSortOrder),
    is_active: props.sector?.isActive ?? true,
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
                {{ t('clients.sectors.back') }}
            </Link>

            <form
                class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5 md:p-6"
                novalidate
                @submit.prevent="save"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ isNew ? t('clients.sectors.create_title') : sector?.name }}
                    </h2>
                    <p
                        v-if="sector"
                        class="mt-1 font-mono text-sm text-muted"
                    >
                        {{ sector.code }}
                    </p>
                    <p class="mt-1 text-sm text-muted">
                        {{ t('clients.sectors.code_help') }}
                    </p>
                    <p
                        v-if="sector?.requiresFreeText"
                        class="mt-1 text-sm text-muted"
                    >
                        {{ t('clients.sectors.free_text_help') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_10rem]">
                    <TextField
                        v-model="form.name"
                        :label="t('clients.sectors.fields.name')"
                        autocomplete="off"
                        :error="form.errors.name"
                    />
                    <TextField
                        v-model="form.sort_order"
                        :label="t('clients.sectors.fields.sort_order')"
                        inputmode="numeric"
                        mono
                        :error="form.errors.sort_order"
                    />
                </div>
                <p class="-mt-3 text-[13px] text-muted">
                    {{ t('clients.sectors.fields.sort_order_help') }}
                </p>

                <CheckboxField
                    v-model="form.is_active"
                    :label="t('clients.sectors.fields.is_active')"
                    :help="t('clients.sectors.fields.is_active_help')"
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
                        {{ form.processing ? t('common.saving') : isNew ? t('clients.sectors.create') : t('clients.sectors.save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
