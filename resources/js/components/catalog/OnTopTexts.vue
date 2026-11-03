<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import type { OnTopData, OnTopItem } from '@/types';

/**
 * On-top texts of a price list (M03 F-10; D-049, D-335): general on the page "Partnership", or of a
 * club on its benefit page. Only descriptive texts, never a price. The admin adds, changes and
 * removes them (removal after a confirmation); the manager reads them.
 */
const props = defineProps<{
    title: string;
    intro: string;
    data: OnTopData;
    canManage: boolean;
}>();

const { t } = useTrans();

const editingId = ref<number | 'new' | null>(null);
const form = useForm({ club_id: null as number | null, title: '', body: '' });

function startNew(): void {
    form.clearErrors();
    form.title = '';
    form.body = '';
    form.club_id = props.data.clubId;
    editingId.value = 'new';
}

function startEdit(item: OnTopItem): void {
    form.clearErrors();
    form.title = item.title;
    form.body = item.body;
    form.club_id = props.data.clubId;
    editingId.value = item.id;
}

function cancel(): void {
    editingId.value = null;
    form.clearErrors();
}

function submit(item: OnTopItem | null): void {
    const options = { preserveScroll: true, onSuccess: () => cancel() };
    if (item === null) {
        form.post(props.data.createUrl, options);
    } else {
        form.put(item.updateUrl, options);
    }
}

const removing = ref<OnTopItem | null>(null);
const removeProcessing = ref(false);

function remove(): void {
    const item = removing.value;
    if (item === null) {
        return;
    }
    removeProcessing.value = true;
    router.delete(item.removeUrl, {
        preserveScroll: true,
        onFinish: () => {
            removeProcessing.value = false;
            removing.value = null;
        },
    });
}
</script>

<template>
    <section class="rounded-[14px] border border-line bg-white">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-4 py-3">
            <div class="min-w-0 flex-1">
                <h2 class="text-base font-semibold">
                    {{ title }}
                </h2>
                <p class="mt-0.5 text-sm text-muted">
                    {{ intro }}
                </p>
            </div>
            <button
                v-if="canManage && editingId === null"
                type="button"
                :class="buttonClasses.secondary"
                @click="startNew"
            >
                <Plus
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('catalog.on_top.add') }}
            </button>
        </header>

        <p
            v-if="data.items.length === 0 && editingId !== 'new'"
            class="px-4 py-4 text-sm text-muted"
        >
            {{ t('catalog.on_top.empty') }}
        </p>

        <ul class="divide-y divide-line">
            <li
                v-for="item in data.items"
                :key="item.id"
                class="px-4 py-3"
            >
                <form
                    v-if="editingId === item.id"
                    class="flex flex-col gap-3"
                    @submit.prevent="submit(item)"
                >
                    <TextField
                        v-model="form.title"
                        :label="t('catalog.on_top.field_title')"
                        :error="form.errors.title"
                    />
                    <div class="flex flex-col gap-1.5">
                        <label
                            :for="`on-top-body-${item.id}`"
                            class="text-sm font-medium text-ink"
                        >{{ t('catalog.on_top.field_body') }}</label>
                        <textarea
                            :id="`on-top-body-${item.id}`"
                            v-model="form.body"
                            rows="4"
                            class="rounded-[10px] border border-line-strong bg-white px-3 py-2 text-[15px] focus-visible:outline-2 focus-visible:outline-accent"
                            :aria-invalid="form.errors.body !== undefined"
                        />
                        <p
                            v-if="form.errors.body"
                            class="text-sm text-accent"
                        >
                            {{ form.errors.body }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="submit"
                            :class="buttonClasses.primary"
                            :disabled="form.processing"
                        >
                            {{ t('catalog.on_top.save') }}
                        </button>
                        <button
                            type="button"
                            :class="buttonClasses.secondary"
                            :disabled="form.processing"
                            @click="cancel"
                        >
                            {{ t('catalog.on_top.cancel') }}
                        </button>
                    </div>
                </form>
                <div
                    v-else
                    class="flex items-start gap-3"
                >
                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold">
                            {{ item.title }}
                        </h3>
                        <p class="mt-1 text-sm whitespace-pre-line text-ink-soft">
                            {{ item.body }}
                        </p>
                    </div>
                    <template v-if="canManage && editingId === null">
                        <button
                            type="button"
                            class="inline-flex size-11 items-center justify-center rounded-[10px] text-ink-soft hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                            :aria-label="t('catalog.on_top.edit', { title: item.title })"
                            @click="startEdit(item)"
                        >
                            <Pencil
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                        </button>
                        <button
                            type="button"
                            class="inline-flex size-11 items-center justify-center rounded-[10px] text-accent hover:bg-accent-soft focus-visible:outline-2 focus-visible:outline-accent"
                            :aria-label="t('catalog.on_top.remove', { title: item.title })"
                            @click="removing = item"
                        >
                            <Trash2
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                        </button>
                    </template>
                </div>
            </li>
        </ul>

        <form
            v-if="editingId === 'new'"
            class="flex flex-col gap-3 border-t border-line px-4 py-3"
            @submit.prevent="submit(null)"
        >
            <TextField
                v-model="form.title"
                :label="t('catalog.on_top.field_title')"
                :error="form.errors.title"
                autofocus
            />
            <div class="flex flex-col gap-1.5">
                <label
                    for="on-top-body-new"
                    class="text-sm font-medium text-ink"
                >{{ t('catalog.on_top.field_body') }}</label>
                <textarea
                    id="on-top-body-new"
                    v-model="form.body"
                    rows="4"
                    class="rounded-[10px] border border-line-strong bg-white px-3 py-2 text-[15px] focus-visible:outline-2 focus-visible:outline-accent"
                    :aria-invalid="form.errors.body !== undefined"
                />
                <p
                    v-if="form.errors.body"
                    class="text-sm text-accent"
                >
                    {{ form.errors.body }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="submit"
                    :class="buttonClasses.primary"
                    :disabled="form.processing"
                >
                    {{ t('catalog.on_top.save') }}
                </button>
                <button
                    type="button"
                    :class="buttonClasses.secondary"
                    :disabled="form.processing"
                    @click="cancel"
                >
                    {{ t('catalog.on_top.cancel') }}
                </button>
            </div>
        </form>

        <ConfirmDialog
            :open="removing !== null"
            :title="t('catalog.on_top.remove_title', { title: removing?.title ?? '' })"
            :confirm-label="t('catalog.on_top.remove_confirm')"
            :processing="removeProcessing"
            @confirm="remove"
            @cancel="removing = null"
        >
            <p>{{ t('catalog.on_top.remove_body') }}</p>
        </ConfirmDialog>
    </section>
</template>
