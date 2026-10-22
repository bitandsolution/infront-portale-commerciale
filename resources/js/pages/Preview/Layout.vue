<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import EmptyState from '@/components/states/EmptyState.vue';
import ErrorState from '@/components/states/ErrorState.vue';
import LoadingState from '@/components/states/LoadingState.vue';
import { useToasts } from '@/composables/useToasts';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData } from '@/types';

/**
 * Layout preview (PR-01, D-264): local and testing environments only.
 * Lets Antonio check the menu of each role, the breakpoints and the shared states before users exist.
 */
defineProps<{
    layout: LayoutData;
    roles: { slug: string; label: string; href: string; current: boolean }[];
    errorUrl: string;
}>();

const { t } = useTrans();
const { show } = useToasts();

type DemoState = 'content' | 'empty' | 'loading' | 'error';
const states: { value: DemoState; label: string }[] = [
    { value: 'content', label: 'preview.state_content' },
    { value: 'empty', label: 'preview.state_empty' },
    { value: 'loading', label: 'preview.state_loading' },
    { value: 'error', label: 'preview.state_error' },
];
const state = ref<DemoState>('content');

function retry(): void {
    state.value = 'loading';
    window.setTimeout(() => {
        state.value = 'content';
    }, 1500);
}

const sending = ref(false);

function simulateServerError(errorUrl: string): void {
    router.post(errorUrl, {}, {
        preserveScroll: true,
        onStart: () => {
            sending.value = true;
        },
        onFinish: () => {
            sending.value = false;
        },
    });
}

const segment = 'flex min-h-11 items-center justify-center rounded-[10px] px-4 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
const segmentIdle = 'border border-line-strong bg-white text-ink hover:bg-surface';
const segmentCurrent = 'bg-ink text-white';
const button = 'flex min-h-11 items-center justify-center rounded-[10px] border border-line-strong bg-white px-4 text-sm font-semibold text-ink hover:bg-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-60';
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-5xl flex-col gap-5 p-4 md:p-6">
            <p
                role="note"
                class="rounded-xl border border-notice-line bg-notice-soft px-4 py-3 text-sm leading-relaxed text-notice"
            >
                <strong>{{ t('preview.title') }}.</strong> {{ t('preview.notice') }}
            </p>

            <section class="flex flex-col gap-2">
                <h2 class="text-xs font-semibold tracking-[0.6px] text-muted uppercase">
                    {{ t('preview.role') }}
                </h2>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="role in roles"
                        :key="role.slug"
                        :href="role.href"
                        :class="[segment, role.current ? segmentCurrent : segmentIdle]"
                        :aria-current="role.current ? 'page' : undefined"
                    >
                        {{ role.label }}
                    </Link>
                </div>
            </section>

            <div class="grid gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <section class="flex flex-col gap-3">
                    <h2 class="text-xs font-semibold tracking-[0.6px] text-muted uppercase">
                        {{ t('preview.states_title') }}
                    </h2>
                    <div
                        class="grid grid-cols-2 gap-2 sm:grid-cols-4"
                        role="group"
                        :aria-label="t('preview.states_title')"
                    >
                        <button
                            v-for="item in states"
                            :key="item.value"
                            type="button"
                            :class="[segment, state === item.value ? segmentCurrent : segmentIdle]"
                            :aria-pressed="state === item.value"
                            @click="state = item.value"
                        >
                            {{ t(item.label) }}
                        </button>
                    </div>

                    <div class="min-h-72 rounded-[14px] border border-line bg-white p-5">
                        <div
                            v-if="state === 'content'"
                            class="flex flex-col gap-2"
                        >
                            <h2 class="text-xl font-semibold">
                                {{ layout.title }}
                            </h2>
                            <p class="text-[15px] leading-relaxed text-muted">
                                {{ t('preview.content_text') }}
                            </p>
                        </div>
                        <EmptyState
                            v-else-if="state === 'empty'"
                            :title="t('preview.empty_title')"
                            :message="t('preview.empty_message')"
                        >
                            <template #action>
                                <button
                                    type="button"
                                    class="flex h-[52px] items-center rounded-[10px] bg-accent px-6 text-[15px] font-semibold text-white hover:bg-accent-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                                >
                                    {{ t('preview.empty_action') }}
                                </button>
                            </template>
                        </EmptyState>
                        <LoadingState
                            v-else-if="state === 'loading'"
                            :rows="6"
                        />
                        <ErrorState
                            v-else
                            @retry="retry"
                        />
                    </div>
                </section>

                <section class="flex flex-col gap-3">
                    <h2 class="text-xs font-semibold tracking-[0.6px] text-muted uppercase">
                        {{ t('preview.toast_title') }}
                    </h2>
                    <div class="flex flex-col gap-2 rounded-[14px] border border-line bg-white p-5">
                        <button
                            type="button"
                            :class="button"
                            :disabled="sending"
                            @click="simulateServerError(errorUrl)"
                        >
                            {{ t('preview.toast_server') }}
                        </button>
                        <button
                            type="button"
                            :class="button"
                            @click="show('error', t('errors.offline'))"
                        >
                            {{ t('preview.toast_offline') }}
                        </button>
                        <button
                            type="button"
                            :class="button"
                            @click="show('success', t('preview.toast_success_message'))"
                        >
                            {{ t('preview.toast_success') }}
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
