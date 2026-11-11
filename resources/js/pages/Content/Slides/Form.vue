<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import CheckboxField from '@/components/forms/CheckboxField.vue';
import SelectField from '@/components/forms/SelectField.vue';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ContentSeason, LayoutData, Option, SlideFormData, SlideFormOptions } from '@/types';

/**
 * New slide or page of a slide of the library (M08 §4.1, §4.2; DM-02, D-402…D-406): title, scope and
 * club, document, section and order, kind (static or a layout), PNG 1920×1080 (none for the LED
 * simulation), conditions, active. One multipart request with the image (PUT spoofed as POST).
 * The rules are checked by the server (SlideRequest); the page only shows and hides fields.
 */
const props = defineProps<{
    layout: LayoutData;
    season: ContentSeason;
    slide: SlideFormData | null;
    options: SlideFormOptions;
    saveUrl: string;
    indexUrl: string;
}>();

const { t } = useTrans();

const isNew = computed(() => props.slide === null);
const readOnly = computed(() => props.season.closed);

/** Selected values of the multi-value conditions; the yes / no ones in their own select. */
const multi = ref<Record<string, string[]>>({});
const yesNo = ref<Record<string, string>>({});

for (const condition of props.options.conditions) {
    const values = props.slide?.conditions[condition.type] ?? [];
    if (condition.boolean) {
        yesNo.value[condition.type] = values[0] ?? '';
    } else {
        multi.value[condition.type] = [...values];
    }
}

const form = useForm<{
    _method: 'post' | 'put';
    title: string;
    scope: string;
    club_id: string;
    document_type: string;
    slide_section_id: string;
    sort_order: string;
    layout: string;
    is_active: boolean;
    image: File | null;
    conditions: Record<string, string[]>;
}>({
    _method: props.slide === null ? 'post' : 'put',
    title: props.slide?.title ?? '',
    scope: props.slide?.scope ?? 'infront',
    club_id: props.slide?.clubId != null ? String(props.slide.clubId) : '',
    document_type: props.slide?.documentType ?? 'both',
    slide_section_id: props.slide !== null ? String(props.slide.sectionId) : (props.options.sections[0]?.value ?? ''),
    sort_order: String(props.slide?.sortOrder ?? 10),
    layout: props.slide?.layout ?? 'static',
    is_active: props.slide?.isActive ?? true,
    image: null,
    conditions: {},
});

const needsImage = computed(() => !props.options.imageLess.includes(form.layout));
const systemHint = computed(() => {
    const key = `content.form.system.${form.layout}`;
    const text = t(key);

    return text === key ? null : text;
});
const closingSectionHint = computed(() => form.slide_section_id === props.options.closingSection);

const yesNoOptions = computed<Option[]>(() => [
    { value: 'true', label: t('content.form.conditions.yes') },
    { value: 'false', label: t('content.form.conditions.no') },
]);

const imageInput = ref<HTMLInputElement | null>(null);
const preview = ref<string | null>(null);

function pickImage(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.image = file;
    if (preview.value !== null) {
        URL.revokeObjectURL(preview.value);
    }
    preview.value = file !== null ? URL.createObjectURL(file) : null;
}

function toggle(type: string, value: string, checked: boolean): void {
    const current = multi.value[type] ?? [];
    multi.value[type] = checked ? [...current, value] : current.filter((item) => item !== value);
}

function conditionError(type: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[`conditions.${type}`];
}

