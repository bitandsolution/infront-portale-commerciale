<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { useId } from 'vue';
import { useTrans } from '@/composables/useTrans';
import type { LayoutData } from '@/types';

/**
 * Season of the top bar (D-317): native select, so it works with touch and keyboard. The choice is
 * kept in the session on the server and the page reloads with the new season. Disabled when no
 * season exists yet.
 */
const props = defineProps<{ season: LayoutData['season'] }>();

const { t } = useTrans();
const id = useId();

function select(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    if (value !== String(props.season.current)) {
        router.post(props.season.selectUrl, { season_id: Number(value) }, { preserveScroll: true });
    }
}
</script>

<template>
    <div
        class="relative"
        :title="season.enabled ? t('season.select') : t('season.unavailable')"
    >
        <label
            :for="id"
            class="sr-only"
        >{{ t('season.select') }}</label>
        <select
            v-if="season.enabled"
            :id="id"
            :value="String(season.current)"
            class="h-9 min-h-9 appearance-none rounded-full border border-line-strong bg-white py-0 pr-8 pl-3 text-[13px] text-ink-soft outline-none focus:border-accent focus:ring-2 focus:ring-accent/20"
            @change="select"
        >
            <option
                v-for="option in season.options"
                :key="option.value"
                :value="option.value"
            >
                {{ t('season.label', { season: option.label }) }}
            </option>
        </select>
        <button
            v-else
            :id="id"
            type="button"
            disabled
            class="flex h-9 items-center gap-1.5 rounded-full border border-line-strong px-3 text-[13px] text-ink-soft disabled:cursor-not-allowed disabled:opacity-70"
        >
            {{ season.label }}
        </button>
        <ChevronDown
            v-if="season.enabled"
            :size="14"
            :stroke-width="2"
            class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-ink-soft"
            aria-hidden="true"
        />
    </div>
</template>
