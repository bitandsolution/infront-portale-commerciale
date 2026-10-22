<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Construction } from '@lucide/vue';
import EmptyState from '@/components/states/EmptyState.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData } from '@/types';

/**
 * Menu page not built yet (PR-02, D-269): the server already checked the area permission;
 * the slice that builds the function replaces this page and keeps the route.
 */
defineProps<{
    layout: LayoutData;
    /** Development access, local environment only (D-268). */
    devLoginUrl: string | null;
}>();

const { t } = useTrans();
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-5xl flex-col gap-5 p-4 md:p-6">
            <p
                v-if="devLoginUrl"
                role="note"
                class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-notice-line bg-notice-soft px-4 py-3 text-sm leading-relaxed text-notice"
            >
                <span><strong>{{ t('dev_login.title') }}.</strong> {{ t('dev_login.notice') }}</span>
                <Link
                    :href="devLoginUrl"
                    class="inline-flex min-h-11 items-center font-semibold underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-accent"
                >
                    {{ t('dev_login.change_user') }}
                </Link>
            </p>

            <div class="rounded-[14px] border border-line bg-white">
                <EmptyState
                    :title="t('placeholder.title')"
                    :message="t('placeholder.message')"
                >
                    <template #icon>
                        <Construction
                            :size="34"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                    </template>
                </EmptyState>
            </div>
        </div>
    </AppLayout>
</template>
