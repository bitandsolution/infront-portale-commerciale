<script setup lang="ts">
import { Minus, Plus } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Stepper − value + for touch screens (D-157: areas of at least 44 px; mockup "Configurazione per
 * club"): the value moves by step between min and max, never typed. The text of the value is given
 * by the parent (minutes as "1′30″", seats as a number).
 */
const props = defineProps<{
    modelValue: number;
    min: number;
    max: number;
    step: number;
    text: string;
    label: string;
    decreaseLabel: string;
    increaseLabel: string;
    describedBy?: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: number] }>();

const canDecrease = computed(() => !props.disabled && props.modelValue - props.step >= props.min);
// A value already beyond the maximum (residual lowered meanwhile) can only go down.
const canIncrease = computed(() => !props.disabled && props.modelValue + props.step <= props.max);

function change(delta: number): void {
    const next = props.modelValue + delta;
    if ((delta < 0 && canDecrease.value) || (delta > 0 && canIncrease.value)) {
        emit('update:modelValue', next);
    }
}

const buttonClass = 'flex size-11 shrink-0 items-center justify-center rounded-[10px] border border-line-strong bg-white text-ink hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:bg-surface disabled:text-muted';
</script>

<template>
    <div
        class="flex items-center gap-2"
        role="group"
        :aria-label="label"
    >
        <button
            type="button"
            :class="buttonClass"
            :aria-label="decreaseLabel"
            :disabled="!canDecrease"
            @click="change(-step)"
        >
            <Minus
                :size="18"
                :stroke-width="2.2"
                aria-hidden="true"
            />
        </button>
        <output
            class="flex h-11 min-w-20 items-center justify-center rounded-[10px] border border-line px-3 font-mono text-base"
            :aria-describedby="describedBy"
            aria-live="polite"
        >{{ text }}</output>
        <button
            type="button"
            :class="buttonClass"
            :aria-label="increaseLabel"
            :disabled="!canIncrease"
            @click="change(step)"
        >
            <Plus
                :size="18"
                :stroke-width="2.2"
                aria-hidden="true"
            />
        </button>
    </div>
</template>
