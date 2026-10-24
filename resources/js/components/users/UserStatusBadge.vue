<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import type { UserStatus } from '@/types';

/**
 * Account status as text, never by colour alone (ux-architettura §4.5, "Accessibilità").
 */
defineProps<{ status: UserStatus }>();

const { t } = useTrans();

const classes: Record<UserStatus, string> = {
    active: 'bg-success-soft text-success',
    pending: 'bg-notice-soft text-notice',
    inactive: 'bg-surface text-muted',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[13px] font-medium whitespace-nowrap"
        :class="classes[status]"
    >
        {{ t(`users.status.${status}`) }}
    </span>
</template>
