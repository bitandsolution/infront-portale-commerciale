<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { buttonClasses } from '@/components/buttons';
import SelectField from '@/components/forms/SelectField.vue';
import TextField from '@/components/forms/TextField.vue';
import NoticeDialog from '@/components/NoticeDialog.vue';
import { useTrans } from '@/composables/useTrans';
import type { SectorOption } from '@/types';

/**
 * New client created from step 1 of the configurator (M05 F-02, FL-02): the fields and rules of the
 * client form (company name, e-mail, sector, logo; VAT number and contact optional; free text with a
 * sector that asks for it). The server checks duplicates on the whole registry: a duplicate opens the
 * blocking dialog with the neutral message (FL-02 step 4) and the data stay in the form. On a draft
 * the page sends its lock version (D-216).
 */
const props = defineProps<{
    open: boolean;
    url: string;
    sectors: SectorOption[];
    lockVersion: number | null;
}>();

const emit = defineEmits<{ close: []; created: [] }>();

const { t } = useTrans();

const form = useForm<{
    company_name: string;
    vat_number: string;
    email: string;
    contact_name: string;
    commodity_sector_id: string;
    sector_free_text: string;
    logo: File | null;
    lock_version: number | null;
}>({
    company_name: '',
    vat_number: '',
    email: '',
    contact_name: '',
    commodity_sector_id: '',
    sector_free_text: '',
    logo: null,
    lock_version: null,
});

const needsFreeText = computed(() => props.sectors.find((sector) => sector.value === form.commodity_sector_id)?.requiresFreeText === true);
const duplicateMessage = computed(() => (form.errors as Record<string, string | undefined>).duplicate);
const logoInput = ref<HTMLInputElement | null>(null);
const title = ref<HTMLHeadingElement | null>(null);
let opener: HTMLElement | null = null;

function pickLogo(event: Event): void {
    form.logo = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function close(): void {
    if (!form.processing) {
        emit('close');
    }
}

function submit(): void {
    form.lock_version = props.lockVersion;
    // On success the page comes back with the new client chosen (a new draft, or the draft saved with
    // it); on errors the form keeps what was typed.
    form.post(props.url, {
        preserveScroll: true,
        preserveState: 'errors',
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            if (logoInput.value) {
                logoInput.value.value = '';
            }
            emit('created');
        },
    });
}

function closeDuplicate(): void {
    form.errors = Object.fromEntries(Object.entries(form.errors).filter(([key]) => key !== 'duplicate')) as typeof form.errors;
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && duplicateMessage.value === undefined) {
        close();
    }
}

watch(
    () => props.open,
    async (open) => {
        if (open) {
            opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            document.addEventListener('keydown', onKeydown);
            await nextTick();
            title.value?.focus();
        } else {
            document.removeEventListener('keydown', onKeydown);
            form.clearErrors();
            opener?.focus();
            opener = null;
        }
    },
);

onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
    >
        <div
            class="absolute inset-0 bg-ink/45"
            aria-hidden="true"
            @click="close"
        />
        <form
            role="dialog"
            aria-modal="true"
            aria-labelledby="new-client-title"
            class="relative flex max-h-[calc(100dvh-2rem)] w-full max-w-xl flex-col rounded-[14px] bg-white shadow-xl"
            novalidate
            @submit.prevent="submit"
        >
            <div class="border-b border-line px-6 pt-6 pb-4">
                <h2
                    id="new-client-title"
                    ref="title"
                    tabindex="-1"
                    class="text-lg font-semibold outline-none"
                >
                    {{ t('proposals.client.dialog_title') }}
                </h2>
                <p class="mt-1 text-sm leading-relaxed text-muted">
                    {{ t('proposals.client.dialog_intro') }}
                </p>
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-6 py-5">
                <TextField
                    v-model="form.company_name"
                    :label="t('clients.form.fields.company_name')"
                    autocomplete="organization"
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
                        :error="form.errors.email"
                    />
                </div>
                <TextField
                    v-model="form.contact_name"
                    :label="t('clients.form.fields.contact_name')"
                    autocomplete="name"
                    :error="form.errors.contact_name"
                />
                <SelectField
                    v-model="form.commodity_sector_id"
                    :label="t('clients.form.fields.sector')"
                    :options="sectors"
                    :placeholder="t('clients.form.fields.sector_placeholder')"
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
                        :error="form.errors.sector_free_text"
                    />
                    <p class="text-[13px] text-muted">
                        {{ t('clients.form.fields.sector_free_text_help') }}
                    </p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label
                        for="new-client-logo"
                        class="text-sm font-medium text-ink"
                    >{{ t('clients.form.logo.choose') }}</label>
                    <input
                        id="new-client-logo"
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
                    <p class="text-[13px] text-muted">
                        {{ t('clients.form.logo.intro') }}
                    </p>
                </div>
                <p class="text-[13px] leading-relaxed text-muted">
                    {{ t('clients.form.duplicate_help') }}
                </p>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-line px-6 py-4 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    :class="buttonClasses.secondary"
                    :disabled="form.processing"
                    @click="close"
                >
                    {{ t('common.cancel') }}
                </button>
                <button
                    type="submit"
                    :class="buttonClasses.primary"
                    :disabled="form.processing"
                >
                    {{ form.processing ? t('common.saving') : t('proposals.client.dialog_create') }}
                </button>
            </div>
        </form>

        <NoticeDialog
            :open="duplicateMessage !== undefined"
            :title="t('clients.form.duplicate_title')"
            :close-label="t('clients.form.duplicate_close')"
            @close="closeDuplicate"
        >
            <p>{{ duplicateMessage }}</p>
        </NoticeDialog>
    </div>
</template>
