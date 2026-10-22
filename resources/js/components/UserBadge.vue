<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import type { LayoutData } from '@/types';

defineProps<{ user: LayoutData['user'] }>();

const { t } = useTrans();
</script>

<template>
    <div
        class="flex items-center gap-3"
        role="group"
        :aria-label="t('user.area')"
    >
        <div class="hidden text-right text-[13px] leading-tight sm:block">
            <div class="font-semibold">
                {{ user.name }}
            </div>
            <div class="text-muted">
                {{ user.role }}
            </div>
        </div>
        <div
            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-navy text-[13px] font-semibold text-white"
            aria-hidden="true"
        >
            {{ user.initials }}
        </div>
    </div>
</template>
