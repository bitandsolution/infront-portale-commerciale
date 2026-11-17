<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Ban, CheckCircle2, CircleCheck, CircleX, Info, Lock, LogOut, Pencil, Send, Undo2 } from '@lucide/vue';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import SelectField from '@/components/forms/SelectField.vue';
import TextField from '@/components/forms/TextField.vue';
import DocumentPanel from '@/components/proposals/DocumentPanel.vue';
import DocumentSlides from '@/components/proposals/DocumentSlides.vue';
import DraftSummary from '@/components/proposals/DraftSummary.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { DocumentSlides as DocumentSlidesData, LayoutData, ProposalGroupDetail } from '@/types';

/**
 * Detail of a group of variants (D-411; DM-03a, DM-03b), read only and the same for admin, manager and
 * the agent of the group: header, last decision, every variant with its summary (frozen version after
 * the approval), the history (M07 F-10). The agent sends the group again from here or from the
 * configurator. Admin and manager, while the group waits: prices changed since the sending (D-409),
 * availability of the group (D-216), discount of every variant with the final total calculated by the
 * server (D-408), "Approva" and "Rifiuta"; after the approval of more variants, the choice of the
 * client (D-410). The agent never sees discounts (D-011). DM-04: the PDF document of the group
 * (DocumentPanel) and, before the approval, what the library lacks for it (D-416). DM-05: the slides
 * of the document (DocumentSlides), which admin and manager change while the group waits (D-397).
 * F5-01: the lock of the work (D-446): who holds it, "Subentra" for the admin, "Lascia la lavorazione"
 * for the holder; "Ritira dall'approvazione" for the agent (D-100), blocked while the group is worked.
 */
const props = defineProps<{
    layout: LayoutData;
    group: ProposalGroupDetail;
    documentSlides: DocumentSlidesData | null;
}>();

const { t } = useTrans();

const many = computed(() => props.group.variants.length > 1);
const letters = computed(() => props.group.variants.map((variant) => variant.letter).filter((letter) => letter !== null).join(', '));
const isRejected = computed(() => props.group.status === 'rejected');
const isSubmitted = computed(() => props.group.status === 'submitted');
const isApproved = computed(() => props.group.status === 'approved');
const isCancelled = computed(() => props.group.status === 'cancelled');

const reviewing = computed(() => props.group.canApprove);

// Discounts typed by admin or manager (D-408): the final totals come from the server, which the page
// asks again with the query parameter "sconti" (a reload of the prop "group" only).
const discountForm = reactive<Record<number, { type: string; value: string }>>({});
for (const variant of props.group.variants) {
    discountForm[variant.id] = { type: variant.discount?.type ?? '', value: variant.discount?.value ?? '' };
}
const discountErrors = ref<Record<string, string>>({});
const calculating = ref(false);
let previewTimer: ReturnType<typeof setTimeout> | undefined;

const discountTypes = computed(() => [
    { value: '', label: t('proposals.approval.discount_none') },
    { value: 'percent', label: t('proposals.approval.discount_percent') },
    { value: 'amount', label: t('proposals.approval.discount_amount') },
]);

function previewDiscounts(): void {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(() => {
        calculating.value = true;
        router.reload({
            data: { sconti: discountForm },
            only: ['group'],
            onFinish: () => (calculating.value = false),
        });
    }, 400);
}

onBeforeUnmount(() => clearTimeout(previewTimer));

function discountError(variantId: number): string | undefined {
    return discountErrors.value[`discounts.${variantId}.value`] ?? props.group.variants.find((variant) => variant.id === variantId)?.discount?.error ?? undefined;
}

const confirmingApprove = ref(false);

// F5-04 (D-466): "Modifica" of an approved variant starts its new version and opens the configurator.
function startRevision(url: string): void {
    processing.value = true;
    router.post(url, {}, { onFinish: () => (processing.value = false) });
}

// F5-03 (D-064, D-070, D-459): "Forza e approva" of the admin, with the reason.
const forcing = ref(false);
const forcedReason = ref('');
const forcedReasonError = ref<string | undefined>(undefined);

function openForce(): void {
    forcedReason.value = '';
    forcedReasonError.value = undefined;
    forcing.value = true;
}

function force(): void {
    processing.value = true;
    const send = (): void => {
        router.post(props.group.forceUrl, { discounts: discountForm, forced_reason: forcedReason.value }, {
            preserveScroll: true,
            preserveState: 'errors',
            onError: (errors) => {
                discountErrors.value = errors;
                forcedReasonError.value = errors.forced_reason;
            },
            onSuccess: () => (forcing.value = false),
            onFinish: () => (processing.value = false),
        });
    };
    if (documentSlidesPanel.value) {
        documentSlidesPanel.value.flush(send);
    } else {
        send();
    }
}

