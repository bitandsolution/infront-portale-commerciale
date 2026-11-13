<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Bold, CircleAlert, Eye, GripVertical, ImagePlus, Info, List, Minus, Plus, Trash2, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import SelectField from '@/components/forms/SelectField.vue';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import type { VisitQueue } from '@/composables/visitQueue';
import type { CustomBlock, CustomBlockType, CustomSlideData, CustomSlides } from '@/types';

/**
 * Editor of one slide composed with blocks (D-399, D-429…D-432; DM-07).
 *
 * - Title for the list, layout, section of the document; the image on the left with the layout
 *   "image_text".
 * - Blocks stacked like in the slide: title, text (bold "**…**", lines "- " for the list), table
 *   (first row = header), image, image with text; at most 6 blocks and 2 images (D-430). They are
 *   reordered by dragging the handle, which works with the mouse and with the finger (pointer
 *   events), or with the arrows, which work with the keyboard.
 * - Every change is saved shortly after the last one (the saves of the panel go one at a time,
 *   visitQueue); the errors of the server stay under the fields of this slide (error bag of its own).
 * - "Anteprima" asks the server for the slide drawn like in the PDF (D-432), after the pending save.
 * - The warnings (content reduced or cut, D-431) come from the server after every save.
 */
const props = defineProps<{
    slide: CustomSlideData;
    options: CustomSlides;
    queue: VisitQueue;
}>();

const { t } = useTrans();

const editable = computed(() => props.slide.updateUrl !== null);

const title = ref(props.slide.title);
const layout = ref<CustomSlideData['layout']>(props.slide.layout);
const section = ref(props.slide.section);
const imageFileId = ref<number | null>(props.slide.imageFileId);
const imageUrl = ref<string | null>(props.slide.imageUrl);
const blocks = ref<CustomBlock[]>(clone(props.slide.blocks));
const errors = ref<Record<string, string>>({});
const state = ref<'idle' | 'saving' | 'saved' | 'error' | 'offline'>('idle');
const uploading = ref<string | null>(null);

let timer: ReturnType<typeof setTimeout> | undefined;

function clone(source: CustomBlock[]): CustomBlock[] {
    return source.map((block) => ({ ...block, rows: block.rows?.map((row) => [...row]) }));
}

function adopt(slide: CustomSlideData): void {
    title.value = slide.title;
    layout.value = slide.layout;
    section.value = slide.section;
    imageFileId.value = slide.imageFileId;
    imageUrl.value = slide.imageUrl;
    blocks.value = clone(slide.blocks);
}

// A new answer of the server is shown unless there are changes not saved yet.
watch(() => props.slide, (slide) => {
    if (timer === undefined && !props.queue.busy() && uploading.value === null) {
        adopt(slide);
    }
});

const imageCount = computed(() => (layout.value === 'image_text' ? 1 : 0) + blocks.value.filter((block) => block.type === 'image' || block.type === 'image_text').length);
const canAddBlock = computed(() => editable.value && blocks.value.length < props.options.limits.blocks);

function canAddType(type: string): boolean {
    if (!canAddBlock.value) {
        return false;
    }

    return !((type === 'image' || type === 'image_text') && imageCount.value >= props.options.limits.images);
}

const newType = ref<string>('text');

function payload(): { title: string; layout: string; section: string; image_file_id: number | null; blocks: Array<Omit<CustomBlock, 'imageUrl'>> } {
    return {
        title: title.value,
        layout: layout.value,
        section: section.value,
        image_file_id: layout.value === 'image_text' ? imageFileId.value : null,
        blocks: blocks.value.map((block) => ({
            key: block.key,
            type: block.type,
            column: block.column,
            text: block.text,
            rows: block.rows,
            file_id: block.file_id,
        })),
    };
}

function schedule(): void {
    if (!editable.value) {
        return;
    }
    clearTimeout(timer);
    timer = setTimeout(() => {
        timer = undefined;
        save();
    }, 800);
}

