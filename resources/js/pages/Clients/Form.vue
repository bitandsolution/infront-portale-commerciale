<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Lock } from '@lucide/vue';
import { computed, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import SelectField from '@/components/forms/SelectField.vue';
import TextField from '@/components/forms/TextField.vue';
import NoticeDialog from '@/components/NoticeDialog.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ClientForm, LayoutData, Option, SectorOption } from '@/types';

/**
 * New client or page of a client (M05 F-01, F-03, F-04, §6; D-075, D-078, D-358, D-361).
 * - Company name, e-mail, sector and logo required (logo only for a new client); VAT number and
 *   contact optional; free text required with a sector that asks for it (RB-04).
 * - The admin chooses the owner agent of a new client (D-358).
 * - The manager sees the client read-only (canEdit false).
 * - A duplicate opens a blocking dialog (ux-architettura §4.5); the data stay in the form.
 * Data and logo travel together in one multipart request (PUT spoofed as POST for a change).
 */
const props = defineProps<{
    layout: LayoutData;
    client: ClientForm | null;
    canEdit: boolean;
    sectors: SectorOption[];
    owners: Option[] | null;
    saveUrl: string | null;
    indexUrl: string;
    scope: 'mine' | 'all';
}>();

const { t } = useTrans();

const isNew = computed(() => props.client === null);
const backLabel = computed(() => t(`clients.form.back_${props.scope}`));

const form = useForm<{
    _method: 'post' | 'put';
    company_name: string;
    vat_number: string;
    email: string;
    contact_name: string;
    commodity_sector_id: string;
    sector_free_text: string;
    owner_id: string;
    logo: File | null;
}>({
    _method: props.client === null ? 'post' : 'put',
    company_name: props.client?.companyName ?? '',
    vat_number: props.client?.vatNumber ?? '',
    email: props.client?.email ?? '',
    contact_name: props.client?.contactName ?? '',
    commodity_sector_id: props.client === null ? '' : String(props.client.sectorId),
    sector_free_text: props.client?.sectorFreeText ?? '',
    owner_id: '',
    logo: null,
});

const needsFreeText = computed(() => props.sectors.find((sector) => sector.value === form.commodity_sector_id)?.requiresFreeText === true);

const logoInput = ref<HTMLInputElement | null>(null);

function pickLogo(event: Event): void {
    form.logo = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function save(): void {
    if (props.saveUrl === null) {
        return;
    }

    form.post(props.saveUrl, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            if (!isNew.value) {
                form.logo = null;
                if (logoInput.value) {
                    logoInput.value.value = '';
                }
            }
        },
    });
}

// Duplicate (RB-02): a blocking dialog, closed by the user; the form keeps what was typed.
// The error arrives in its own key, outside the fields of the form.
const duplicateMessage = computed(() => (form.errors as Record<string, string | undefined>).duplicate);

