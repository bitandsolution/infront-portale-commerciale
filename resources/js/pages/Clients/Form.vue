<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Combine, Lock, Power, UserRoundCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import SelectField from '@/components/forms/SelectField.vue';
import TextField from '@/components/forms/TextField.vue';
import NoticeDialog from '@/components/NoticeDialog.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ClientForm, ClientManagement, ClientStatus, LayoutData, Option, SectorOption, StatusTone } from '@/types';

/**
 * New client or page of a client (M05 F-01, F-03, F-04, §6; D-075, D-078, D-358, D-361).
 * - Company name, e-mail, sector and logo required (logo only for a new client); VAT number and
 *   contact optional; free text required with a sector that asks for it (RB-04).
 * - The admin chooses the owner agent of a new client (D-358).
 * - The manager sees the client read-only (canEdit false).
 * - A duplicate opens a blocking dialog (ux-architettura §4.5); the data stay in the form.
 * Data and logo travel together in one multipart request (PUT spoofed as POST for a change).
 * - The admin also has the "Gestione del cliente" section (F3-03; D-082, D-359): reassignment,
 *   deactivation (with confirmation) and reactivation, merge into the client that stays (with
 *   confirmation). The server sends only the actions allowed now; a merged client is read-only.
 */
const props = defineProps<{
    layout: LayoutData;
    client: ClientForm | null;
    canEdit: boolean;
    sectors: SectorOption[];
    owners: Option[] | null;
    management: ClientManagement | null;
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

const statusTones: Record<ClientStatus, StatusTone> = { active: 'success', inactive: 'muted', merged: 'notice' };

const ownerText = computed(() =>
    props.client === null
        ? ''
        : props.client.ownerActive
            ? t('clients.form.owner', { owner: props.client.owner })
            : t('clients.form.owner_inactive', { owner: props.client.owner }),
);

// Admin actions (F3-03). Reassignment: one select and a button; deactivation and merge ask for a
// confirmation with the effect (ux-architettura §4.5); reactivation runs at once.
const reassignForm = useForm({ owner_id: '' });

function reassign(): void {
    const url = props.management?.reassignUrl;
    if (!url) {
        return;
    }
    reassignForm.post(url, { preserveScroll: true, onSuccess: () => reassignForm.reset() });
}

const mergeSearch = ref('');
const mergeForm = useForm({ keep_id: '' });

const mergeOptions = computed<Option[]>(() => {
    const term = mergeSearch.value.trim().toLocaleLowerCase('it');
    const options = props.management?.mergeTargets ?? [];

    return term === '' ? options : options.filter((option) => option.label.toLocaleLowerCase('it').includes(term) || option.value === mergeForm.keep_id);
});

const mergeKeepLabel = computed(() => props.management?.mergeTargets.find((option) => option.value === mergeForm.keep_id)?.label ?? '');

type Confirming = 'deactivate' | 'merge';

const confirming = ref<Confirming | null>(null);
const running = ref(false);

const confirmTitle = computed(() =>
    confirming.value === 'merge'
        ? t('clients.manage.confirm_merge_title', { client: props.client?.companyName ?? '' })
        : t('clients.manage.confirm_deactivate_title', { client: props.client?.companyName ?? '' }),
);

function runStatus(url: string | null | undefined): void {
    if (!url) {
        return;
    }
    running.value = true;
    router.post(url, {}, {
        preserveScroll: true,
        onFinish: () => {
            running.value = false;
            confirming.value = null;
        },
    });
}

function confirm(): void {
    if (confirming.value === 'deactivate') {
        runStatus(props.management?.deactivateUrl);

        return;
    }
    const url = props.management?.mergeUrl;
    if (!url) {
        return;
    }
    running.value = true;
    mergeForm.post(url, {
        onFinish: () => {
            running.value = false;
            confirming.value = null;
        },
    });
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
                                :label="t(`clients.${client.status}`)"
                                :tone="statusTones[client.status]"
                            />
                            <span>{{ ownerText }}</span>
                        </div>
                        <p
                            v-if="canEdit"
                            class="mt-2 text-sm leading-relaxed text-muted"
                        >
                            {{ t('clients.form.duplicate_help') }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="client?.status === 'merged'"
                    class="flex items-start gap-2 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink-soft"
                >
                    <Combine
                        :size="16"
                        :stroke-width="2"
                        class="mt-0.5 shrink-0"
                        aria-hidden="true"
                    />
                    <p v-if="client.mergedInto">
                        {{ t('clients.form.merged_into', { client: client.mergedInto.name }) }}
                        <Link
                            :href="client.mergedInto.url"
                            class="font-semibold text-ink underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-accent"
                        >
                            {{ t('clients.form.merged_open', { client: client.mergedInto.name }) }}
                        </Link>
                    </p>
                    <p v-else>
                        {{ t('clients.form.merged_read_only') }}
                    </p>
                </div>

                <p
                    v-else-if="!canEdit"
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

            <!-- Admin actions (F3-03): the server sends only those allowed now. -->
            <section
                v-if="management && client && client.status !== 'merged'"
                class="flex flex-col divide-y divide-line rounded-[14px] border border-line bg-white px-5 md:px-6"
                aria-labelledby="client-manage"
            >
                <h2
                    id="client-manage"
                    class="py-5 text-lg font-semibold"
                >
                    {{ t('clients.manage.title') }}
                </h2>

                <form
                    v-if="management.reassignUrl"
                    class="flex flex-col gap-3 py-5"
                    novalidate
                    @submit.prevent="reassign"
                >
                    <h3 class="text-base font-semibold">
                        {{ t('clients.manage.reassign_title') }}
                    </h3>
                    <p class="text-sm leading-relaxed text-ink-soft">
                        {{ t('clients.manage.reassign_help') }}
                    </p>
                    <p
                        v-if="management.owners.length === 0"
                        class="text-sm text-muted"
                    >
                        {{ t('clients.manage.reassign_none') }}
                    </p>
                    <div
                        v-else
                        class="flex flex-col gap-3 sm:flex-row sm:items-end"
                    >
                        <div class="min-w-0 flex-1">
                            <SelectField
                                v-model="reassignForm.owner_id"
                                :label="t('clients.manage.reassign_owner')"
                                :options="management.owners"
                                :placeholder="t('clients.manage.reassign_placeholder')"
                                :error="reassignForm.errors.owner_id"
                            />
                        </div>
                        <button
                            type="submit"
                            :class="buttonClasses.secondary"
                            class="shrink-0"
                            :disabled="reassignForm.processing || reassignForm.owner_id === '' || running"
                        >
                            <UserRoundCheck
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('clients.manage.reassign') }}
                        </button>
                    </div>
                </form>

                <div class="flex flex-col gap-3 py-5">
                    <h3 class="text-base font-semibold">
                        {{ t('clients.manage.status_title') }}
                    </h3>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex max-w-xl flex-col gap-1 text-sm leading-relaxed text-ink-soft">
                            <p>{{ client.isActive ? t('clients.manage.active_text') : t('clients.manage.inactive_text') }}</p>
                            <p v-if="management.deactivateUrl">
                                {{ t('clients.manage.deactivate_help') }}
                            </p>
                        </div>
                        <button
                            v-if="management.deactivateUrl"
                            type="button"
                            :class="buttonClasses.danger"
                            class="shrink-0"
                            :disabled="running"
                            @click="confirming = 'deactivate'"
                        >
                            <Power
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('clients.manage.deactivate') }}
                        </button>
                        <button
                            v-if="management.reactivateUrl"
                            type="button"
                            :class="buttonClasses.secondary"
                            class="shrink-0"
                            :disabled="running"
                            @click="runStatus(management.reactivateUrl)"
                        >
                            <Power
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('clients.manage.reactivate') }}
                        </button>
                    </div>
                </div>

                <form
                    v-if="management.mergeUrl"
                    class="flex flex-col gap-3 py-5"
                    novalidate
                    @submit.prevent="confirming = 'merge'"
                >
                    <h3 class="text-base font-semibold">
                        {{ t('clients.manage.merge_title') }}
                    </h3>
                    <p class="text-sm leading-relaxed text-ink-soft">
                        {{ t('clients.manage.merge_help') }}
                    </p>
                    <TextField
                        v-model="mergeSearch"
                        :label="t('clients.manage.merge_search')"
                        type="search"
                        autocomplete="off"
                    />
                    <p
                        v-if="mergeOptions.length === 0"
                        class="text-sm text-muted"
                    >
                        {{ t('clients.manage.merge_none') }}
                    </p>
                    <div
                        v-else
                        class="flex flex-col gap-3 sm:flex-row sm:items-end"
                    >
                        <div class="min-w-0 flex-1">
                            <SelectField
                                v-model="mergeForm.keep_id"
                                :label="t('clients.manage.merge_keep')"
                                :options="mergeOptions"
                                :placeholder="t('clients.manage.merge_placeholder')"
                                :error="mergeForm.errors.keep_id"
                            />
                        </div>
                        <button
                            type="submit"
                            :class="buttonClasses.danger"
                            class="shrink-0"
                            :disabled="mergeForm.keep_id === '' || running"
                        >
                            <Combine
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('clients.manage.merge') }}
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <ConfirmDialog
            :open="confirming !== null"
            :title="confirmTitle"
            :confirm-label="confirming === 'merge' ? t('clients.manage.confirm_merge') : t('clients.manage.confirm_deactivate')"
            :processing="running"
            @cancel="confirming = null"
            @confirm="confirm"
        >
            <template v-if="confirming === 'merge'">
                <p>{{ t('clients.manage.confirm_merge_text', { client: client?.companyName ?? '', keep: mergeKeepLabel }) }}</p>
                <p>{{ t('clients.manage.confirm_merge_final') }}</p>
            </template>
            <p v-else>
                {{ t('clients.manage.confirm_deactivate_text') }}
            </p>
        </ConfirmDialog>

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