const documentSlidesPanel = ref<InstanceType<typeof DocumentSlides> | null>(null);

function approve(): void {
    processing.value = true;
    // The choice of the slides still to save goes first (DM-05): the approval freezes it.
    if (documentSlidesPanel.value) {
        documentSlidesPanel.value.flush(sendApproval);
    } else {
        sendApproval();
    }
}

function sendApproval(): void {
    router.post(props.group.approveUrl, { discounts: discountForm }, {
        preserveScroll: true,
        preserveState: 'errors',
        onError: (errors) => (discountErrors.value = errors),
        onFinish: () => {
            processing.value = false;
            confirmingApprove.value = false;
        },
    });
}

// Choice of the client (D-410).
const chosenVariant = ref('');
const confirmingChoice = ref(false);
const choiceOptions = computed(() => props.group.variants.map((variant) => ({
    value: String(variant.id),
    label: t('proposals.approval.choose_variant', { letter: variant.letter ?? '', number: String(variant.number), total: variant.discount?.finalTotal ?? variant.draft?.listTotal ?? '' }),
})));
const chosenLetter = computed(() => props.group.variants.find((variant) => String(variant.id) === chosenVariant.value)?.letter ?? '');

function choose(): void {
    processing.value = true;
    router.post(props.group.chooseUrl, { proposal_id: chosenVariant.value }, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            confirmingChoice.value = false;
        },
    });
}

const confirmingSubmit = ref(false);
const rejecting = ref(false);
const reason = ref('');
const reasonError = ref<string | undefined>(undefined);
const processing = ref(false);

function submit(): void {
    processing.value = true;
    router.post(props.group.submitUrl, {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            confirmingSubmit.value = false;
        },
    });
}

// F5-01: withdrawal (agent), take over (admin), release (holder).
const confirmingWithdraw = ref(false);
const confirmingTakeOver = ref(false);

function postAndClose(url: string, close: () => void): void {
    processing.value = true;
    router.post(url, {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            close();
        },
    });
}

function withdraw(): void {
    postAndClose(props.group.withdrawUrl, () => (confirmingWithdraw.value = false));
}

function takeOver(): void {
    postAndClose(props.group.takeOverUrl, () => (confirmingTakeOver.value = false));
}

function release(): void {
    postAndClose(props.group.releaseUrl, () => undefined);
}

// F5-05 (D-095, D-447): cancellation of the approved group by the admin, with the reason.
const cancelling = ref(false);
const cancelReason = ref('');
const cancelReasonError = ref<string | undefined>(undefined);

function openCancel(): void {
    cancelReason.value = '';
    cancelReasonError.value = undefined;
    cancelling.value = true;
}

function cancelGroup(): void {
    processing.value = true;
    router.post(props.group.cancelUrl, { cancel_reason: cancelReason.value }, {
        preserveScroll: true,
        preserveState: 'errors',
        onError: (errors) => (cancelReasonError.value = errors.cancel_reason),
        onSuccess: () => (cancelling.value = false),
        onFinish: () => (processing.value = false),
    });
}

function openReject(): void {
    reason.value = '';
    reasonError.value = undefined;
    rejecting.value = true;
}

