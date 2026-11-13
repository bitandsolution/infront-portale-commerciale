<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CircleAlert, FileImage, Info, Layers, Lock } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CustomSlides from '@/components/proposals/CustomSlides.vue';
import { useTrans } from '@/composables/useTrans';
import { createVisitQueue } from '@/composables/visitQueue';
import type { DocumentSlide, DocumentSlides } from '@/types';

/**
 * Slides of the document of a group (D-397, D-418…D-420; DM-05): the step "Documento" of the
 * configurator for the agent and the detail of the group for admin and manager while it waits.
 *
 * - "Documento completo" / "Solo riepilogo" (the mode is saved, D-418) and one tick per slide, by
 *   section, with its thumbnail; cover and recap always ticked; in "Solo riepilogo" the slides out of
 *   it are shown unticked and locked, and the exclusions come back with "Documento completo".
 * - Every change is saved on the group shortly after the last tick (only the prop "documentSlides"
 *   is reloaded); flush() lets the parent wait for it before sending the group.
 * - The list and the counts come from the server (DocumentComposer); the page only shows what a
 *   tick does until the answer arrives.
 * - DM-07: the slides composed with blocks are listed in their section with their tick, and their
 *   editor (CustomSlides) follows the list; all the saves go one at a time (visitQueue).
 */
const props = withDefaults(defineProps<{
    slides: DocumentSlides;
    /** The detail of the group already shows the missing system slides (D-416). */
    showWarnings?: boolean;
}>(), { showWarnings: true });

const { t } = useTrans();

// The saves of this panel and of the slides composed with blocks go one at a time (DM-07).
const queue = createVisitQueue();
const customPanel = ref<InstanceType<typeof CustomSlides> | null>(null);

const mode = ref(props.slides.mode);
const excluded = ref(new Set(excludedOf(props.slides, false)));
const excludedCustom = ref(new Set(excludedOf(props.slides, true)));
const state = ref<'idle' | 'saving' | 'saved' | 'error' | 'offline'>('idle');

let timer: ReturnType<typeof setTimeout> | undefined;

function excludedOf(slides: DocumentSlides, custom: boolean): number[] {
    return slides.sections.flatMap((section) => section.slides.filter((slide) => slide.custom === custom && slide.excluded).map((slide) => slide.id));
}

// A new answer of the server (another save, the automatic save of the draft) is shown, unless the
// user has ticked something not saved yet.
watch(() => props.slides, (slides) => {
    if (timer === undefined && !queue.busy()) {
        mode.value = slides.mode;
        excluded.value = new Set(excludedOf(slides, false));
        excludedCustom.value = new Set(excludedOf(slides, true));
    }
});

const editable = computed(() => props.slides.editable);
const summaryOnly = computed(() => mode.value === 'summary');

function setOf(slide: DocumentSlide): Set<number> {
    return slide.custom ? excludedCustom.value : excluded.value;
}

function included(slide: DocumentSlide): boolean {
    if (slide.mandatory) {
        return true;
    }
    if (summaryOnly.value && !slide.inSummary) {
        return false;
    }

    return !setOf(slide).has(slide.id);
}

function locked(slide: DocumentSlide): boolean {
    return !editable.value || slide.mandatory || (summaryOnly.value && !slide.inSummary);
}

const allSlides = computed(() => props.slides.sections.flatMap((section) => section.slides));
const includedCount = computed(() => allSlides.value.filter(included).length);
const pageCount = computed(() => allSlides.value.filter(included).reduce((total, slide) => total + slide.pages, 0));

function toggle(slide: DocumentSlide): void {
    if (locked(slide)) {
        return;
    }
    const next = new Set(setOf(slide));
    if (next.has(slide.id)) {
        next.delete(slide.id);
    } else {
        next.add(slide.id);
    }
    if (slide.custom) {
        excludedCustom.value = next;
    } else {
        excluded.value = next;
    }
    schedule();
}

function chooseMode(value: 'complete' | 'summary'): void {
    if (!editable.value || mode.value === value) {
        return;
    }
    mode.value = value;
    schedule();
}

function schedule(): void {
    clearTimeout(timer);
    timer = setTimeout(() => {
        timer = undefined;
        save();
    }, 400);
}

