<script setup lang="ts">
import { AlertTriangle, Save, Undo2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import SelectField from '@/components/forms/SelectField.vue';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import type { ProposalRevisionMode } from '@/types';

/**
 * Box "Nuova versione" at step 5 of the configurator, while the admin changes an approved variant
 * (M07 FL-04; D-464…D-467; F5-04): differences from the current version, discount of the new version
 * with the final total calculated by the server (the page asks it again with a partial reload,
 * "preview"), what blocks the save (the check on the difference, D-465) and the forcing of the admin
 * with its reason. The parent saves the pending changes before every request ("preview", "save",
 * "discard").
 */
const props = defineProps<{
    revision: ProposalRevisionMode;
    number: number;
    processing: boolean;
    errors: Record<string, string | undefined>;
}>();

const emit = defineEmits<{
    preview: [discount: { sconto_tipo: string; sconto: string }];
    save: [payload: { discount_type: string; discount_value: string; forced_reason: string }];
    discard: [];
}>();

const { t } = useTrans();

const discountType = ref(props.revision.discountType);
const discountValue = ref(props.revision.discountValue);
const forcedReason = ref('');
const confirmingDiscard = ref(false);
let previewTimer: ReturnType<typeof setTimeout> | undefined;

const discountTypes = computed(() => [
    { value: '', label: t('proposals.approval.discount_none') },
    { value: 'percent', label: t('proposals.approval.discount_percent') },
    { value: 'amount', label: t('proposals.approval.discount_amount') },
]);

const blocked = computed(() => props.revision.problems.length > 0 && !props.revision.canForce);
const forcing = computed(() => props.revision.problems.length > 0 && props.revision.canForce);
const discountError = computed(() => props.errors.discount_value ?? props.revision.discountError ?? undefined);

watch([discountType, discountValue], () => {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(() => emit('preview', { sconto_tipo: discountType.value, sconto: discountValue.value }), 500);
});

onBeforeUnmount(() => clearTimeout(previewTimer));

function save(): void {
    clearTimeout(previewTimer);
    emit('save', {
        discount_type: discountType.value,
        discount_value: discountType.value === '' ? '' : discountValue.value,
        forced_reason: forcing.value ? forcedReason.value : '',
    });
}
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-[14px] border border-accent/40 bg-white p-4 md:p-5"
        aria-labelledby="new-version-title"
    >
        <div class="flex flex-col gap-1">
            <h3
                id="new-version-title"
                class="text-base font-semibold text-ink"
            >
                {{ t('proposals.revision.box_title', { next: String(revision.nextNumber) }) }}
            </h3>
            <p class="text-sm text-ink-soft">
                {{ t('proposals.revision.box_intro', { current: String(revision.versionNumber) }) }}
            </p>
        </div>

        <div class="rounded-[10px] bg-surface px-3.5 py-3 text-sm">
            <p class="font-medium text-ink">
                {{ t('proposals.revision.current', { number: String(revision.versionNumber) }) }}
            </p>
            <p class="text-ink-soft">
                {{ t('proposals.revision.current_line', { list: revision.current.listTotal, discount: revision.current.discount, final: revision.current.finalTotal }) }}
            </p>
        </div>

        <div class="flex flex-col gap-1.5">
            <h4 class="text-sm font-semibold text-ink">
                {{ t('proposals.revision.changes_title', { number: String(revision.versionNumber) }) }}
            </h4>
            <ul
                v-if="revision.changes.length > 0"
                class="flex list-disc flex-col gap-1 pl-5 text-sm text-ink"
            >
                <li
                    v-for="(change, index) in revision.changes"
                    :key="index"
                >
                    {{ change }}
                </li>
            </ul>
            <p
                v-else
                class="text-sm text-muted"
            >
                {{ t('proposals.revision.no_changes') }}
            </p>
        </div>

        <div class="flex flex-col gap-3">
            <h4 class="text-sm font-semibold text-ink">
                {{ t('proposals.approval.discount_title') }}
            </h4>
            <div class="grid gap-3 sm:grid-cols-2">
                <SelectField
                    v-model="discountType"
                    :label="t('proposals.approval.discount_type')"
                    :options="discountTypes"
                />
                <div class="flex flex-col gap-1">
                    <TextField
                        v-model="discountValue"
                        :label="t('proposals.approval.discount_value')"
                        :disabled="discountType === ''"
                        :error="discountError"
                        inputmode="numeric"
                        mono
                    />
                    <p class="text-[13px] text-muted">
                        {{ t('proposals.approval.discount_value_help') }}
                    </p>
                </div>
            </div>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                <dt class="text-muted">
                    {{ t('proposals.revision.list_total') }}
                </dt>
                <dd class="font-medium text-ink">
                    € {{ revision.listTotal }}
                </dd>
                <template v-if="revision.preview">
                    <dt class="text-muted">
                        {{ t('proposals.approval.discount') }}
                    </dt>
                    <dd class="text-ink">
                        {{ revision.preview.discount }}
                    </dd>
                    <dt class="text-muted">
                        {{ t('proposals.revision.final_total') }}
                    </dt>
                    <dd class="font-semibold text-ink">
                        € {{ revision.preview.finalTotal }}
                    </dd>
                </template>
            </dl>
        </div>

        <div
            v-if="revision.problems.length > 0"
            class="flex gap-2.5 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
            role="status"
        >
            <AlertTriangle
                :size="18"
                :stroke-width="2"
                class="mt-0.5 shrink-0 text-notice"
                aria-hidden="true"
            />
            <div class="flex flex-col gap-1">
                <p class="font-semibold">
                    {{ t('proposals.revision.problems_title') }}
                </p>
                <ul class="flex list-disc flex-col gap-1 pl-5">
                    <li
                        v-for="(problem, index) in revision.problems"
                        :key="index"
                    >
                        {{ problem }}
                    </li>
                </ul>
                <p
                    v-if="revision.forceable"
                    class="text-muted"
                >
                    {{ revision.canForce ? t('proposals.revision.force_hint') : t('proposals.revision.force_admin_only') }}
                </p>
            </div>
        </div>

        <div
            v-if="forcing"
            class="flex flex-col gap-1.5"
        >
            <ul class="flex list-disc flex-col gap-1 pl-5 text-sm text-ink">
                <li
                    v-for="(need, index) in revision.forceNeeds"
                    :key="index"
                >
                    {{ t('proposals.approval.force_need', { ...need }) }}
                </li>
            </ul>
            <label
                for="version-forced-reason"
                class="text-sm font-medium text-ink"
            >{{ t('proposals.revision.force_reason') }}</label>
            <textarea
                id="version-forced-reason"
                v-model="forcedReason"
                rows="3"
                maxlength="1000"
                class="rounded-[10px] border border-line-strong bg-white px-3 py-2 text-[15px] focus-visible:outline-2 focus-visible:outline-accent"
                :aria-invalid="errors.forced_reason !== undefined"
                aria-describedby="version-forced-reason-help"
            />
            <p
                id="version-forced-reason-help"
                class="text-[13px] text-muted"
            >
                {{ t('proposals.revision.force_reason_help', { min: String(revision.forcedReasonMin) }) }}
            </p>
            <p
                v-if="errors.forced_reason"
                class="text-sm text-accent"
            >
                {{ errors.forced_reason }}
            </p>
        </div>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
            <button
                type="button"
                :class="buttonClasses.secondary"
                :disabled="processing"
                @click="confirmingDiscard = true"
            >
                <Undo2
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('proposals.revision.discard') }}
            </button>
            <button
                type="button"
                :class="forcing ? buttonClasses.danger : buttonClasses.primary"
                :disabled="processing || blocked || revision.blocking.length > 0"
                @click="save"
            >
                <Save
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ forcing ? t('proposals.revision.save_forced', { next: String(revision.nextNumber) }) : t('proposals.revision.save', { next: String(revision.nextNumber) }) }}
            </button>
        </div>

        <ConfirmDialog
            :open="confirmingDiscard"
            :title="t('proposals.revision.discard_title', { number: String(number) })"
            :confirm-label="t('proposals.revision.discard_confirm')"
            :processing="processing"
            @cancel="confirmingDiscard = false"
            @confirm="emit('discard')"
        >
            <p>{{ t('proposals.revision.discard_text', { current: String(revision.versionNumber) }) }}</p>
        </ConfirmDialog>
    </section>
</template>
