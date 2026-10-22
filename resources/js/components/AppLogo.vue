<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';

// Text placeholder until the official Infront logo file arrives: replace only here.
const { t } = useTrans();
</script>

<template>
    <span class="text-[22px] leading-none font-bold tracking-[-0.5px] text-accent select-none">
        {{ t('brand') }}
    </span>
</template>
