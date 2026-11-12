<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Download, FileText, RefreshCw } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import StatusPill from '@/components/StatusPill.vue';
import { useTrans } from '@/composables/useTrans';
import type { GroupDocument } from '@/types';

/**
 * PDF document of an approved group (M09; DM-04): status, download, regeneration by the admin and
 * the replaced documents (admin only, D-205). While the document is queued or being generated the
 * page asks the server again every few seconds (prop "group" only).
 */
const props = defineProps<{ document: GroupDocument }>();

const { t } = useTrans();

const POLL_MILLISECONDS = 4000;
let pollTimer: ReturnType<typeof setTimeout> | undefined;

function schedulePoll(): void {
    clearTimeout(pollTimer);

    if (!props.document.pending) {
        return;
    }

    pollTimer = setTimeout(() => {
        router.reload({ only: ['group'], onFinish: schedulePoll });
    }, POLL_MILLISECONDS);
}

watch(() => props.document.pending, schedulePoll, { immediate: true });
onBeforeUnmount(() => clearTimeout(pollTimer));

const confirming = ref(false);
const processing = ref(false);

function regenerate(): void {
    processing.value = true;
    router.post(props.document.regenerateUrl, {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            confirming.value = false;
        },
    });
}
</script>

<template>
    <section class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <h2 class="flex items-center gap-2 text-base font-semibold text-ink">
                    <FileText
                        :size="18"
                        :stroke-width="2"
                        aria-hidden="true"
                    />
                    {{ t('proposals.document.title') }}
                </h2>
                <StatusPill
                    v-if="document.current"
                    :label="document.current.statusLabel"
                    :tone="document.current.statusTone"
                />
            </div>
            <div class="flex flex-wrap gap-2">
                <a
                    v-if="document.current?.downloadUrl"
                    :href="document.current.downloadUrl"
                    :class="buttonClasses.primary"
                >
                    <Download
                        :size="18"
                        :stroke-width="2"
                        aria-hidden="true"
                    />
                    {{ t('proposals.document.download') }}
                </a>
                <button
                    v-if="document.canRegenerate"
                    type="button"
                    :class="buttonClasses.secondary"
                    @click="confirming = true"
                >
                    <RefreshCw
                        :size="18"
                        :stroke-width="2"
                        aria-hidden="true"
                    />
                    {{ t('proposals.document.regenerate') }}
                </button>
            </div>
        </div>

        <p
            v-if="!document.current"
            class="text-sm text-muted"
        >
            {{ t('proposals.document.none') }}
        </p>
        <p
            v-else-if="document.pending"
            class="text-sm text-muted"
            role="status"
        >
            {{ t('proposals.document.pending') }}
        </p>
        <p
            v-else-if="document.current.status === 'ready'"
            class="text-sm text-ink"
        >
            {{ t('proposals.document.ready', { type: document.current.type, pages: String(document.current.pages ?? ''), size: document.current.size ?? '', at: document.current.at ?? '' }) }}
            <span
                v-if="document.current.fileName"
                class="block text-muted"
            >{{ document.current.fileName }}</span>
        </p>
        <div
            v-else-if="document.current.status === 'failed'"
            class="flex flex-col gap-1 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
            role="alert"
        >
            <p>{{ t('proposals.document.failed') }}</p>
            <p
                v-if="document.current.error"
                class="text-muted"
            >
                {{ t('proposals.document.failed_reason', { reason: document.current.error }) }}
            </p>
        </div>

        <div
            v-if="document.previous.length > 0"
            class="flex flex-col gap-2"
        >
            <h3 class="text-sm font-semibold text-ink">
                {{ t('proposals.document.previous_title') }}
            </h3>
            <ul class="flex flex-col gap-1 text-sm">
                <li
                    v-for="entry in document.previous"
                    :key="entry.id"
                    class="flex flex-wrap items-center gap-2"
                >
                    <span class="text-muted">{{ t('proposals.document.previous_entry', { status: entry.statusLabel, at: entry.at ?? '' }) }}</span>
                    <a
                        v-if="entry.downloadUrl"
                        :href="entry.downloadUrl"
                        class="font-medium text-accent hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                    >{{ entry.fileName ?? t('proposals.document.download') }}</a>
                </li>
            </ul>
        </div>

        <ConfirmDialog
            :open="confirming"
            :title="t('proposals.document.regenerate_title')"
            :confirm-label="t('proposals.document.regenerate')"
            :processing="processing"
            @cancel="confirming = false"
            @confirm="regenerate"
        >
            <p>{{ t('proposals.document.regenerate_text') }}</p>
        </ConfirmDialog>
    </section>
</template>