function reject(): void {
    processing.value = true;
    router.post(props.group.rejectUrl, { reason: reason.value }, {
        preserveState: 'errors',
        onError: (errors) => (reasonError.value = errors.reason),
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <Link
                :href="group.backUrl"
                class="inline-flex items-center gap-1.5 self-start text-sm font-medium text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
            >
                <ArrowLeft
                    :size="16"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ group.backLabel }}
            </Link>

            <section class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl font-semibold text-ink">
                            {{ group.title }}
                        </h1>
                        <StatusPill
                            :label="group.statusLabel"
                            :tone="group.statusTone"
                        />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="group.canSubmit"
                            type="button"
                            :class="buttonClasses.primary"
                            @click="confirmingSubmit = true"
                        >
                            <Send
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('proposals.submit.action') }}
                        </button>
                        <button
                            v-if="group.canWithdraw"
                            type="button"
                            :class="buttonClasses.secondary"
                            :disabled="group.withdrawBlocked"
                            @click="confirmingWithdraw = true"
                        >
                            <Undo2
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('proposals.groups.withdraw.action') }}
                        </button>
                        <button
                            v-if="group.canApprove"
                            type="button"
                            :class="buttonClasses.primary"
                            :disabled="group.problems.length > 0 || calculating"
                            @click="confirmingApprove = true"
                        >
                            <CircleCheck
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('proposals.approval.action') }}
                        </button>
                        <button
                            v-if="group.canReject"
                            type="button"
                            :class="buttonClasses.secondary"
                            @click="openReject"
                        >
                            <CircleX
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('proposals.groups.reject.action') }}
                        </button>
                        <button
                            v-if="group.canCancel"
                            type="button"
                            :class="buttonClasses.secondary"
                            @click="openCancel"
                        >
                            <Ban
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('proposals.groups.cancel.action') }}
                        </button>
                    </div>
                </div>

                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-muted">
                            {{ t('proposals.groups.client') }}
                        </dt>
                        <dd class="font-medium text-ink">
                            {{ group.client }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted">
                            {{ t('proposals.groups.season') }}
                        </dt>
                        <dd class="font-medium text-ink">
                            {{ group.season }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted">
                            {{ t('proposals.groups.agent') }}
                        </dt>
                        <dd class="font-medium text-ink">
                            {{ group.agent }}
                        </dd>
                    </div>
                    <div v-if="group.documentLabel">
                        <dt class="text-muted">
                            {{ t('proposals.groups.document') }}
                        </dt>
                        <dd class="font-medium text-ink">
                            {{ group.documentLabel }}
                        </dd>
                    </div>
                </dl>

                <p
                    v-if="group.submittedAt"
                    class="text-sm text-muted"
                >
                    {{ t('proposals.groups.submitted', { at: group.submittedAt }) }}
                </p>

                <p
                    v-if="isSubmitted"
                    class="rounded-[10px] bg-surface px-3.5 py-3 text-sm text-ink"
                >
                    {{ group.reviewLock?.mine ? t('proposals.groups.read_only_mine') : t('proposals.groups.read_only') }}
                </p>

                <!-- F5-01: lock of the work (D-446) -->
                <div
                    v-if="group.reviewLock"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] px-3.5 py-3 text-sm text-ink"
                    :class="group.reviewLock.mine ? 'bg-surface' : 'bg-notice-soft'"
                    role="status"
                >
                    <p class="flex items-start gap-2">
                        <Lock
                            :size="18"
                            :stroke-width="2"
                            class="mt-0.5 shrink-0"
                            :class="group.reviewLock.mine ? 'text-muted' : 'text-notice'"
                            aria-hidden="true"
                        />
                        <span v-if="group.reviewLock.mine">{{ t('proposals.groups.lock.mine') }}</span>
                        <span v-else-if="group.canWithdraw">{{ t('proposals.groups.lock.agent', { name: group.reviewLock.holder }) }}</span>
                        <span v-else>{{ t('proposals.groups.lock.other', { name: group.reviewLock.holder, since: group.reviewLock.since ?? '' }) }}</span>
                    </p>
                    <button
                        v-if="group.canTakeOver"
                        type="button"
                        :class="buttonClasses.secondary"
                        @click="confirmingTakeOver = true"
                    >
                        {{ t('proposals.groups.lock.take_over') }}
                    </button>
                    <button
                        v-if="group.canRelease"
                        type="button"
                        :class="buttonClasses.secondary"
                        :disabled="processing"
                        @click="release"
                    >
                        <LogOut
                            :size="18"
                            :stroke-width="2"
                            aria-hidden="true"
                        />
                        {{ t('proposals.groups.lock.release') }}
                    </button>
                </div>

                <div
                    v-if="isRejected && group.decidedAt"
                    class="flex gap-3 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
                    role="status"
                >
                    <CircleX
                        :size="18"
                        :stroke-width="2"
                        class="mt-0.5 shrink-0 text-notice"
                        aria-hidden="true"
                    />
                    <div class="flex flex-col gap-1">
                        <p class="font-semibold">
                            {{ t('proposals.rejection.title', { at: group.decidedAt }) }}<template v-if="group.decidedBy">
                                {{ t('proposals.rejection.by', { name: group.decidedBy }) }}
                            </template>
                        </p>
                        <p>{{ group.rejectionReason ? t('proposals.rejection.reason', { reason: group.rejectionReason }) : t('proposals.rejection.no_reason') }}</p>
                        <p
                            v-if="group.canSubmit"
                            class="text-muted"
                        >
                            {{ t('proposals.rejection.hint') }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="isCancelled && group.cancellation"
                    class="flex gap-3 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
                    role="status"
                >
                    <Ban
                        :size="18"
                        :stroke-width="2"
                        class="mt-0.5 shrink-0 text-notice"
                        aria-hidden="true"
                    />
                    <div class="flex flex-col gap-1">
                        <p class="font-semibold">
                            {{ t('proposals.groups.cancel.banner', { at: group.cancellation.at ?? '' }) }}<template v-if="group.cancellation.by">
                                {{ t('proposals.groups.cancel.by', { name: group.cancellation.by }) }}
                            </template>
                        </p>
                        <p v-if="group.cancellation.reason">
                            {{ t('proposals.groups.cancel.reason_line', { reason: group.cancellation.reason }) }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="isApproved && group.decidedAt"
                    class="flex gap-3 rounded-[10px] bg-success-soft px-3.5 py-3 text-sm text-ink"
                    role="status"
                >
                    <CheckCircle2
                        :size="18"
                        :stroke-width="2"
                        class="mt-0.5 shrink-0 text-success"
                        aria-hidden="true"
                    />
                    <div class="flex flex-col gap-1">
                        <p class="font-semibold">
                            {{ group.decidedBy ? t('proposals.approval.approved', { at: group.decidedAt, name: group.decidedBy }) : t('proposals.approval.approved_at', { at: group.decidedAt }) }}
                        </p>
                        <p v-if="group.chosenLetter">
                            {{ t('proposals.approval.chosen', { letter: group.chosenLetter }) }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- PDF document of the approved group (DM-04) -->
            <DocumentPanel
                v-if="group.document"
                :document="group.document"
            />

            <!-- Admin and manager: problems and availability of the group (D-216, D-409) -->
            <section
                v-if="reviewing"
                class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5"
            >
                <div
                    v-if="group.problems.length > 0"
                    class="flex flex-col gap-2 rounded-[10px] bg-accent-soft px-3.5 py-3 text-sm text-ink"
                    role="alert"
                >
                    <p class="flex items-center gap-2 font-semibold">
                        <AlertTriangle
                            :size="18"
                            :stroke-width="2"
                            class="shrink-0 text-accent"
                            aria-hidden="true"
                        />
                        {{ t('proposals.approval.blocked_title') }}
                    </p>
                    <ul class="flex list-disc flex-col gap-1 pl-5">
                        <li
                            v-for="(problem, index) in group.problems"
                            :key="index"
                        >
                            {{ problem }}
                        </li>
                    </ul>
                    <div
                        v-if="group.canForce"
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <p class="text-muted">
                            {{ t('proposals.approval.force_hint') }}
                        </p>
                        <button
                            type="button"
                            :class="buttonClasses.danger"
                            :disabled="calculating"
                            @click="openForce"
                        >
                            {{ t('proposals.approval.force_action') }}
                        </button>
                    </div>
                    <p
                        v-else-if="group.forceable"
                        class="text-muted"
                    >
                        {{ t('proposals.approval.force_admin_only') }}
                    </p>
                    <p
                        v-else
                        class="text-muted"
                    >
                        {{ t('proposals.approval.blocked_hint') }}
                    </p>
                </div>

                <!-- What the document would miss in the library (D-416): the approval stays possible -->
                <div
                    v-if="group.documentWarnings.length > 0"
                    class="flex flex-col gap-2 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
                    role="status"
                >
                    <p class="flex items-center gap-2 font-semibold">
                        <Info
                            :size="18"
                            :stroke-width="2"
                            class="shrink-0 text-notice"
                            aria-hidden="true"
                        />
                        {{ t('proposals.document.warnings_title') }}
                    </p>
                    <ul class="flex list-disc flex-col gap-1 pl-5">
                        <li
                            v-for="(warning, index) in group.documentWarnings"
                            :key="index"
                        >
                            {{ warning }}
                        </li>
                    </ul>
                    <p class="text-muted">
                        {{ t('proposals.document.warnings_hint') }}
                    </p>
                </div>

                <div v-if="group.availability && group.availability.length > 0">
                    <h2 class="text-base font-semibold text-ink">
                        {{ t('proposals.approval.availability_title') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        {{ t('proposals.approval.availability_intro') }}
                    </p>
                    <div class="mt-3 overflow-x-auto rounded-[12px] border border-line">
                        <table class="w-full min-w-[560px] text-left text-sm">
                            <thead class="border-b border-line bg-surface text-[13px] text-muted">
                                <tr>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 font-medium"
                                    >
                                        {{ t('proposals.approval.columns.club') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 font-medium"
                                    >
                                        {{ t('proposals.approval.columns.resource') }}
                                    </th>
                                    <th
                                        v-if="group.variants.length > 1"
                                        scope="col"
                                        class="px-3 py-2.5 font-medium"
                                    >
                                        {{ t('proposals.approval.columns.variants') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-right font-medium"
                                    >
                                        {{ t('proposals.approval.columns.requested') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 text-right font-medium"
                                    >
                                        {{ t('proposals.approval.columns.remaining') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 font-medium"
                                    >
                                        {{ t('proposals.approval.columns.outcome') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <tr
                                    v-for="(need, index) in group.availability"
                                    :key="index"
                                >
                                    <td class="px-3 py-2.5 font-medium">
                                        {{ need.club }}
                                    </td>
                                    <td class="px-3 py-2.5">
                                        {{ need.resource }}
                                    </td>
                                    <td
                                        v-if="group.variants.length > 1"
                                        class="px-3 py-2.5 text-muted"
                                    >
                                        {{ need.letters ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono">
                                        {{ need.requested }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono">
                                        {{ need.remaining }}
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span
                                            class="inline-flex items-center gap-1.5"
                                            :class="need.covered ? 'text-success' : 'text-accent'"
                                        >
                                            <CheckCircle2
                                                v-if="need.covered"
                                                :size="16"
                                                :stroke-width="2"
                                                aria-hidden="true"
                                            />
                                            <AlertTriangle
                                                v-else
                                                :size="16"
                                                :stroke-width="2"
                                                aria-hidden="true"
                                            />
                                            {{ need.covered ? t('proposals.approval.covered') : t('proposals.approval.short') }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Choice of the client (D-410) -->
            <section
                v-if="group.canChoose"
                class="flex flex-col gap-3 rounded-[14px] border border-line bg-white p-5"
            >
                <h2 class="text-base font-semibold text-ink">
                    {{ t('proposals.approval.choose_title') }}
                </h2>
                <p class="text-sm text-muted">
                    {{ t('proposals.approval.choose_text') }}
                </p>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-72 flex-1">
                        <SelectField
                            v-model="chosenVariant"
                            :label="t('proposals.approval.choose_label')"
                            :options="choiceOptions"
                            :placeholder="chosenVariant === '' ? t('proposals.approval.choose_placeholder') : undefined"
                        />
                    </div>
                    <button
                        type="button"
                        :class="buttonClasses.primary"
                        :disabled="chosenVariant === ''"
                        @click="confirmingChoice = true"
                    >
                        {{ t('proposals.approval.choose_action') }}
                    </button>
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <h2
                    v-if="many"
                    class="text-lg font-semibold text-ink"
                >
                    {{ t('proposals.groups.variants_title') }}
                </h2>
                <article
                    v-for="variant in group.variants"
                    :key="variant.id"
                    class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h3 class="text-base font-semibold text-ink">
                                {{ variant.letter ? t('proposals.groups.variant_title', { letter: variant.letter, number: String(variant.number) }) : t('proposals.groups.single_title', { number: String(variant.number) }) }}
                            </h3>
                            <span
                                v-if="many"
                                class="text-[13px] text-muted"
                            >{{ variant.statusLabel }}</span>
                            <StatusPill
                                v-if="variant.forcedReason"
                                :label="t('proposals.approval.forced_badge')"
                                tone="notice"
                            />
                        </div>
                        <Link
                            v-if="variant.editUrl"
                            :href="variant.editUrl"
                            :class="buttonClasses.secondary"
                        >
                            <Pencil
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('proposals.groups.open_configurator') }}
                        </Link>
                        <Link
                            v-if="variant.reviewUrl"
                            :href="variant.reviewUrl"
                            :class="buttonClasses.secondary"
                        >
                            <Pencil
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('proposals.groups.edit_review') }}
                        </Link>
                        <!-- F5-04 (D-466): new version of an approved variant, admin only -->
                        <button
                            v-if="variant.revisionStartUrl"
                            type="button"
                            :class="buttonClasses.secondary"
                            :disabled="processing"
                            @click="startRevision(variant.revisionStartUrl)"
                        >
                            <Pencil
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('proposals.versions.start') }}
                        </button>
                        <Link
                            v-if="variant.revisionUrl"
                            :href="variant.revisionUrl"
                            :class="buttonClasses.primary"
                        >
                            <Pencil
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('proposals.versions.resume') }}
                        </Link>
                    </div>
                    <p
                        v-if="variant.underRevision"
                        class="rounded-[10px] bg-notice-soft px-3.5 py-2.5 text-sm text-ink"
                        role="status"
                    >
                        {{ t('proposals.versions.in_progress') }}
                    </p>
                    <div
                        v-if="variant.repricing"
                        class="flex gap-2.5 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
                        role="status"
                    >
                        <Info
                            :size="18"
                            :stroke-width="2"
                            class="mt-0.5 shrink-0 text-notice"
                            aria-hidden="true"
                        />
                        <div class="flex flex-col gap-1">
                            <p class="font-semibold">
                                {{ t('proposals.approval.repricing_title') }}
                            </p>
                            <ul class="flex flex-col gap-1">
                                <li
                                    v-for="club in variant.repricing.clubs"
                                    :key="club.name"
                                >
                                    {{ club.text }}
                                </li>
                                <li class="font-medium">
                                    {{ variant.repricing.total }}
                                </li>
                            </ul>
                            <p class="text-muted">
                                {{ t('proposals.approval.repricing_hint') }}
                            </p>
                        </div>
                    </div>
                    <DraftSummary
                        v-if="variant.draft"
                        :draft="variant.draft"
                        :client-name="variant.draft.client?.name ?? null"
                        :sector="variant.draft.client?.sector ?? null"
                        read-only
                        in-group
                        :hide-note="variant.discount !== null || isApproved"
                    />

                    <!-- Discount and final total, admin and manager only (D-011, D-408) -->
                    <div
                        v-if="variant.discount"
                        class="flex flex-col gap-3 rounded-[12px] border border-line bg-surface/60 p-4"
                    >
                        <h4 class="text-sm font-semibold text-ink">
                            {{ t('proposals.approval.discount_title') }}
                        </h4>
                        <div
                            v-if="variant.discount.editable && reviewing"
                            class="grid gap-3 sm:grid-cols-2"
                        >
                            <SelectField
                                v-model="discountForm[variant.id]!.type"
                                :label="t('proposals.approval.discount_type')"
                                :options="discountTypes"
                                @update:model-value="previewDiscounts"
                            />
                            <div class="flex flex-col gap-1">
                                <TextField
                                    v-model="discountForm[variant.id]!.value"
                                    :label="t('proposals.approval.discount_value')"
                                    inputmode="numeric"
                                    mono
                                    :disabled="discountForm[variant.id]!.type === ''"
                                    :error="discountError(variant.id)"
                                    @update:model-value="previewDiscounts"
                                />
                                <p class="text-[13px] text-muted">
                                    {{ t('proposals.approval.discount_value_help') }}
                                </p>
                            </div>
                        </div>
                        <dl class="flex flex-col gap-1.5 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted">
                                    {{ t('proposals.approval.list_total') }}
                                </dt>
                                <dd class="font-mono">
                                    € {{ variant.discount.listTotal }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted">
                                    {{ variant.discount.label ?? t('proposals.approval.discount') }}
                                </dt>
                                <dd class="font-mono">
                                    − € {{ variant.discount.amount }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3 border-t border-line pt-1.5">
                                <dt class="font-semibold">
                                    {{ t('proposals.approval.final_total') }}
                                </dt>
                                <dd
                                    class="font-mono text-base font-semibold"
                                    aria-live="polite"
                                >
                                    <span
                                        v-if="calculating"
                                        class="font-sans text-sm font-normal text-muted"
                                    >{{ t('proposals.approval.calculating') }}</span>
                                    <template v-else>
                                        € {{ variant.discount.finalTotal }}
                                    </template>
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <p
                        v-else
                        class="text-sm text-muted"
                    >
                        {{ t('proposals.groups.no_version') }}
                    </p>

                    <!-- F5-04 (D-468): frozen versions of the variant, the most recent first -->
                    <div
                        v-if="variant.versions.length > 0"
                        class="flex flex-col gap-2"
                    >
                        <h4 class="text-sm font-semibold text-ink">
                            {{ t('proposals.versions.heading') }}
                        </h4>
                        <ol class="flex flex-col divide-y divide-line rounded-[12px] border border-line">
                            <li
                                v-for="version in variant.versions"
                                :key="version.id"
                                class="flex flex-col gap-1.5 px-4 py-3 text-sm"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-ink">{{ version.title }}</span>
                                    <StatusPill
                                        v-if="version.current"
                                        :label="t('proposals.versions.current')"
                                        tone="success"
                                    />
                                    <StatusPill
                                        v-if="version.forced"
                                        :label="t('proposals.versions.forced')"
                                        tone="notice"
                                    />
                                    <span class="text-muted">{{ t('proposals.versions.meta', { at: version.at ?? '', author: version.author ?? '' }) }}</span>
                                </div>
                                <p class="text-ink-soft">
                                    {{ t('proposals.versions.list_total', { value: version.listTotal }) }}
                                    <template v-if="version.discount">
                                        · {{ version.discount }}
                                    </template>
                                    <template v-if="version.finalTotal">
                                        · {{ t('proposals.versions.final_total', { value: version.finalTotal }) }}
                                    </template>
                                </p>
                                <ul
                                    v-if="version.changes.length > 0"
                                    class="flex list-disc flex-col gap-0.5 pl-5 text-ink"
                                >
                                    <li
                                        v-for="(change, index) in version.changes"
                                        :key="index"
                                    >
                                        {{ change }}
                                    </li>
                                </ul>
                                <p
                                    v-else-if="version.number === 1"
                                    class="text-muted"
                                >
                                    {{ t('proposals.versions.first') }}
                                </p>
                                <details
                                    v-if="version.draft"
                                    class="group"
                                >
                                    <summary class="min-h-11 cursor-pointer py-2 font-medium text-accent focus-visible:outline-2 focus-visible:outline-accent">
                                        {{ t('proposals.versions.show', { number: String(version.number) }) }}
                                    </summary>
                                    <DraftSummary
                                        :draft="version.draft"
                                        :client-name="version.draft.client?.name ?? null"
                                        :sector="version.draft.client?.sector ?? null"
                                        read-only
                                        in-group
                                        hide-note
                                    />
                                </details>
                            </li>
                        </ol>
                    </div>
                </article>
            </section>

            <DocumentSlides
                v-if="documentSlides"
                ref="documentSlidesPanel"
                :slides="documentSlides"
                :show-warnings="false"
            />

            <section class="flex flex-col gap-3 rounded-[14px] border border-line bg-white p-5">
                <h2 class="text-lg font-semibold text-ink">
                    {{ t('proposals.groups.history') }}
                </h2>
                <p
                    v-if="group.history.length === 0"
                    class="text-sm text-muted"
                >
                    {{ t('proposals.groups.history_empty') }}
                </p>
                <ol
                    v-else
                    class="divide-y divide-line text-sm"
                >
                    <li
                        v-for="(event, index) in group.history"
                        :key="index"
                        class="flex flex-col gap-0.5 py-2.5 sm:flex-row sm:gap-4"
                    >
                        <span class="shrink-0 font-mono text-muted sm:w-36">{{ event.at }}</span>
                        <span class="flex flex-col gap-0.5">
                            <span>
                                <span class="font-medium text-ink">{{ event.label }}</span>
                                <template v-if="event.variant">
                                    · {{ t('proposals.groups.history_variant', { letter: event.variant }) }}
                                </template>
                                <template v-if="event.user">
                                    · <span class="text-muted">{{ event.user }}</span>
                                </template>
                            </span>
                            <span
                                v-if="event.notes"
                                class="text-muted"
                            >{{ event.notes }}</span>
                        </span>
                    </li>
                </ol>
            </section>
        </div>

        <ConfirmDialog
            :open="confirmingApprove"
            :title="many ? t('proposals.approval.confirm_title_variants', { number: String(group.number), count: String(group.variants.length) }) : t('proposals.approval.confirm_title', { number: String(group.number) })"
            :confirm-label="t('proposals.approval.confirm')"
            :processing="processing"
            @cancel="confirmingApprove = false"
            @confirm="approve"
        >
            <p>{{ t('proposals.approval.confirm_text') }}</p>
            <ul class="mt-2 flex flex-col gap-1">
                <li
                    v-for="variant in group.variants"
                    :key="variant.id"
                    class="font-medium"
                >
                    {{ variant.letter ? t('proposals.approval.confirm_variant', { letter: variant.letter, total: variant.discount?.finalTotal ?? '' }) : t('proposals.approval.confirm_single', { total: variant.discount?.finalTotal ?? '' }) }}
                </li>
            </ul>
        </ConfirmDialog>

        <ConfirmDialog
            :open="forcing"
            :title="t('proposals.approval.force_title', { number: String(group.number) })"
            :confirm-label="t('proposals.approval.force_confirm')"
            :processing="processing"
            @cancel="forcing = false"
            @confirm="force"
        >
            <p>{{ t('proposals.approval.force_text') }}</p>
            <ul class="mt-2 flex list-disc flex-col gap-1 pl-5">
                <li
                    v-for="(need, index) in group.forceNeeds"
                    :key="index"
                >
                    {{ t('proposals.approval.force_need', { ...need }) }}
                </li>
            </ul>
            <div class="mt-3 flex flex-col gap-1.5">
                <label
                    for="forced-reason"
                    class="text-sm font-medium text-ink"
                >{{ t('proposals.approval.force_reason') }}</label>
                <textarea
                    id="forced-reason"
                    v-model="forcedReason"
                    rows="3"
                    maxlength="1000"
                    class="rounded-[10px] border border-line-strong bg-white px-3 py-2 text-[15px] focus-visible:outline-2 focus-visible:outline-accent"
                    :aria-invalid="forcedReasonError !== undefined"
                    aria-describedby="forced-reason-help"
                />
                <p
                    id="forced-reason-help"
                    class="text-[13px] text-muted"
                >
                    {{ t('proposals.approval.force_reason_help', { min: String(group.forcedReasonMin) }) }}
                </p>
                <p
                    v-if="forcedReasonError"
                    class="text-sm text-accent"
                >
                    {{ forcedReasonError }}
                </p>
            </div>
        </ConfirmDialog>

        <ConfirmDialog
            :open="confirmingChoice"
            :title="t('proposals.approval.choose_confirm_title', { letter: chosenLetter })"
            :confirm-label="t('proposals.approval.choose_confirm')"
            :processing="processing"
            @cancel="confirmingChoice = false"
            @confirm="choose"
        >
            <p>{{ t('proposals.approval.choose_confirm_text') }}</p>
        </ConfirmDialog>

        <ConfirmDialog
            :open="confirmingSubmit"
            :title="many ? t('proposals.submit.title_variants', { number: String(group.number), count: String(group.variants.length) }) : t('proposals.submit.title', { number: String(group.number) })"
            :confirm-label="t('proposals.submit.confirm')"
            :processing="processing"
            @cancel="confirmingSubmit = false"
            @confirm="submit"
        >
            <p>{{ many ? t('proposals.submit.text_variants', { letters }) : t('proposals.submit.text') }}</p>
        </ConfirmDialog>

        <ConfirmDialog
            :open="confirmingWithdraw"
            :title="t('proposals.groups.withdraw.title', { number: String(group.number) })"
            :confirm-label="t('proposals.groups.withdraw.confirm')"
            :processing="processing"
            @cancel="confirmingWithdraw = false"
            @confirm="withdraw"
        >
            <p>{{ t('proposals.groups.withdraw.text') }}</p>
        </ConfirmDialog>

        <ConfirmDialog
            :open="confirmingTakeOver"
            :title="t('proposals.groups.lock.take_over_title', { number: String(group.number) })"
            :confirm-label="t('proposals.groups.lock.take_over')"
            :processing="processing"
            @cancel="confirmingTakeOver = false"
            @confirm="takeOver"
        >
            <p>{{ t('proposals.groups.lock.take_over_text', { name: group.reviewLock?.holder ?? '' }) }}</p>
        </ConfirmDialog>

        <ConfirmDialog
            :open="cancelling"
            :title="t('proposals.groups.cancel.title', { number: String(group.number) })"
            :confirm-label="t('proposals.groups.cancel.confirm')"
            :processing="processing"
            @cancel="cancelling = false"
            @confirm="cancelGroup"
        >
            <p>{{ t('proposals.groups.cancel.text') }}</p>
            <p
                v-if="group.cancelDiscardsRevision"
                class="mt-2 font-medium"
            >
                {{ t('proposals.groups.cancel.revision') }}
            </p>
            <div class="mt-3 flex flex-col gap-1.5">
                <label
                    for="cancel-reason"
                    class="text-sm font-medium text-ink"
                >{{ t('proposals.groups.cancel.reason') }}</label>
                <textarea
                    id="cancel-reason"
                    v-model="cancelReason"
                    rows="3"
                    maxlength="1000"
                    class="rounded-[10px] border border-line-strong bg-white px-3 py-2 text-[15px] focus-visible:outline-2 focus-visible:outline-accent"
                    :aria-invalid="cancelReasonError !== undefined"
                    aria-describedby="cancel-reason-help"
                />
                <p
                    id="cancel-reason-help"
                    class="text-[13px] text-muted"
                >
                    {{ t('proposals.groups.cancel.reason_help', { min: String(group.forcedReasonMin) }) }}
                </p>
                <p
                    v-if="cancelReasonError"
                    class="text-sm text-accent"
                >
                    {{ cancelReasonError }}
                </p>
            </div>
        </ConfirmDialog>

        <ConfirmDialog
            :open="rejecting"
            :title="t('proposals.groups.reject.title', { number: String(group.number) })"
            :confirm-label="t('proposals.groups.reject.confirm')"
            :processing="processing"
            @cancel="rejecting = false"
            @confirm="reject"
        >
            <p>{{ t('proposals.groups.reject.text') }}</p>
            <div class="mt-2 flex flex-col gap-1.5">
                <label
                    for="reject-reason"
                    class="text-sm font-medium text-ink"
                >{{ t('proposals.groups.reject.reason') }}</label>
                <textarea
                    id="reject-reason"
                    v-model="reason"
                    rows="3"
                    maxlength="1000"
                    class="rounded-[10px] border border-line-strong bg-white px-3 py-2 text-[15px] focus-visible:outline-2 focus-visible:outline-accent"
                    :aria-invalid="reasonError !== undefined"
                    aria-describedby="reject-reason-help"
                />
                <p
                    id="reject-reason-help"
                    class="text-[13px] text-muted"
                >
                    {{ t('proposals.groups.reject.reason_help') }}
                </p>
                <p
                    v-if="reasonError"
                    class="text-sm text-accent"
                >
                    {{ reasonError }}
                </p>
            </div>
        </ConfirmDialog>
    </AppLayout>
</template>
