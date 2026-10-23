<script setup lang="ts">
import { Construction } from '@lucide/vue';
import EmptyState from '@/components/states/EmptyState.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData } from '@/types';

/**
 * Menu page not built yet (PR-02, D-269): the server already checked the area permission;
 * the slice that builds the function replaces this page and keeps the route.
 */
defineProps<{ layout: LayoutData }>();

const { t } = useTrans();
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-5xl flex-col gap-5 p-4 md:p-6">
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
