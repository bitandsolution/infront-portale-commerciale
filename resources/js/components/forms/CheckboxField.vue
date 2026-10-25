<script setup lang="ts">
import { computed, useId } from 'vue';

/**
 * Labelled checkbox with an optional help text and its error message (ux-architettura §4.5).
 * The whole row is the touch area (at least 44 px).
 */
const props = withDefaults(
    defineProps<{
        label: string;
        help?: string;
        error?: string;
        disabled?: boolean;
    }>(),
    { help: undefined, error: undefined, disabled: false },
);

const model = defineModel<boolean>({ required: true });

const id = useId();
const describedBy = computed(() => [props.help ? `${id}-help` : '', props.error ? `${id}-error` : ''].filter(Boolean).join(' ') || undefined);
</script>

<template>
    <div class="flex flex-col gap-1">
        <label
            :for="id"
            class="flex min-h-11 cursor-pointer items-center gap-3 text-[15px] text-ink"
            :class="disabled ? 'cursor-not-allowed opacity-70' : ''"
        >
            <input
                :id="id"
                v-model="model"
                type="checkbox"
                :disabled="disabled"
                :aria-describedby="describedBy"
                class="size-5 shrink-0 rounded border-line-strong accent-accent"
            >
            <span>{{ label }}</span>
        </label>
        <p
            v-if="help"
            :id="`${id}-help`"
            class="pl-8 text-[13px] leading-snug text-muted"
        >
            {{ help }}
        </p>
        <p
            v-if="error"
            :id="`${id}-error`"
            class="pl-8 text-[13px] leading-snug text-accent"
        >
            {{ error }}
        </p>
    </div>
</template>