function save(): void {
    const url = props.slide.updateUrl;
    if (url === null) {
        return;
    }
    state.value = 'saving';
    props.queue.run(`custom-slide-${props.slide.id}`, (done) => {
        let failed = false;
        router.put(url, payload(), {
            preserveState: true,
            preserveScroll: true,
            only: ['documentSlides'],
            errorBag: `customSlide${props.slide.id}`,
            onSuccess: () => {
                errors.value = {};
            },
            onError: (bag) => {
                failed = true;
                errors.value = bag;
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

/** Sends the pending change now (before an upload, a preview, the sending of the group). */
function flush(): void {
    if (timer !== undefined) {
        clearTimeout(timer);
        timer = undefined;
        save();
    }
}

defineExpose({ flush });

onBeforeUnmount(() => {
    clearTimeout(timer);
    window.removeEventListener('online', retry);
});

// Blocks

function newKey(): string {
    return Math.random().toString(36).slice(2, 10) || 'b';
}

function addBlock(): void {
    const type = newType.value as CustomBlockType;
    if (!canAddType(type)) {
        return;
    }
    const block: CustomBlock = { key: newKey(), type, column: 'left' };
    if (type === 'title' || type === 'text' || type === 'image_text') {
        block.text = '';
    }
    if (type === 'table') {
        block.rows = [['', ''], ['', '']];
    }
    if (type === 'image' || type === 'image_text') {
        block.file_id = null;
        block.imageUrl = null;
    }
    blocks.value = [...blocks.value, block];
    schedule();
}

function removeBlock(index: number): void {
    blocks.value = blocks.value.filter((_, position) => position !== index);
    schedule();
}

function move(from: number, to: number): void {
    if (to < 0 || to >= blocks.value.length || from === to) {
        return;
    }
    const next = [...blocks.value];
    const [moved] = next.splice(from, 1);
    if (moved === undefined) {
        return;
    }
    next.splice(to, 0, moved);
    blocks.value = next;
}

function moveAndSave(from: number, to: number): void {
    move(from, to);
    schedule();
}

// Dragging with the handle: pointer events, so it works with the mouse and on touch screens.
const blockElements: Array<HTMLElement | null> = [];
const dragging = ref<number | null>(null);
let dragMoved = false;

function setBlockElement(element: unknown, index: number): void {
    blockElements[index] = element instanceof HTMLElement ? element : null;
}

function startDrag(event: PointerEvent, index: number): void {
    if (!editable.value) {
        return;
    }
    event.preventDefault();
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    dragging.value = index;
    dragMoved = false;
}

function dragOver(event: PointerEvent): void {
    const from = dragging.value;
    if (from === null) {
        return;
    }
    for (let index = 0; index < blocks.value.length; index++) {
        const element = blockElements[index];
        if (index === from || !element) {
            continue;
        }
        const rect = element.getBoundingClientRect();
        const middle = rect.top + rect.height / 2;
        if ((index < from && event.clientY < middle) || (index > from && event.clientY > middle)) {
            move(from, index);
            dragging.value = index;
            dragMoved = true;
            break;
        }
    }
}

function endDrag(event: PointerEvent): void {
    if (dragging.value === null) {
        return;
    }
    const target = event.currentTarget as HTMLElement;
    if (target.hasPointerCapture(event.pointerId)) {
        target.releasePointerCapture(event.pointerId);
    }
    dragging.value = null;
    if (dragMoved) {
        schedule();
    }
}

// Text: bold and bulleted list on the selection of the textarea.
const textareas = new Map<string, HTMLTextAreaElement>();

function setTextarea(element: unknown, key: string): void {
    if (element instanceof HTMLTextAreaElement) {
        textareas.set(key, element);
    } else {
        textareas.delete(key);
    }
}

async function bold(block: CustomBlock): Promise<void> {
    const area = textareas.get(block.key);
    const text = block.text ?? '';
    const start = area?.selectionStart ?? text.length;
    const end = area?.selectionEnd ?? text.length;
    block.text = `${text.slice(0, start)}**${text.slice(start, end)}**${text.slice(end)}`;
    schedule();
    await nextTick();
    area?.focus();
    area?.setSelectionRange(start + 2, end + 2);
}

async function bullet(block: CustomBlock): Promise<void> {
    const area = textareas.get(block.key);
    const text = block.text ?? '';
    const start = area?.selectionStart ?? 0;
    const end = area?.selectionEnd ?? 0;
    const lineStart = text.lastIndexOf('\n', start - 1) + 1;
    const lineEndIndex = text.indexOf('\n', end);
    const lineEnd = lineEndIndex === -1 ? text.length : lineEndIndex;
    const lines = text.slice(lineStart, lineEnd).split('\n');
    const all = lines.every((line) => line.startsWith('- '));
    const changed = lines.map((line) => (all ? line.slice(2) : `- ${line}`)).join('\n');
    block.text = text.slice(0, lineStart) + changed + text.slice(lineEnd);
    schedule();
    await nextTick();
    area?.focus();
    area?.setSelectionRange(lineStart, lineStart + changed.length);
}

// Table

function addRow(block: CustomBlock): void {
    const rows = block.rows ?? [['']];
    if (rows.length >= props.options.limits.rows) {
        return;
    }
    block.rows = [...rows, Array.from({ length: rows[0]?.length ?? 1 }, () => '')];
    schedule();
}

function removeRow(block: CustomBlock): void {
    if ((block.rows?.length ?? 0) > 1) {
        block.rows = block.rows?.slice(0, -1);
        schedule();
    }
}

function addColumn(block: CustomBlock): void {
    const rows = block.rows ?? [['']];
    if ((rows[0]?.length ?? 0) >= props.options.limits.columns) {
        return;
    }
    block.rows = rows.map((row) => [...row, '']);
    schedule();
}

function removeColumn(block: CustomBlock): void {
    const rows = block.rows ?? [['']];
    if ((rows[0]?.length ?? 0) > 1) {
        block.rows = rows.map((row) => row.slice(0, -1));
        schedule();
    }
}

// Images

function chooseFile(event: Event, blockKey: string | null): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file || props.slide.uploadUrl === null) {
        return;
    }
    const url = props.slide.uploadUrl;
    flush();
    uploading.value = blockKey ?? 'slide';
    props.queue.run(`custom-upload-${props.slide.id}-${blockKey ?? 'slide'}`, (done) => {
        router.post(url, { image: file, block: blockKey ?? '' }, {
            forceFormData: true,
            preserveState: true,
            preserveScroll: true,
            only: ['documentSlides'],
            errorBag: `customSlide${props.slide.id}`,
            onSuccess: () => {
                errors.value = {};
            },
            onError: (bag) => {
                errors.value = bag;
            },
            onFinish: () => {
                // The new image comes from the server: only its field is taken, the rest stays.
                const fresh = props.slide;
                if (blockKey === null) {
                    imageFileId.value = fresh.imageFileId;
                    imageUrl.value = fresh.imageUrl;
                } else {
                    const source = fresh.blocks.find((block) => block.key === blockKey);
                    const target = blocks.value.find((block) => block.key === blockKey);
                    if (source && target) {
                        target.file_id = source.file_id ?? null;
                        target.imageUrl = source.imageUrl ?? null;
                    }
                }
                uploading.value = null;
                done();
            },
        });
    });
}

function removeImage(block: CustomBlock | null): void {
    if (block === null) {
        imageFileId.value = null;
        imageUrl.value = null;
    } else {
        block.file_id = null;
        block.imageUrl = null;
    }
    schedule();
}

// Preview (D-432)

const previewOpen = ref(false);
const previewSrc = ref<string | null>(null);
const previewState = ref<'loading' | 'ready' | 'error'>('loading');

function openPreview(): void {
    flush();
    previewOpen.value = true;
    previewState.value = 'loading';
    previewSrc.value = null;
    props.queue.whenIdle(() => {
        previewSrc.value = `${props.slide.previewUrl}?v=${Date.now()}`;
    });
}

function closePreview(): void {
    previewOpen.value = false;
    previewSrc.value = null;
}

function onPreviewKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        closePreview();
    }
}

watch(previewOpen, (open) => {
    if (open) {
        document.addEventListener('keydown', onPreviewKeydown);
    } else {
        document.removeEventListener('keydown', onPreviewKeydown);
    }
});

onBeforeUnmount(() => document.removeEventListener('keydown', onPreviewKeydown));

// Deletion

const confirmingDelete = ref(false);
const deleting = ref(false);

function deleteSlide(): void {
    const url = props.slide.deleteUrl;
    if (url === null) {
        return;
    }
    clearTimeout(timer);
    timer = undefined;
    deleting.value = true;
    props.queue.run(`custom-delete-${props.slide.id}`, (done) => {
        router.delete(url, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                deleting.value = false;
                confirmingDelete.value = false;
                done();
            },
        });
    });
}

