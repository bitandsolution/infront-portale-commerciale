<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import ToastRegion from '@/components/ToastRegion.vue';
import { useTrans } from '@/composables/useTrans';

/**
 * Layout of the pages before the portal (PR-03): login, second factor, setup and recovery codes.
 * One centred card, readable from smartphone to desktop (ux-architettura §4.5).
 */
withDefaults(defineProps<{ title: string; wide?: boolean }>(), { wide: false });

const { t } = useTrans();
</script>

<template>
    <Head :title="title" />

    <main class="flex min-h-dvh flex-col items-center justify-center bg-surface px-4 py-10">
        <div
            class="w-full"
            :class="wide ? 'max-w-xl' : 'max-w-md'"
        >
            <div class="mb-6 flex items-baseline justify-center gap-3">
                <AppLogo />
                <span class="text-[15px] font-medium text-ink-soft">{{ t('app_name') }}</span>
            </div>

            <section class="rounded-[14px] border border-line bg-white p-6 shadow-sm sm:p-8">
                <h1 class="text-xl font-semibold">
                    {{ title }}
                </h1>
                <slot />
            </section>
        </div>

        <ToastRegion />
    </main>
</template>
