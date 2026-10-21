<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    title: string;
    subtitle: string;
}>();
</script>

<template>
    <Head :title="title" />
    <main class="flex min-h-screen items-center justify-center bg-white p-6 text-neutral-900">
        <div class="text-center">
            <h1 class="text-2xl font-semibold">
                {{ title }}
            </h1>
            <p class="mt-2 text-neutral-600">
                {{ subtitle }}
            </p>
        </div>
    </main>
</template>
