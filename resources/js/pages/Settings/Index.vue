<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { buttonClasses } from '@/components/buttons';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData } from '@/types';

/**
 * Settings page (PR-07; D-191, D-286): admin only. The editable values are whole numbers in a
 * single form, validated again by the server; the fixed ones are read-only, with the reason.
 * Labels, descriptions, units and allowed ranges come from the server (SettingKey).
 */
interface SettingItem {
    key: string;
    label: string;
    help: string;
    editable: boolean;
    fixedNote: string;
    value: number | string;
    display: string;
    unit: string;
    min: number | null;
    max: number | null;
    isDefault: boolean;
    lastChange: string | null;
}

interface SettingGroup {
    key: string;
    title: string;
    items: SettingItem[];
}

const props = defineProps<{
    layout: LayoutData;
    groups: SettingGroup[];
    updateUrl: string;
}>();

const { t } = useTrans();

const form = useForm<Record<string, string>>(
    Object.fromEntries(
        props.groups.flatMap((group) => group.items.filter((item) => item.editable).map((item) => [item.key, String(item.value)])),
    ),
);

function save(): void {
    form.put(props.updateUrl, {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}
</script>

<template>
    <AppLayout :layout="layout">
        <form
            class="mx-auto flex max-w-3xl flex-col gap-5 p-4 md:p-6"
            novalidate
            @submit.prevent="save"
        >
            <p class="text-sm leading-relaxed text-muted">
                {{ t('settings.intro') }}
            </p>

            <section
                v-for="group in groups"
                :key="group.key"
                class="flex flex-col rounded-[14px] border border-line bg-white"
                :aria-labelledby="`settings-group-${group.key}`"
            >
                <h2
                    :id="`settings-group-${group.key}`"
                    class="border-b border-line px-5 py-4 text-lg font-semibold md:px-6"
                >
                    {{ group.title }}
                </h2>

                <div
                    v-for="item in group.items"
                    :key="item.key"
                    class="flex flex-col gap-2 border-b border-line px-5 py-4 last:border-b-0 md:px-6"
                >
                    <template v-if="item.editable">
                        <div class="sm:max-w-[calc(50%-0.5rem)]">
                            <TextField
                                :model-value="form[item.key] ?? ''"
                                :label="item.label"
                                type="number"
                                inputmode="numeric"
                                :error="form.errors[item.key]"
                                @update:model-value="(value: string) => (form[item.key] = value)"
                            />
                        </div>
                        <p class="text-[13px] leading-snug text-muted">
                            {{ item.help }}
                            {{ t('settings.range', { min: String(item.min), max: String(item.max), unit: item.unit }) }}
                        </p>
                    </template>

                    <template v-else>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-sm font-medium text-ink">{{ item.label }}</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-surface px-2.5 py-1 text-[13px] font-medium text-muted">
                                <Lock
                                    :size="14"
                                    :stroke-width="2.2"
                                    aria-hidden="true"
                                />
                                {{ t('settings.fixed') }}
                            </span>
                        </div>
                        <p class="text-[15px] font-semibold text-ink">
                            {{ item.display }}
                        </p>
                        <p class="text-[13px] leading-snug text-muted">
                            {{ item.help }} {{ item.fixedNote }}
                        </p>
                    </template>

                    <p
                        v-if="item.isDefault || item.lastChange"
                        class="text-[13px] text-muted"
                    >
                        <span v-if="item.isDefault">{{ t('settings.default_value') }}</span>
                        <span v-else>{{ item.lastChange }}</span>
                    </p>
                </div>
            </section>

            <div class="flex justify-end">
                <button
                    type="submit"
                    :class="buttonClasses.primary"
                    :disabled="form.processing || !form.isDirty"
                >
                    {{ form.processing ? t('common.saving') : t('settings.save') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
