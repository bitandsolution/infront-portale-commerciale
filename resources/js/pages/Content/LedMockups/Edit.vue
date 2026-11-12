<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Eye, RotateCcw } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import SelectField from '@/components/forms/SelectField.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, LedMockupEditor } from '@/types';

/**
 * Editor of one configuration of the simulation of the logo on the LED (D-398; DM-06): the photo of
 * the field, the areas of the LED marked with four clicks each (top left, then clockwise; two areas,
 * upper and lower row, for Jumbo and Domination), the points dragged to adjust them, the height of
 * the logo and the spacing. The preview is drawn by the server with the pipeline of the PDF (one
 * implementation), from the saved photo and the values on the screen. Only the admin saves.
 */
const props = defineProps<{
    layout: LayoutData;
    mockup: LedMockupEditor;
}>();

const { t } = useTrans();

const form = useForm<{
    photo: File | null;
    areas: string;
    logo_height_percent: number;
    spacing: number;
}>({
    photo: null,
    areas: JSON.stringify(props.mockup.areas),
    logo_height_percent: props.mockup.logoHeightPercent,
    spacing: props.mockup.spacing,
});

const totalPoints = computed(() => props.mockup.areaCount * 4);
const points = ref<Array<[number, number]>>(props.mockup.areas.flat());
const localPhoto = ref<{ url: string; width: number; height: number } | null>(null);
const photo = computed(() => localPhoto.value ?? props.mockup.photo);
const editable = computed(() => props.mockup.canEdit);

const svg = ref<SVGSVGElement | null>(null);
const dragging = ref<number | null>(null);

function pickPhoto(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.photo = file;
    if (localPhoto.value !== null) {
        URL.revokeObjectURL(localPhoto.value.url);
        localPhoto.value = null;
    }
    if (file === null) {
        return;
    }
    const url = URL.createObjectURL(file);
    const image = new Image();
    image.onload = () => {
        localPhoto.value = { url, width: image.naturalWidth, height: image.naturalHeight };
        points.value = [];
    };
    image.src = url;
}

onBeforeUnmount(() => {
    if (localPhoto.value !== null) {
        URL.revokeObjectURL(localPhoto.value.url);
    }
});

/** Point of the photo under the pointer, in pixels of the photo. */
function photoPoint(event: PointerEvent): [number, number] | null {
    const matrix = svg.value?.getScreenCTM();
    if (!svg.value || !matrix || !photo.value) {
        return null;
    }
    const point = svg.value.createSVGPoint();
    point.x = event.clientX;
    point.y = event.clientY;
    const local = point.matrixTransform(matrix.inverse());

    return [
        Math.round(Math.min(Math.max(local.x, 0), photo.value.width)),
        Math.round(Math.min(Math.max(local.y, 0), photo.value.height)),
    ];
}

function addPoint(event: PointerEvent): void {
    if (!editable.value || dragging.value !== null || points.value.length >= totalPoints.value) {
        return;
    }
    const point = photoPoint(event);
    if (point !== null) {
        points.value = [...points.value, point];
    }
}

function startDrag(index: number, event: PointerEvent): void {
    if (!editable.value) {
        return;
    }
    event.stopPropagation();
    dragging.value = index;
    (event.target as Element).setPointerCapture(event.pointerId);
}

function drag(event: PointerEvent): void {
    if (dragging.value === null) {
        return;
    }
    const point = photoPoint(event);
    if (point !== null) {
        const next = [...points.value];
        next[dragging.value] = point;
        points.value = next;
    }
}

function endDrag(): void {
    // Released after the click on the overlay: the click must not add a point.
    setTimeout(() => {
        dragging.value = null;
    }, 0);
}

function resetPoints(): void {
    points.value = [];
}

const areas = computed(() => {
    const result: Array<Array<[number, number]>> = [];
    for (let index = 0; index < points.value.length; index += 4) {
        result.push(points.value.slice(index, index + 4));
    }

    return result;
});

const complete = computed(() => points.value.length === totalPoints.value);
const radius = computed(() => Math.max(4, Math.round((photo.value?.width ?? 1000) / 160)));

function polygon(area: Array<[number, number]>): string {
    return area.map(([x, y]) => `${x},${y}`).join(' ');
}

function areaLabel(index: number): string {
    if (props.mockup.areaCount === 1) {
        return t('content.led_mockups.areas_title');
    }

    return index === 0 ? t('content.led_mockups.area_upper') : t('content.led_mockups.area_lower');
}

