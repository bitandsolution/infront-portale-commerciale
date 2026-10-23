<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Copy, Download, TriangleAlert } from '@lucide/vue';
import { ref } from 'vue';
import { useTrans } from '@/composables/useTrans';
import AuthLayout from '@/layouts/AuthLayout.vue';

/**
 * The 8 single-use recovery codes (D-214), shown once right after the setup of the second
 * factor: reloading the page does not show them again. New codes come with a new setup, after
 * the admin resets the second factor (D-140, PR-04).
 */
const props = defineProps<{
    codes: string[];
    continueUrl: string;
}>();

const { t } = useTrans();

const saved = ref(false);
const copied = ref(false);

function asText(): string {
    return [t('auth.recovery.file_header'), '', ...props.codes, ''].join('\n');
}

async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(asText());
        copied.value = true;
        window.setTimeout(() => (copied.value = false), 2500);
    } catch {
        copied.value = false;
    }
}

function download(): void {
    const url = URL.createObjectURL(new Blob([asText()], { type: 'text/plain;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = t('auth.recovery.file_name');
    link.click();
    URL.revokeObjectURL(url);
}

function proceed(): void {
    router.visit(props.continueUrl);
}
</script>

<template>
    <AuthLayout
        :title="t('auth.recovery.title')"
        wide
    >
        <p class="mt-2 text-sm leading-relaxed text-muted">
            {{ t('auth.recovery.intro') }}
        </p>

        <p
            role="note"
            class="mt-4 flex items-start gap-2.5 rounded-[10px] border border-notice-line bg-notice-soft px-3.5 py-2.5 text-[13px] leading-snug font-medium text-notice"
        >
            <TriangleAlert
                :size="18"
                :stroke-width="2"
                class="mt-px shrink-0"
                aria-hidden="true"
            />
            {{ t('auth.recovery.warning') }}
        </p>

        <ul class="mt-5 grid grid-cols-1 gap-2 rounded-xl bg-surface p-4 font-mono text-[15px] tracking-wide sm:grid-cols-2">
            <li
                v-for="code in codes"
                :key="code"
                class="rounded-md bg-white px-3 py-2 text-center select-all"
            >
                {{ code }}
            </li>
        </ul>

        <div class="mt-4 flex flex-wrap gap-2">
            <button
                type="button"
                class="inline-flex min-h-11 items-center gap-2 rounded-[10px] border border-line-strong bg-white px-4 text-sm font-medium text-ink hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                @click="copy"
            >
                <component
                    :is="copied ? Check : Copy"
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ copied ? t('auth.recovery.copied') : t('auth.recovery.copy') }}
            </button>
            <button
                type="button"
                class="inline-flex min-h-11 items-center gap-2 rounded-[10px] border border-line-strong bg-white px-4 text-sm font-medium text-ink hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                @click="download"
            >
                <Download
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('auth.recovery.download') }}
            </button>
        </div>

        <div class="mt-6 flex flex-col gap-4 border-t border-line pt-5">
            <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm font-medium">
                <input
                    v-model="saved"
                    type="checkbox"
                    class="size-5 accent-accent"
                >
                {{ t('auth.recovery.confirm') }}
            </label>
            <button
                type="button"
                class="inline-flex min-h-11 items-center justify-center rounded-[10px] bg-accent px-5 text-[15px] font-semibold text-white hover:bg-accent-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="!saved"
                @click="proceed"
            >
                {{ t('auth.recovery.continue') }}
            </button>
        </div>
    </AuthLayout>
</template>
