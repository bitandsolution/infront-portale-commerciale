<script setup lang="ts">
import {
    ChartColumn,
    FileText,
    House,
    Images,
    Plus,
    ScrollText,
    Settings,
    Tags,
    Trophy,
    UserCog,
    UserRound,
    Users,
    Warehouse,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';

/** Icons of the side menu (D-266), by the name sent by app/Support/Navigation/NavigationMenu.php. */
const icons: Record<string, Component> = {
    ChartColumn,
    FileText,
    House,
    Images,
    Plus,
    ScrollText,
    Settings,
    Tags,
    Trophy,
    UserCog,
    UserRound,
    Users,
    Warehouse,
};

const props = defineProps<{ name: string }>();

const icon = computed(() => icons[props.name] ?? FileText);
</script>

<template>
    <component
        :is="icon"
        :size="22"
        :stroke-width="1.8"
        aria-hidden="true"
    />
</template>