function closeDuplicate(): void {
    form.errors = Object.fromEntries(Object.entries(form.errors).filter(([key]) => key !== 'duplicate')) as typeof form.errors;
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
                {{ backLabel }}
            </Link>

            <form
                class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5 md:p-6"
                novalidate
                @submit.prevent="save"
            >
                <div class="flex flex-wrap items-start gap-4">
                    <img
                        v-if="client"
                        :src="client.logoUrl"
                        :alt="t('clients.form.logo.current', { client: client.companyName })"
                        class="size-20 shrink-0 rounded-lg border border-line bg-white object-contain p-1"
                    >
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-semibold">
                            {{ isNew ? t('clients.form.create_title') : client?.companyName }}
                        </h2>
                        <div
                            v-if="client"
                            class="mt-1.5 flex flex-wrap items-center gap-2 text-sm text-muted"
                        >
                            <StatusPill
                                :label="client.isActive ? t('clients.active') : t('clients.inactive')"
                                :tone="client.isActive ? 'success' : 'muted'"
                            />
                            <span>{{ t('clients.form.owner', { owner: client.owner }) }}</span>
                        </div>
                        <p
                            v-if="canEdit"
                            class="mt-2 text-sm leading-relaxed text-muted"
                        >
                            {{ t('clients.form.duplicate_help') }}
                        </p>
                    </div>
                </div>

                <p
                    v-if="!canEdit"
                    class="flex items-start gap-2 rounded-[10px] bg-surface px-3.5 py-3 text-sm text-ink-soft"
                >
                    <Lock
                        :size="16"
                        :stroke-width="2"
                        class="mt-0.5 shrink-0"
                        aria-hidden="true"
                    />
                    {{ t('clients.form.read_only') }}
                </p>

                <SelectField
                    v-if="owners !== null"
                    v-model="form.owner_id"
                    :label="t('clients.form.fields.owner')"
                    :options="owners"
                    :placeholder="t('clients.form.fields.owner_placeholder')"
                    :error="form.errors.owner_id"
                />
                <p
                    v-if="owners !== null"
                    class="-mt-3 text-[13px] text-muted"
                >
                    {{ t('clients.form.fields.owner_help') }}
                </p>

                <TextField
                    v-model="form.company_name"
                    :label="t('clients.form.fields.company_name')"
                    autocomplete="organization"
                    :disabled="!canEdit"
                    :error="form.errors.company_name"
                />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <TextField
                            v-model="form.vat_number"
                            :label="t('clients.form.fields.vat_number')"
                            inputmode="numeric"
                            autocomplete="off"
                            mono
                            :disabled="!canEdit"
                            :error="form.errors.vat_number"
                        />
                        <p class="text-[13px] text-muted">
                            {{ t('clients.form.fields.vat_number_help') }}
                        </p>
                    </div>
                    <TextField
                        v-model="form.email"
                        :label="t('clients.form.fields.email')"
                        type="email"
                        inputmode="email"
                        autocomplete="email"
                        :disabled="!canEdit"
                        :error="form.errors.email"
                    />
                </div>

                <TextField
                    v-model="form.contact_name"
                    :label="t('clients.form.fields.contact_name')"
                    autocomplete="name"
                    :disabled="!canEdit"
                    :error="form.errors.contact_name"
                />

                <SelectField
                    v-model="form.commodity_sector_id"
                    :label="t('clients.form.fields.sector')"
                    :options="sectors"
                    :placeholder="t('clients.form.fields.sector_placeholder')"
                    :disabled="!canEdit"
                    :error="form.errors.commodity_sector_id"
                />

                <div
                    v-if="needsFreeText"
                    class="flex flex-col gap-1.5"
                >
                    <TextField
                        v-model="form.sector_free_text"
                        :label="t('clients.form.fields.sector_free_text')"
                        autocomplete="off"
                        :disabled="!canEdit"
                        :error="form.errors.sector_free_text"
                    />
                    <p class="text-[13px] text-muted">
                        {{ t('clients.form.fields.sector_free_text_help') }}
                    </p>
                </div>

                <section
                    v-if="canEdit"
                    class="flex flex-col gap-2 border-t border-line pt-5"
                    aria-labelledby="client-logo"
                >
                    <h3
                        id="client-logo"
                        class="text-base font-semibold"
                    >
                        {{ t('clients.form.logo.title') }}
                    </h3>
                    <p class="text-sm leading-relaxed text-muted">
                        {{ t('clients.form.logo.intro') }}
                    </p>
                    <label
                        for="client-logo-file"
                        class="mt-1 text-sm font-medium text-ink"
                    >{{ isNew ? t('clients.form.logo.choose') : t('clients.form.logo.replace') }}</label>
                    <input
                        id="client-logo-file"
                        ref="logoInput"
                        type="file"
                        accept="image/png,image/jpeg"
                        class="min-h-11 text-sm file:mr-3 file:min-h-11 file:rounded-[10px] file:border file:border-line-strong file:bg-white file:px-3.5 file:text-sm file:font-medium"
                        :aria-invalid="form.errors.logo ? 'true' : undefined"
                        @change="pickLogo"
                    >
                    <p
                        v-if="form.errors.logo"
                        class="text-[13px] leading-snug text-accent"
                    >
                        {{ form.errors.logo }}
                    </p>
                    <p
                        v-if="!isNew"
                        class="text-[13px] text-muted"
                    >
                        {{ t('clients.form.logo.replace_help') }}
                    </p>
                </section>

                <div
                    v-if="canEdit"
                    class="flex flex-col-reverse gap-2 border-t border-line pt-5 sm:flex-row sm:justify-end"
                >
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
                        {{ form.processing ? t('common.saving') : isNew ? t('clients.form.create') : t('clients.form.save') }}
                    </button>
                </div>
            </form>
        </div>

        <NoticeDialog
            :open="duplicateMessage !== undefined"
            :title="t('clients.form.duplicate_title')"
            :close-label="t('clients.form.duplicate_close')"
            @close="closeDuplicate"
        >
            <p>{{ duplicateMessage }}</p>
        </NoticeDialog>
    </AppLayout>
</template>
