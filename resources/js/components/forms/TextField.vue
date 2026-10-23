<script setup lang="ts">
import { computed, useId } from 'vue';

/**
 * Labelled text field with its error message under the field (ux-architettura §4.5,
 * "Errori e messaggi"). Touch area at least 44 px.
 */
const props = withDefaults(
    defineProps<{
        label: string;
        type?: string;
        error?: string;
        autocomplete?: string;
        inputmode?: 'text' | 'email' | 'numeric';
        disabled?: boolean;
        autofocus?: boolean;
        mono?: boolean;
    }>(),
    {
        type: 'text',
        error: undefined,
        autocomplete: undefined,
        inputmode: undefined,
        disabled: false,
        autofocus: false,
        mono: false,
    },
);

const model = defineModel<string>({ required: true });

const id = useId();
const errorId = computed(() => (props.error ? `${id}-error` : undefined));
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label
            :for="id"
            class="text-sm font-medium text-ink"
        >
            {{ label }}
        </label>
        <input
            :id="id"
            v-model="model"
            :type="type"
            :autocomplete="autocomplete"
            :inputmode="inputmode"
            :disabled="disabled"
            :autofocus="autofocus"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="errorId"
            class="min-h-11 rounded-[10px] border bg-white px-3.5 text-[15px] text-ink outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 disabled:cursor-not-allowed disabled:bg-surface disabled:text-muted"
            :class="[error ? 'border-accent' : 'border-line-strong', mono ? 'font-mono tracking-[0.2em]' : '']"
        >
        <p
            v-if="error"
            :id="errorId"
            class="text-[13px] leading-snug text-accent"
        >
            {{ error }}
        </p>
    </div>
</template>