function save(): void {
    state.value = 'saving';
    queue.run('document-slides', (done) => {
        let failed = false;
        router.put(props.slides.saveUrl, {
            mode: mode.value,
            excluded_slide_ids: [...excluded.value],
            excluded_custom_slide_ids: [...excludedCustom.value],
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['documentSlides'],
            onError: () => {
                failed = true;
                state.value = 'error';
            },
            onNetworkError: () => {
                failed = true;
                state.value = 'offline';
                window.addEventListener('online', retry, { once: true });

                return false;
            },
            onFinish: () => {
                if (!failed) {
                    state.value = 'saved';
                }
                done();
            },
        });
    });
}

function retry(): void {
    if (state.value === 'offline') {
        save();
    }
}

/**
 * Runs the callback once the pending choice and the pending changes of the slides composed with
 * blocks are saved (the parent sends or approves the group after it).
 */
function flush(callback: () => void): void {
    if (timer !== undefined) {
        clearTimeout(timer);
        timer = undefined;
        save();
    }
    customPanel.value?.flush();
    queue.whenIdle(callback);
}

defineExpose({ flush });

onBeforeUnmount(() => {
    clearTimeout(timer);
    window.removeEventListener('online', retry);
});

const stateText = computed(() => ({
    idle: '',
    saving: t('proposals.slides.saving'),
    saved: t('proposals.slides.saved'),
    error: t('proposals.slides.error'),
    offline: t('proposals.slides.offline'),
}[state.value]));

const intro = computed(() => {
    if (props.slides.frozen) {
        return t('proposals.slides.frozen');
    }
    if (!editable.value) {
        return t('proposals.slides.read_only');
    }

    return t('proposals.slides.intro');
});

function modeClass(selected: boolean): string {
    if (!editable.value) {
        return selected ? 'border-2 border-accent bg-accent-soft cursor-default' : 'border border-dashed border-line-strong bg-surface text-muted cursor-not-allowed';
    }

    return selected ? 'border-2 border-accent bg-accent-soft' : 'border border-line-strong bg-white hover:bg-surface';
}
</script>

