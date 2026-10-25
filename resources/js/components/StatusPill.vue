<script setup lang="ts">
import type { StatusTone } from '@/types';

/**
 * Status as text in a pill, never by colour alone (ux-architettura §4.5, "Accessibilità").
 */

defineProps<{ label: string; tone: StatusTone }>();

const classes: Record<StatusTone, string> = {
    success: 'bg-success-soft text-success',
    notice: 'bg-notice-soft text-notice',
    muted: 'bg-surface text-muted',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[13px] font-medium whitespace-nowrap"
        :class="classes[tone]"
    >
        {{ label }}
    </span>
</template>
