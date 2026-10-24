<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, useId } from 'vue';
import type { Option } from '@/types';

/**
 * Labelled select with its error message under the field (ux-architettura §4.5).
 * Native control, so it works with touch and keyboard; touch area at least 44 px.
 */
const props = withDefaults(
    defineProps<{
        label: string;
        options: Option[];
        error?: string;
        disabled?: boolean;
        /** Optional first entry with an empty value (e.g. "Tutti i ruoli"). */
        placeholder?: string;
    }>(),
    {
        error: undefined,
        disabled: false,
        placeholder: undefined,
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
        <div class="relative">
            <select
                :id="id"
                v-model="model"
                :disabled="disabled"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="errorId"
                class="min-h-11 w-full appearance-none rounded-[10px] border bg-white py-2 pr-10 pl-3.5 text-[15px] text-ink outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 disabled:cursor-not-allowed disabled:bg-surface disabled:text-muted"
                :class="error ? 'border-accent' : 'border-line-strong'"
            >
                <option
                    v-if="placeholder !== undefined"
                    value=""
                >
                    {{ placeholder }}
                </option>
                <option
                    v-for="option in options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <ChevronDown
                :size="16"
                :stroke-width="2"
                class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-muted"
                aria-hidden="true"
            />
        </div>
        <p
            v-if="error"
            :id="errorId"
            class="text-[13px] leading-snug text-accent"
        >
            {{ error }}
        </p>
    </div>
</template>
