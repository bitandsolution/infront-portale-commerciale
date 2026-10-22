<script setup lang="ts">
import { Inbox } from '@lucide/vue';

/** Empty state (ux-architettura §4.5): short message and the primary action, passed in the "action" slot. */
defineProps<{
    title: string;
    message?: string;
}>();
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-3.5 px-6 py-10 text-center">
        <div class="flex size-[72px] items-center justify-center rounded-full bg-accent-soft text-accent">
            <slot name="icon">
                <Inbox
                    :size="34"
                    :stroke-width="1.6"
                    aria-hidden="true"
                />
            </slot>
        </div>
        <h2 class="text-[22px] font-semibold">
            {{ title }}
        </h2>
        <p
            v-if="message"
            class="max-w-[360px] text-[15px] leading-relaxed text-muted"
        >
            {{ message }}
        </p>
        <slot name="action" />
    </div>
</template>