function save(): void {
    form.transform((data) => {
        const conditions: Record<string, string[]> = {};
        for (const [type, values] of Object.entries(multi.value)) {
            if (values.length > 0) {
                conditions[type] = values;
            }
        }
        for (const [type, value] of Object.entries(yesNo.value)) {
            if (value !== '') {
                conditions[type] = [value];
            }
        }

        return { ...data, club_id: data.scope === 'club' ? data.club_id : '', conditions };
    }).post(props.saveUrl, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            if (!isNew.value) {
                form.image = null;
                preview.value = null;
                if (imageInput.value) {
                    imageInput.value.value = '';
                }
            }
        },
    });
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-4xl flex-col gap-5 p-4 md:p-6">
            <Link
                :href="indexUrl"
                class="inline-flex min-h-11 items-center gap-2 self-start text-sm font-medium text-ink-soft hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
            >
                <ArrowLeft
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('content.form.back') }}
            </Link>

            <form
                class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5 md:p-6"
                novalidate
                @submit.prevent="save"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ isNew ? t('content.form.create_title', { season: season.code }) : slide?.title }}
                    </h2>
                    <p
                        v-if="!isNew"
                        class="mt-1 text-sm text-muted"
                    >
                        {{ t('content.form.edit_hint', { season: season.code }) }}
                    </p>
                    <p
                        v-if="readOnly"
                        role="note"
                        class="mt-2 rounded-[10px] bg-notice-soft px-3 py-2 text-sm text-notice"
                    >
                        {{ t('content.slides.closed', { season: season.code }) }}
                    </p>
                </div>

                <fieldset
                    :disabled="readOnly"
                    class="flex flex-col gap-5"
                >
                    <div>
                        <TextField
                            v-model="form.title"
                            :label="t('content.form.fields.title')"
                            autocomplete="off"
                            :error="form.errors.title"
                        />
                        <p class="mt-1 text-[13px] text-muted">
                            {{ t('content.form.fields.title_help') }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <SelectField
                            v-model="form.scope"
                            :label="t('content.form.fields.scope')"
                            :options="options.scopes"
                            :error="form.errors.scope"
                        />
                        <SelectField
                            v-if="form.scope === 'club'"
                            v-model="form.club_id"
                            :label="t('content.form.fields.club')"
                            :options="options.clubs"
                            :placeholder="t('content.form.fields.club_placeholder')"
                            :error="form.errors.club_id"
                        />
                    </div>
                    <p class="-mt-3 text-[13px] text-muted">
                        {{ t('content.form.scope_help') }}
                    </p>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_1fr_8rem]">
                        <SelectField
                            v-model="form.document_type"
                            :label="t('content.form.fields.document_type')"
                            :options="options.documentTypes"
                            :error="form.errors.document_type"
                        />
                        <SelectField
                            v-model="form.slide_section_id"
                            :label="t('content.form.fields.section')"
                            :options="options.sections"
                            :error="form.errors.slide_section_id"
                        />
                        <TextField
                            v-model="form.sort_order"
                            :label="t('content.form.fields.sort_order')"
                            inputmode="numeric"
                            mono
                            :error="form.errors.sort_order"
                        />
                    </div>
                    <p class="-mt-3 text-[13px] text-muted">
                        {{ t('content.form.fields.sort_order_help') }}
                        <template v-if="closingSectionHint">
                            {{ t('content.form.system.closing') }}
                        </template>
                    </p>

                    <div>
                        <SelectField
                            v-model="form.layout"
                            :label="t('content.form.fields.layout')"
                            :options="options.layouts"
                            :error="form.errors.layout"
                        />
                        <p class="mt-1 text-[13px] text-muted">
                            {{ t('content.form.fields.layout_help') }}
                        </p>
                        <p
                            v-if="systemHint"
                            class="mt-1 text-[13px] font-medium text-ink-soft"
                        >
                            {{ systemHint }}
                        </p>
                    </div>

                    <section
                        class="flex flex-col gap-2 border-t border-line pt-5"
                        aria-labelledby="slide-image"
                    >
                        <h3
                            id="slide-image"
                            class="text-base font-semibold"
                        >
                            {{ t('content.form.image.title') }}
                        </h3>
                        <p
                            v-if="!needsImage"
                            class="text-sm leading-relaxed text-muted"
                        >
                            {{ t('content.form.image.none_led') }}
                        </p>
                        <template v-else>
                            <p class="text-sm leading-relaxed text-muted">
                                {{ t('content.form.image.intro') }}
                            </p>
                            <img
                                v-if="preview ?? slide?.imageUrl"
                                :src="preview ?? slide?.imageUrl ?? ''"
                                :alt="t('content.form.image.current', { slide: form.title })"
                                class="mt-1 aspect-video w-full max-w-xl rounded-[10px] border border-line bg-surface object-contain"
                            >
                            <label
                                for="slide-image-file"
                                class="mt-1 text-sm font-medium text-ink"
                            >{{ slide?.imageUrl ? t('content.form.image.replace') : t('content.form.image.choose') }}</label>
                            <input
                                id="slide-image-file"
                                ref="imageInput"
                                type="file"
                                accept="image/png"
                                class="min-h-11 text-sm file:mr-3 file:min-h-11 file:rounded-[10px] file:border file:border-line-strong file:bg-white file:px-3.5 file:text-sm file:font-medium"
                                :aria-invalid="form.errors.image ? 'true' : undefined"
                                @change="pickImage"
                            >
                            <p
                                v-if="form.errors.image"
                                class="text-[13px] leading-snug text-accent"
                            >
                                {{ form.errors.image }}
                            </p>
                        </template>
                    </section>

                    <section
                        class="flex flex-col gap-4 border-t border-line pt-5"
                        aria-labelledby="slide-conditions"
                    >
                        <div>
                            <h3
                                id="slide-conditions"
                                class="text-base font-semibold"
                            >
                                {{ t('content.form.conditions.title') }}
                            </h3>
                            <p class="mt-1 text-sm leading-relaxed text-muted">
                                {{ t('content.form.conditions.intro') }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <template
                                v-for="condition in options.conditions"
                                :key="condition.type"
                            >
                                <div v-if="condition.boolean">
                                    <SelectField
                                        :model-value="yesNo[condition.type] ?? ''"
                                        :label="condition.label"
                                        :options="yesNoOptions"
                                        :placeholder="t('content.form.conditions.any')"
                                        :error="conditionError(condition.type)"
                                        @update:model-value="(value: string) => (yesNo[condition.type] = value)"
                                    />
                                </div>
                                <fieldset
                                    v-else
                                    class="flex flex-col gap-1.5"
                                >
                                    <legend class="mb-1.5 text-sm font-medium text-ink">
                                        {{ condition.label }}
                                    </legend>
                                    <div class="flex flex-wrap gap-2">
                                        <label
                                            v-for="value in condition.values"
                                            :key="value.value"
                                            class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full border px-3.5 text-sm"
                                            :class="multi[condition.type]?.includes(value.value) ? 'border-accent bg-accent-soft text-accent-strong' : 'border-line-strong bg-white'"
                                        >
                                            <input
                                                type="checkbox"
                                                class="size-4 accent-accent"
                                                :checked="multi[condition.type]?.includes(value.value)"
                                                @change="toggle(condition.type, value.value, ($event.target as HTMLInputElement).checked)"
                                            >
                                            {{ value.label }}
                                        </label>
                                    </div>
                                    <p
                                        v-if="conditionError(condition.type)"
                                        class="text-[13px] leading-snug text-accent"
                                    >
                                        {{ conditionError(condition.type) }}
                                    </p>
                                </fieldset>
                            </template>
                        </div>
                    </section>

                    <CheckboxField
                        v-model="form.is_active"
                        :label="t('content.form.fields.is_active')"
                        :help="t('content.form.fields.is_active_help')"
                        :error="form.errors.is_active"
                    />
                </fieldset>

                <div
                    v-if="!readOnly"
                    class="flex flex-col-reverse gap-2 border-t border-line pt-5 sm:flex-row sm:justify-end"
                >
                    <Link
                        :href="indexUrl"
                        :class="buttonClasses.secondary"
                    >
                        {{ t('common.cancel') }}
                    </Link>
                    <button
                        type="submit"
                        :class="buttonClasses.primary"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? t('common.saving') : isNew ? t('content.form.create') : t('content.form.save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
