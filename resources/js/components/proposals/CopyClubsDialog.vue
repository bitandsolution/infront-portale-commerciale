<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import SelectField from '@/components/forms/SelectField.vue';
import { useTrans } from '@/composables/useTrans';

/**
 * "Copia su altri club" at step 4 (M06 §5, M06-CA7; F4-05; mockup "Configurazione per club"): the
 * agent chooses the club to copy (the first open one by default) and the clubs that receive its
 * configuration (all the others by default). What can be copied is decided by the server
 * (CopyClubConfiguration with DraftEvaluator), which reports the skipped options club by club.
 */
const props = defineProps<{
    open: boolean;
    clubs: Array<{ id: number; name: string; summary: string }>;
    initialSource: number | null;
    processing: boolean;
}>();

const emit = defineEmits<{
    confirm: [sourceId: number, targetIds: number[]];
    cancel: [];
}>();

const { t } = useTrans();

const source = ref('');
const targets = ref<number[]>([]);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }
        const first = props.clubs.find((club) => club.id === props.initialSource) ?? props.clubs[0];
        source.value = first ? String(first.id) : '';
        targets.value = props.clubs.filter((club) => String(club.id) !== source.value).map((club) => club.id);
    },
);

// A new source is never also a target.
watch(source, (value) => {
    targets.value = props.clubs.filter((club) => String(club.id) !== value).map((club) => club.id);
});

const sourceOptions = computed(() => props.clubs.map((club) => ({ value: String(club.id), label: club.name })));
const sourceSummary = computed(() => props.clubs.find((club) => String(club.id) === source.value)?.summary ?? '');
const others = computed(() => props.clubs.filter((club) => String(club.id) !== source.value));

function toggle(clubId: number): void {
    targets.value = targets.value.includes(clubId) ? targets.value.filter((id) => id !== clubId) : [...targets.value, clubId];
}

function confirm(): void {
    if (source.value !== '' && targets.value.length > 0) {
        emit('confirm', Number(source.value), targets.value);
    }
}
</script>

<template>
    <ConfirmDialog
        :open="open"
        :title="t('proposals.copy.dialog_title')"
        :confirm-label="t('proposals.copy.confirm')"
        :processing="processing"
        :confirm-disabled="source === '' || targets.length === 0"
        @cancel="emit('cancel')"
        @confirm="confirm"
    >
        <p>{{ t('proposals.copy.dialog_intro') }}</p>
        <div class="mt-2 flex flex-col gap-1">
            <SelectField
                v-model="source"
                :label="t('proposals.copy.source')"
                :options="sourceOptions"
            />
            <p class="text-[13px] text-muted">
                {{ sourceSummary }}
            </p>
        </div>
        <fieldset class="mt-2 flex flex-col gap-1">
            <legend class="mb-1 text-sm font-medium text-ink">
                {{ t('proposals.copy.targets') }}
            </legend>
            <ul class="flex max-h-60 flex-col divide-y divide-line overflow-y-auto rounded-[10px] border border-line">
                <li
                    v-for="club in others"
                    :key="club.id"
                >
                    <label class="flex min-h-12 cursor-pointer items-center gap-3 px-3 py-2 hover:bg-surface">
                        <input
                            type="checkbox"
                            class="size-5 shrink-0 accent-accent"
                            :checked="targets.includes(club.id)"
                            :aria-label="t('proposals.copy.target', { club: club.name })"
                            @change="toggle(club.id)"
                        >
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-ink">{{ club.name }}</span>
                            <span class="block truncate text-[13px] text-muted">{{ club.summary }}</span>
                        </span>
                    </label>
                </li>
            </ul>
        </fieldset>
    </ConfirmDialog>
</template>
