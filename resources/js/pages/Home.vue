<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useTrans } from '@/composables/useTrans';

defineProps<{
    title: string;
    subtitle: string;
    /** Link to the layout preview, local environment only (D-264). */
    previewUrl: string | null;
}>();

const { t } = useTrans();
</script>

<template>
    <Head :title="title" />
    <main class="flex min-h-screen items-center justify-center bg-surface p-6">
        <div class="text-center">
            <h1 class="text-2xl font-semibold">
                {{ title }}
            </h1>
            <p class="mt-2 text-muted">
                {{ subtitle }}
            </p>
            <Link
                v-if="previewUrl"
                :href="previewUrl"
                class="mt-6 inline-flex min-h-11 items-center rounded-[10px] bg-accent px-5 text-[15px] font-semibold text-white hover:bg-accent-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
            >
                {{ t('preview.open') }}
            </Link>
        </div>
    </main>
</template>