function blockError(index: number): string | undefined {
    const prefix = `blocks.${index}.`;
    const key = Object.keys(errors.value).find((name) => name.startsWith(prefix));

    return key ? errors.value[key] : undefined;
}

function typeLabel(type: string): string {
    return props.options.blockTypes.find((option) => option.value === type)?.label ?? type;
}

const stateText = computed(() => ({
    idle: '',
    saving: t('proposals.custom_slides.saving'),
    saved: t('proposals.custom_slides.saved'),
    error: t('proposals.custom_slides.error'),
    offline: t('proposals.custom_slides.offline'),
}[state.value]));

const textareaClass = 'min-h-28 w-full rounded-[10px] border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 disabled:cursor-not-allowed disabled:bg-surface disabled:text-muted';
const inputClass = 'min-h-11 w-full rounded-[10px] border border-line-strong bg-white px-3.5 text-[15px] text-ink outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 disabled:cursor-not-allowed disabled:bg-surface disabled:text-muted';
const iconButton = 'inline-flex size-11 shrink-0 items-center justify-center rounded-[10px] text-ink-soft hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-40';
const smallButton = 'inline-flex min-h-11 items-center gap-1.5 rounded-[10px] border border-line-strong bg-white px-3 text-sm font-medium text-ink hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
</script>

