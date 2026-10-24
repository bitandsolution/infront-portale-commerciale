<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { buttonClasses } from '@/components/buttons';
import { useTrans } from '@/composables/useTrans';
import AuthLayout from '@/layouts/AuthLayout.vue';

/**
 * Expired, used or unknown access link (D-277): the same page in every case, so it never says
 * whether an account exists.
 */
defineProps<{ loginUrl: string }>();

const { t } = useTrans();
</script>

<template>
    <AuthLayout :title="t('access.invalid_title')">
        <p class="mt-2 text-sm leading-relaxed text-muted">
            {{ t('access.invalid_message') }}
        </p>
        <Link
            :href="loginUrl"
            :class="buttonClasses.secondary"
            class="mt-6 w-full"
        >
            {{ t('access.to_login') }}
        </Link>
    </AuthLayout>
</template>