<template>
    <section class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5">
        <div class="flex flex-col gap-1.5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-base font-semibold text-ink">
                    <Layers
                        :size="18"
                        :stroke-width="2"
                        aria-hidden="true"
                    />
                    {{ t('proposals.slides.title') }}
                    <span
                        v-if="slides.documentLabel"
                        class="text-sm font-normal text-muted"
                    >· {{ slides.documentLabel }}</span>
                </h2>
                <p
                    class="text-[13px]"
                    :class="state === 'error' ? 'text-accent' : 'text-muted'"
                    aria-live="polite"
                >
                    {{ stateText }}
                </p>
            </div>
            <p class="text-sm text-muted">
                {{ intro }}
            </p>
        </div>

        <!-- Mode (D-418) -->
        <div
            role="radiogroup"
            :aria-label="t('proposals.slides.mode_label')"
            class="grid grid-cols-1 gap-2 sm:grid-cols-2"
        >
            <button
                v-for="option in slides.modes"
                :key="option.value"
                type="button"
                role="radio"
                class="min-h-16 rounded-[10px] px-3.5 py-2.5 text-left text-sm focus-visible:outline-2 focus-visible:outline-accent"
                :class="modeClass(mode === option.value)"
                :aria-checked="mode === option.value"
                :aria-disabled="!editable"
                @click="chooseMode(option.value)"
            >
                <span class="block font-semibold text-ink">{{ option.label }}</span>
                <span class="block text-[13px] text-muted">{{ option.description }}</span>
            </button>
        </div>

        <p class="text-sm font-medium text-ink">
            {{ t('proposals.slides.count', { included: String(includedCount), total: String(slides.totalSlides), pages: String(pageCount) }) }}
        </p>

        <div
            v-if="showWarnings && slides.warnings.length > 0"
            class="flex flex-col gap-2 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
            role="status"
        >
            <p class="flex items-center gap-2 font-semibold">
                <Info
                    :size="18"
                    :stroke-width="2"
                    class="shrink-0 text-notice"
                    aria-hidden="true"
                />
                {{ t('proposals.slides.warnings_title') }}
            </p>
            <ul class="flex list-disc flex-col gap-1 pl-5">
                <li
                    v-for="(warning, index) in slides.warnings"
                    :key="index"
                >
                    {{ warning }}
                </li>
            </ul>
        </div>
        <p
            v-if="slides.note"
            class="flex items-start gap-2 rounded-[10px] bg-surface px-3.5 py-3 text-sm text-ink-soft"
        >
            <Info
                :size="18"
                :stroke-width="2"
                class="mt-0.5 shrink-0 text-muted"
                aria-hidden="true"
            />
            {{ slides.note }}
        </p>

        <p
            v-if="slides.sections.length === 0"
            class="text-sm text-muted"
        >
            {{ t('proposals.slides.empty') }}
        </p>

        <!-- Slides by section -->
        <div
            v-for="section in slides.sections"
            :key="section.name"
            class="flex flex-col gap-2.5"
        >
            <h3 class="text-[13px] font-semibold tracking-wide text-muted uppercase">
                {{ section.name }}
            </h3>
            <ul class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                <li
                    v-for="slide in section.slides"
                    :key="`${slide.custom ? 'c' : 's'}-${slide.id}`"
                >
                    <label
                        class="flex h-full flex-col overflow-hidden rounded-[12px] border transition-opacity"
                        :class="[
                            included(slide) ? 'border-line-strong bg-white' : 'border-dashed border-line-strong bg-surface opacity-60',
                            locked(slide) ? 'cursor-default' : 'cursor-pointer hover:border-accent',
                        ]"
                    >
                        <span class="relative block aspect-video w-full bg-surface">
                            <img
                                v-if="slide.thumbnailUrl"
                                :src="slide.thumbnailUrl"
                                alt=""
                                loading="lazy"
                                class="absolute inset-0 size-full object-cover"
                            >
                            <span
                                v-else
                                class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 px-3 text-center text-[13px] text-muted"
                            >
                                <FileImage
                                    :size="28"
                                    :stroke-width="1.6"
                                    aria-hidden="true"
                                />
                                {{ t('proposals.slides.no_thumbnail') }}
                            </span>
                            <span
                                v-if="slide.kind === 'cover' || slide.kind === 'recap'"
                                class="absolute right-2 bottom-2 hidden rounded-full bg-ink/80 px-2 py-0.5 text-[11px] font-medium text-white sm:block"
                            >{{ t('proposals.slides.no_thumbnail') }}</span>
                        </span>
                        <span class="flex flex-1 items-start gap-3 p-3">
                            <input
                                type="checkbox"
                                class="mt-0.5 size-5 shrink-0 accent-accent"
                                :checked="included(slide)"
                                :disabled="locked(slide)"
                                :aria-label="t('proposals.slides.include', { title: slide.title })"
                                @change="toggle(slide)"
                            >
                            <span class="flex min-w-0 flex-col gap-0.5 text-sm">
                                <span class="font-semibold text-ink">{{ slide.title }}</span>
                                <span
                                    v-if="slide.note"
                                    class="text-[13px] text-ink-soft"
                                >{{ slide.note }}</span>
                                <span
                                    v-if="slide.pagesLabel"
                                    class="text-[13px] text-muted"
                                >{{ slide.pagesLabel }}</span>
                                <span
                                    v-if="slide.mandatory"
                                    class="inline-flex items-center gap-1 text-[12px] text-muted"
                                >
                                    <Lock
                                        :size="13"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                    {{ t('proposals.slides.mandatory') }}
                                </span>
                                <span
                                    v-else-if="summaryOnly && !slide.inSummary"
                                    class="inline-flex items-center gap-1 text-[12px] text-muted"
                                >
                                    <CircleAlert
                                        :size="13"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                    {{ t('proposals.slides.not_in_summary') }}
                                </span>
                            </span>
                        </span>
                    </label>
                </li>
            </ul>
        </div>

        <!-- Slides composed with blocks (DM-07, D-399) -->
        <CustomSlides
            ref="customPanel"
            :custom="slides.custom"
            :queue="queue"
            :frozen="slides.frozen"
        />
    </section>
</template>