function save(): void {
    form.transform((data) => ({ ...data, areas: JSON.stringify(areas.value) })).post(props.mockup.saveUrl, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.photo = null;
            if (localPhoto.value !== null) {
                URL.revokeObjectURL(localPhoto.value.url);
                localPhoto.value = null;
            }
        },
    });
}

// Preview from the server (same rendering as the PDF).
const format = ref(props.mockup.formats[0]?.value ?? '');
const previewSrc = ref<string | null>(null);
const previewState = ref<'idle' | 'loading' | 'ready' | 'error'>('idle');
const canPreview = computed(() => props.mockup.previewUrl !== null && form.photo === null && complete.value);

function preview(): void {
    if (!canPreview.value || props.mockup.previewUrl === null) {
        return;
    }
    const query = new URLSearchParams({
        areas: JSON.stringify(areas.value),
        logo_height_percent: String(form.logo_height_percent),
        spacing: String(form.spacing),
        format: format.value,
    });
    previewState.value = 'loading';
    previewSrc.value = `${props.mockup.previewUrl}?${query.toString()}`;
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-7xl flex-col gap-5 p-4 md:p-6">
            <Link
                :href="mockup.indexUrl"
                class="inline-flex min-h-11 items-center gap-2 self-start text-sm font-medium text-ink-soft hover:text-ink"
            >
                <ArrowLeft
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('content.led_mockups.back') }}
            </Link>

            <div>
                <h1 class="text-xl font-semibold text-ink">
                    {{ mockup.name }} · {{ mockup.kindLabel }}
                </h1>
                <p
                    v-if="!editable"
                    role="note"
                    class="mt-2 rounded-[10px] bg-surface px-3 py-2 text-sm text-ink-soft"
                >
                    {{ t('content.led_mockups.read_only') }}
                </p>
            </div>

            <form
                class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5"
                @submit.prevent="save"
            >
                <div
                    v-if="editable"
                    class="flex flex-col gap-1.5"
                >
                    <label
                        for="led-photo"
                        class="text-sm font-medium text-ink"
                    >{{ t('content.led_mockups.photo') }}</label>
                    <input
                        id="led-photo"
                        type="file"
                        accept="image/jpeg,image/png"
                        class="min-h-11 text-sm file:mr-3 file:min-h-11 file:rounded-[10px] file:border file:border-line-strong file:bg-white file:px-3.5 file:text-sm file:font-medium"
                        :aria-invalid="form.errors.photo ? 'true' : undefined"
                        @change="pickPhoto"
                    >
                    <p class="text-[13px] text-muted">
                        {{ t('content.led_mockups.photo_help') }}
                    </p>
                    <p
                        v-if="form.errors.photo"
                        class="text-[13px] leading-snug text-accent"
                    >
                        {{ form.errors.photo }}
                    </p>
                </div>

                <p
                    v-if="!photo"
                    class="text-sm text-muted"
                >
                    {{ t('content.led_mockups.no_photo') }}
                </p>

                <template v-else>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-ink">
                                {{ t('content.led_mockups.areas_title') }}
                            </p>
                            <p class="text-[13px] text-muted">
                                {{ mockup.areaCount === 1 ? t('content.led_mockups.areas_help_one') : t('content.led_mockups.areas_help_two') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="text-sm"
                                :class="complete ? 'text-ink' : 'text-muted'"
                                aria-live="polite"
                            >{{ t('content.led_mockups.points', { done: String(points.length), total: String(totalPoints) }) }}</span>
                            <button
                                v-if="editable"
                                type="button"
                                :class="buttonClasses.secondary"
                                @click="resetPoints"
                            >
                                <RotateCcw
                                    :size="16"
                                    :stroke-width="2"
                                    aria-hidden="true"
                                />
                                {{ t('content.led_mockups.reset') }}
                            </button>
                        </div>
                    </div>

                    <div class="relative w-full overflow-hidden rounded-[10px] border border-line bg-surface">
                        <img
                            :src="photo.url"
                            alt=""
                            class="block w-full select-none"
                            draggable="false"
                        >
                        <svg
                            ref="svg"
                            class="absolute inset-0 size-full touch-none"
                            :class="editable && !complete ? 'cursor-crosshair' : ''"
                            :viewBox="`0 0 ${photo.width} ${photo.height}`"
                            preserveAspectRatio="none"
                            role="img"
                            :aria-label="t('content.led_mockups.areas_title')"
                            @pointerdown="addPoint"
                            @pointermove="drag"
                            @pointerup="endDrag"
                        >
                            <g
                                v-for="(area, index) in areas"
                                :key="index"
                            >
                                <polygon
                                    v-if="area.length === 4"
                                    :points="polygon(area)"
                                    fill="rgba(200, 16, 46, 0.25)"
                                    stroke="#C8102E"
                                    :stroke-width="radius / 2"
                                />
                                <polyline
                                    v-else
                                    :points="polygon(area)"
                                    fill="none"
                                    stroke="#C8102E"
                                    :stroke-width="radius / 2"
                                />
                                <title>{{ areaLabel(index) }}</title>
                            </g>
                            <circle
                                v-for="(point, index) in points"
                                :key="`p${index}`"
                                :cx="point[0]"
                                :cy="point[1]"
                                :r="radius"
                                fill="#ffffff"
                                stroke="#C8102E"
                                :stroke-width="radius / 2"
                                :class="editable ? 'cursor-move' : ''"
                                @pointerdown="startDrag(index, $event)"
                            />
                        </svg>
                    </div>
                    <p
                        v-if="form.errors.areas"
                        class="text-[13px] leading-snug text-accent"
                    >
                        {{ form.errors.areas }}
                    </p>
                </template>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label
                            for="led-height"
                            class="text-sm font-medium text-ink"
                        >{{ t('content.led_mockups.logo_height') }}</label>
                        <input
                            id="led-height"
                            v-model.number="form.logo_height_percent"
                            type="number"
                            min="10"
                            max="100"
                            step="1"
                            :disabled="!editable"
                            class="min-h-11 rounded-[10px] border border-line-strong bg-white px-3 text-[15px] disabled:bg-surface"
                        >
                        <p
                            v-if="form.errors.logo_height_percent"
                            class="text-[13px] text-accent"
                        >
                            {{ form.errors.logo_height_percent }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            for="led-spacing"
                            class="text-sm font-medium text-ink"
                        >{{ t('content.led_mockups.spacing') }}</label>
                        <input
                            id="led-spacing"
                            v-model.number="form.spacing"
                            type="number"
                            min="0"
                            max="2000"
                            step="1"
                            :disabled="!editable"
                            class="min-h-11 rounded-[10px] border border-line-strong bg-white px-3 text-[15px] disabled:bg-surface"
                        >
                        <p
                            v-if="form.errors.spacing"
                            class="text-[13px] text-accent"
                        >
                            {{ form.errors.spacing }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="editable"
                    class="flex justify-end"
                >
                    <button
                        type="submit"
                        :class="buttonClasses.primary"
                        :disabled="form.processing || !complete || !photo"
                    >
                        {{ t('content.led_mockups.save') }}
                    </button>
                </div>
            </form>

            <section
                v-if="editable"
                class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5"
            >
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-ink">
                            {{ t('content.led_mockups.preview') }}
                        </h2>
                        <p class="text-[13px] text-muted">
                            {{ canPreview ? t('content.led_mockups.preview_help') : t('content.led_mockups.preview_needs_save') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <div
                            v-if="mockup.formats.length > 1"
                            class="w-48"
                        >
                            <SelectField
                                v-model="format"
                                :label="t('content.led_mockups.preview_format')"
                                :options="mockup.formats"
                            />
                        </div>
                        <button
                            type="button"
                            :class="buttonClasses.secondary"
                            :disabled="!canPreview || previewState === 'loading'"
                            @click="preview"
                        >
                            <Eye
                                :size="16"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('content.led_mockups.preview') }}
                        </button>
                    </div>
                </div>
                <p
                    v-if="previewState === 'loading'"
                    class="text-sm text-muted"
                    aria-live="polite"
                >
                    {{ t('content.led_mockups.preview_loading') }}
                </p>
                <p
                    v-if="previewState === 'error'"
                    class="text-sm text-accent"
                    role="alert"
                >
                    {{ t('content.led_mockups.preview_error') }}
                </p>
                <img
                    v-if="previewSrc"
                    v-show="previewState === 'ready'"
                    :src="previewSrc"
                    :alt="t('content.led_mockups.preview_alt')"
                    class="aspect-video w-full rounded-[10px] border border-line object-contain"
                    @load="previewState = 'ready'"
                    @error="previewState = 'error'"
                >
            </section>
        </div>
    </AppLayout>
</template>
