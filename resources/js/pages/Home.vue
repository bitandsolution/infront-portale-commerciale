<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ToastRegion from '@/components/ToastRegion.vue';
import { useTrans } from '@/composables/useTrans';

defineProps<{
    title: string;
    subtitle: string;
    /** Link to the layout preview, local environment only (D-264). */
    previewUrl: string | null;
    /** Link to the development access, local environment only (D-268). */
    devLoginUrl: string | null;
}>();

const { t } = useTrans();

const link = 'inline-flex min-h-11 items-center rounded-[10px] px-5 text-[15px] font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
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
            <div
                v-if="devLoginUrl || previewUrl"
                class="mt-6 flex flex-wrap justify-center gap-3"
            >
                <Link
                    v-if="devLoginUrl"
                    :href="devLoginUrl"
                    :class="[link, 'bg-accent text-white hover:bg-accent-strong']"
                >
                    {{ t('dev_login.open') }}
                </Link>
                <Link
                    v-if="previewUrl"
                    :href="previewUrl"
                    :class="[link, 'border border-line-strong bg-white text-ink hover:bg-surface']"
                >
                    {{ t('preview.open') }}
                </Link>
            </div>
        </div>
        <ToastRegion />
    </main>
</template>