<template>
    <article class="flex flex-col gap-4 rounded-[12px] border border-line-strong bg-white p-4">
        <!-- Header: title, layout, section -->
        <div class="flex flex-wrap items-start justify-between gap-3">
            <p class="text-sm font-semibold text-ink">
                {{ slide.title }}
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <p
                    class="text-[13px]"
                    :class="state === 'error' ? 'text-accent' : 'text-muted'"
                    aria-live="polite"
                >
                    {{ stateText }}
                </p>
                <button
                    type="button"
                    :class="smallButton"
                    @click="openPreview"
                >
                    <Eye
                        :size="16"
                        :stroke-width="2"
                        aria-hidden="true"
                    />
                    {{ t('proposals.custom_slides.preview.action') }}
                </button>
                <button
                    v-if="slide.deleteUrl"
                    type="button"
                    :class="smallButton"
                    @click="confirmingDelete = true"
                >
                    <Trash2
                        :size="16"
                        :stroke-width="2"
                        aria-hidden="true"
                    />
                    {{ t('proposals.custom_slides.delete.action') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <TextField
                v-model="title"
                :label="t('proposals.custom_slides.fields.title')"
                :error="errors.title"
                :disabled="!editable"
                @update:model-value="schedule"
            />
            <SelectField
                v-model="layout"
                :label="t('proposals.custom_slides.fields.layout')"
                :options="options.layouts"
                :disabled="!editable"
                @update:model-value="schedule"
            />
            <SelectField
                v-model="section"
                :label="t('proposals.custom_slides.fields.section')"
                :options="options.sections"
                :disabled="!editable"
                @update:model-value="schedule"
            />
        </div>
        <p class="-mt-1 text-[13px] text-muted">
            {{ t('proposals.custom_slides.fields.section_help') }}
        </p>

        <!-- Warnings of the content (D-431) -->
        <p
            v-if="slide.warning"
            class="flex items-start gap-2 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
            role="status"
        >
            <CircleAlert
                :size="18"
                :stroke-width="2"
                class="mt-0.5 shrink-0 text-notice"
                aria-hidden="true"
            />
            {{ slide.warning }}
        </p>
        <p
            v-else-if="slide.reduced"
            class="flex items-start gap-2 text-[13px] text-muted"
        >
            <Info
                :size="16"
                :stroke-width="2"
                class="mt-0.5 shrink-0"
                aria-hidden="true"
            />
            {{ slide.reduced }}
        </p>

        <!-- Image on the left (layout "image_text") -->
        <div
            v-if="layout === 'image_text'"
            class="flex flex-col gap-2"
        >
            <p class="text-sm font-medium text-ink">
                {{ t('proposals.custom_slides.slide_image') }}
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <span class="relative block aspect-video w-40 shrink-0 overflow-hidden rounded-[8px] border border-line bg-surface">
                    <img
                        v-if="imageUrl"
                        :src="imageUrl"
                        :alt="t('proposals.custom_slides.image.alt')"
                        class="absolute inset-0 size-full object-contain"
                    >
                    <span
                        v-else
                        class="absolute inset-0 flex items-center justify-center text-[12px] text-muted"
                    >{{ t('proposals.custom_slides.image.none') }}</span>
                </span>
                <div
                    v-if="editable"
                    class="flex flex-wrap items-center gap-2"
                >
                    <label :class="[smallButton, 'relative cursor-pointer focus-within:outline-2 focus-within:outline-accent']">
                        <ImagePlus
                            :size="16"
                            :stroke-width="2"
                            aria-hidden="true"
                        />
                        {{ uploading === 'slide' ? t('proposals.custom_slides.image.uploading') : imageUrl ? t('proposals.custom_slides.image.replace') : t('proposals.custom_slides.image.choose') }}
                        <input
                            type="file"
                            accept="image/jpeg,image/png"
                            class="sr-only"
                            :disabled="uploading !== null"
                            @change="chooseFile($event, null)"
                        >
                    </label>
                    <button
                        v-if="imageUrl"
                        type="button"
                        :class="smallButton"
                        @click="removeImage(null)"
                    >
                        {{ t('proposals.custom_slides.image.remove') }}
                    </button>
                    <span class="text-[12px] text-muted">{{ t('proposals.custom_slides.image.hint') }}</span>
                </div>
            </div>
            <p
                v-if="errors.image && uploading === null"
                class="text-[13px] text-accent"
            >
                {{ errors.image }}
            </p>
        </div>

        <!-- Blocks -->
        <div class="flex flex-col gap-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-medium text-ink">
                    {{ t('proposals.custom_slides.blocks.title') }}
                </p>
                <p class="text-[13px] text-muted">
                    {{ t('proposals.custom_slides.blocks.count', { count: String(blocks.length), max: String(options.limits.blocks), images: String(imageCount), image_limit: String(options.limits.images) }) }}
                </p>
            </div>
            <p
                v-if="errors.blocks"
                class="text-[13px] text-accent"
            >
                {{ errors.blocks }}
            </p>
            <p
                v-if="blocks.length === 0"
                class="text-sm text-muted"
            >
                {{ t('proposals.custom_slides.blocks.empty') }}
            </p>

            <ol class="flex flex-col gap-2">
                <li
                    v-for="(block, index) in blocks"
                    :key="block.key"
                    :ref="(element) => setBlockElement(element, index)"
                    class="flex gap-2 rounded-[10px] border bg-white p-2.5"
                    :class="dragging === index ? 'border-accent shadow-md' : 'border-line'"
                >
                    <div
                        v-if="editable"
                        class="flex shrink-0 flex-col items-center"
                    >
                        <button
                            type="button"
                            class="inline-flex size-11 cursor-grab touch-none items-center justify-center rounded-[10px] text-muted hover:bg-surface active:cursor-grabbing"
                            :aria-label="t('proposals.custom_slides.blocks.drag', { number: String(index + 1) })"
                            @pointerdown="startDrag($event, index)"
                            @pointermove="dragOver"
                            @pointerup="endDrag"
                            @pointercancel="endDrag"
                        >
                            <GripVertical
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                        </button>
                        <button
                            type="button"
                            :class="iconButton"
                            :disabled="index === 0"
                            :aria-label="t('proposals.custom_slides.blocks.move_up', { number: String(index + 1) })"
                            @click="moveAndSave(index, index - 1)"
                        >
                            <ArrowUp
                                :size="16"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                        </button>
                        <button
                            type="button"
                            :class="iconButton"
                            :disabled="index === blocks.length - 1"
                            :aria-label="t('proposals.custom_slides.blocks.move_down', { number: String(index + 1) })"
                            @click="moveAndSave(index, index + 1)"
                        >
                            <ArrowDown
                                :size="16"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                        </button>
                    </div>

                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-[13px] font-semibold tracking-wide text-muted uppercase">
                                {{ index + 1 }} · {{ typeLabel(block.type) }}
                            </p>
                            <div class="flex items-center gap-2">
                                <div
                                    v-if="layout === 'two_columns'"
                                    role="radiogroup"
                                    :aria-label="t('proposals.custom_slides.blocks.column')"
                                    class="flex overflow-hidden rounded-[10px] border border-line-strong text-sm"
                                >
                                    <button
                                        v-for="side in (['left', 'right'] as const)"
                                        :key="side"
                                        type="button"
                                        role="radio"
                                        class="min-h-11 px-3"
                                        :class="block.column === side ? 'bg-accent-soft font-semibold text-ink' : 'bg-white text-ink-soft hover:bg-surface'"
                                        :aria-checked="block.column === side"
                                        :disabled="!editable"
                                        @click="block.column = side; schedule()"
                                    >
                                        {{ t(`proposals.custom_slides.blocks.${side}`) }}
                                    </button>
                                </div>
                                <button
                                    v-if="editable"
                                    type="button"
                                    :class="iconButton"
                                    :aria-label="t('proposals.custom_slides.blocks.delete', { number: String(index + 1) })"
                                    @click="removeBlock(index)"
                                >
                                    <Trash2
                                        :size="16"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                        </div>

                        <!-- Title -->
                        <input
                            v-if="block.type === 'title'"
                            v-model="block.text"
                            type="text"
                            :class="[inputClass, 'font-semibold']"
                            :maxlength="options.limits.title"
                            :placeholder="t('proposals.custom_slides.blocks.title_placeholder')"
                            :aria-label="`${index + 1} · ${typeLabel(block.type)}`"
                            :disabled="!editable"
                            @input="schedule"
                        >

                        <!-- Image (alone or with the text) -->
                        <div
                            v-if="block.type === 'image' || block.type === 'image_text'"
                            class="flex flex-wrap items-center gap-3"
                        >
                            <span class="relative block aspect-video w-32 shrink-0 overflow-hidden rounded-[8px] border border-line bg-surface">
                                <img
                                    v-if="block.imageUrl"
                                    :src="block.imageUrl"
                                    :alt="t('proposals.custom_slides.image.alt')"
                                    class="absolute inset-0 size-full object-contain"
                                >
                                <span
                                    v-else
                                    class="absolute inset-0 flex items-center justify-center text-[12px] text-muted"
                                >{{ t('proposals.custom_slides.image.none') }}</span>
                            </span>
                            <div
                                v-if="editable"
                                class="flex flex-wrap items-center gap-2"
                            >
                                <label :class="[smallButton, 'relative cursor-pointer focus-within:outline-2 focus-within:outline-accent']">
                                    <ImagePlus
                                        :size="16"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                    {{ uploading === block.key ? t('proposals.custom_slides.image.uploading') : block.imageUrl ? t('proposals.custom_slides.image.replace') : t('proposals.custom_slides.image.choose') }}
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png"
                                        class="sr-only"
                                        :disabled="uploading !== null"
                                        @change="chooseFile($event, block.key)"
                                    >
                                </label>
                                <button
                                    v-if="block.imageUrl"
                                    type="button"
                                    :class="smallButton"
                                    @click="removeImage(block)"
                                >
                                    {{ t('proposals.custom_slides.image.remove') }}
                                </button>
                                <span class="text-[12px] text-muted">{{ t('proposals.custom_slides.image.hint') }}</span>
                            </div>
                        </div>

                        <!-- Text (alone or next to the image) -->
                        <div
                            v-if="block.type === 'text' || block.type === 'image_text'"
                            class="flex flex-col gap-1.5"
                        >
                            <div
                                v-if="editable"
                                class="flex items-center gap-1"
                            >
                                <button
                                    type="button"
                                    :class="iconButton"
                                    :aria-label="t('proposals.custom_slides.blocks.bold')"
                                    :title="t('proposals.custom_slides.blocks.bold')"
                                    @click="bold(block)"
                                >
                                    <Bold
                                        :size="16"
                                        :stroke-width="2.4"
                                        aria-hidden="true"
                                    />
                                </button>
                                <button
                                    type="button"
                                    :class="iconButton"
                                    :aria-label="t('proposals.custom_slides.blocks.bullet')"
                                    :title="t('proposals.custom_slides.blocks.bullet')"
                                    @click="bullet(block)"
                                >
                                    <List
                                        :size="16"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                </button>
                                <span class="text-[12px] text-muted">{{ t('proposals.custom_slides.blocks.text_help') }}</span>
                            </div>
                            <textarea
                                :ref="(element) => setTextarea(element, block.key)"
                                v-model="block.text"
                                :class="textareaClass"
                                :maxlength="options.limits.text"
                                :placeholder="t('proposals.custom_slides.blocks.text_placeholder')"
                                :aria-label="`${index + 1} · ${typeLabel(block.type)}`"
                                :aria-invalid="(block.text ?? '').length >= options.limits.text ? 'true' : undefined"
                                :disabled="!editable"
                                @input="schedule"
                            />
                            <p
                                class="text-right text-[12px]"
                                :class="(block.text ?? '').length >= options.limits.text ? 'font-semibold text-accent' : 'text-muted'"
                                aria-live="polite"
                            >
                                {{ (block.text ?? '').length >= options.limits.text
                                    ? t('proposals.custom_slides.blocks.characters_limit', { max: String(options.limits.text) })
                                    : t('proposals.custom_slides.blocks.characters', { count: String((block.text ?? '').length), max: String(options.limits.text) }) }}
                            </p>
                        </div>

                        <!-- Table -->
                        <div
                            v-if="block.type === 'table'"
                            class="flex flex-col gap-2"
                        >
                            <p class="text-[12px] text-muted">
                                {{ t('proposals.custom_slides.table.header_hint') }}
                            </p>
                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse">
                                    <tbody>
                                        <tr
                                            v-for="(row, rowIndex) in block.rows ?? []"
                                            :key="rowIndex"
                                        >
                                            <td
                                                v-for="(_, columnIndex) in row"
                                                :key="columnIndex"
                                                class="min-w-28 p-0.5"
                                            >
                                                <input
                                                    v-model="row[columnIndex]"
                                                    type="text"
                                                    :class="[inputClass, rowIndex === 0 ? 'bg-accent-soft font-semibold' : '']"
                                                    :maxlength="options.limits.cell"
                                                    :aria-label="t('proposals.custom_slides.table.cell', { row: String(rowIndex + 1), column: String(columnIndex + 1) })"
                                                    :disabled="!editable"
                                                    @input="schedule"
                                                >
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div
                                v-if="editable"
                                class="flex flex-wrap gap-2"
                            >
                                <button
                                    type="button"
                                    :class="smallButton"
                                    :disabled="(block.rows?.length ?? 0) >= options.limits.rows"
                                    @click="addRow(block)"
                                >
                                    <Plus
                                        :size="16"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                    {{ t('proposals.custom_slides.table.add_row') }}
                                </button>
                                <button
                                    type="button"
                                    :class="smallButton"
                                    :disabled="(block.rows?.length ?? 0) <= 1"
                                    @click="removeRow(block)"
                                >
                                    <Minus
                                        :size="16"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                    {{ t('proposals.custom_slides.table.remove_row') }}
                                </button>
                                <button
                                    type="button"
                                    :class="smallButton"
                                    :disabled="(block.rows?.[0]?.length ?? 0) >= options.limits.columns"
                                    @click="addColumn(block)"
                                >
                                    <Plus
                                        :size="16"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                    {{ t('proposals.custom_slides.table.add_column') }}
                                </button>
                                <button
                                    type="button"
                                    :class="smallButton"
                                    :disabled="(block.rows?.[0]?.length ?? 0) <= 1"
                                    @click="removeColumn(block)"
                                >
                                    <Minus
                                        :size="16"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                    {{ t('proposals.custom_slides.table.remove_column') }}
                                </button>
                            </div>
                        </div>

                        <p
                            v-if="blockError(index)"
                            class="text-[13px] text-accent"
                        >
                            {{ blockError(index) }}
                        </p>
                    </div>
                </li>
            </ol>

            <!-- Add a block -->
            <div
                v-if="editable"
                class="flex flex-wrap items-end gap-2"
            >
                <label class="flex flex-col gap-1.5 text-sm font-medium text-ink">
                    {{ t('proposals.custom_slides.blocks.add_label') }}
                    <select
                        v-model="newType"
                        class="min-h-11 rounded-[10px] border border-line-strong bg-white px-3 text-[15px] text-ink focus:border-accent focus:ring-2 focus:ring-accent/20"
                        :disabled="!canAddBlock"
                    >
                        <option
                            v-for="option in options.blockTypes"
                            :key="option.value"
                            :value="option.value"
                            :disabled="!canAddType(option.value)"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </label>
                <button
                    type="button"
                    :class="buttonClasses.secondary"
                    :disabled="!canAddType(newType)"
                    @click="addBlock"
                >
                    <Plus
                        :size="18"
                        :stroke-width="2"
                        aria-hidden="true"
                    />
                    {{ t('proposals.custom_slides.blocks.add') }}
                </button>
                <p
                    v-if="!canAddBlock"
                    class="text-[13px] text-muted"
                >
                    {{ t('proposals.custom_slides.blocks.limit', { max: String(options.limits.blocks) }) }}
                </p>
                <p
                    v-else-if="imageCount >= options.limits.images"
                    class="text-[13px] text-muted"
                >
                    {{ t('proposals.custom_slides.blocks.images_limit', { max: String(options.limits.images) }) }}
                </p>
            </div>
        </div>

        <!-- Preview (D-432) -->
        <div
            v-if="previewOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
        >
            <div
                class="absolute inset-0 bg-ink/60"
                aria-hidden="true"
                @click="closePreview"
            />
            <section
                role="dialog"
                aria-modal="true"
                :aria-label="t('proposals.custom_slides.preview.title', { title: slide.title })"
                class="relative flex w-full max-w-5xl flex-col gap-3 rounded-[14px] bg-white p-4 shadow-xl"
            >
                <div class="flex items-center justify-between gap-3">
                    <h4 class="text-base font-semibold text-ink">
                        {{ t('proposals.custom_slides.preview.title', { title: slide.title }) }}
                    </h4>
                    <button
                        type="button"
                        :class="iconButton"
                        :aria-label="t('proposals.custom_slides.preview.close')"
                        @click="closePreview"
                    >
                        <X
                            :size="18"
                            :stroke-width="2"
                            aria-hidden="true"
                        />
                    </button>
                </div>
                <div class="relative aspect-video w-full overflow-hidden rounded-[8px] border border-line bg-surface">
                    <img
                        v-if="previewSrc"
                        v-show="previewState === 'ready'"
                        :src="previewSrc"
                        :alt="t('proposals.custom_slides.preview.alt', { title: slide.title })"
                        class="absolute inset-0 size-full object-contain"
                        @load="previewState = 'ready'"
                        @error="previewState = 'error'"
                    >
                    <p
                        v-if="previewState !== 'ready'"
                        class="absolute inset-0 flex items-center justify-center px-4 text-center text-sm"
                        :class="previewState === 'error' ? 'text-accent' : 'text-muted'"
                        aria-live="polite"
                    >
                        {{ previewState === 'error' ? t('proposals.custom_slides.preview.error') : t('proposals.custom_slides.preview.loading') }}
                    </p>
                </div>
            </section>
        </div>

        <ConfirmDialog
            :open="confirmingDelete"
            :title="t('proposals.custom_slides.delete.title', { title: slide.title })"
            :confirm-label="t('proposals.custom_slides.delete.confirm')"
            :processing="deleting"
            @confirm="deleteSlide"
            @cancel="confirmingDelete = false"
        >
            <p>{{ t('proposals.custom_slides.delete.text') }}</p>
        </ConfirmDialog>
    </article>
</template>
