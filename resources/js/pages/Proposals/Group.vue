<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CircleX, Pencil, Send } from '@lucide/vue';
import { computed, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DraftSummary from '@/components/proposals/DraftSummary.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, ProposalGroupDetail } from '@/types';

/**
 * Detail of a group of variants (D-411; DM-03a), read only and the same for admin, manager and the
 * agent of the group: header, last rejection (D-412), every variant with the summary of the
 * configurator, the history (M07 F-10). The agent sends the group again from here or from the
 * configurator; admin and manager reject it with an optional reason. Approval arrives with DM-03b.
 */
const props = defineProps<{
    layout: LayoutData;
    group: ProposalGroupDetail;
}>();

const { t } = useTrans();

const many = computed(() => props.group.variants.length > 1);
const letters = computed(() => props.group.variants.map((variant) => variant.letter).filter((letter) => letter !== null).join(', '));
const isRejected = computed(() => props.group.status === 'rejected');
const isSubmitted = computed(() => props.group.status === 'submitted');

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
                    {{ t('proposals.groups.read_only') }}
                </p>

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
                    </div>
                    <DraftSummary
                        v-if="variant.draft"
                        :draft="variant.draft"
                        :client-name="variant.draft.client?.name ?? null"
                        :sector="variant.draft.client?.sector ?? null"
                        read-only
                    />
                    <p
                        v-else
                        class="text-sm text-muted"
                    >
                        {{ t('proposals.groups.no_version') }}
                    </p>
                </article>
            </section>

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
