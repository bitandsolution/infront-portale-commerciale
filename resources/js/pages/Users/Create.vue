<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Mail } from '@lucide/vue';
import { buttonClasses } from '@/components/buttons';
import SelectField from '@/components/forms/SelectField.vue';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, Option } from '@/types';

/**
 * New user (M01 §5; D-139): the user receives the activation link by e-mail (48 hours) and sets
 * password and second factor himself (D-277, D-278).
 */
const props = defineProps<{
    layout: LayoutData;
    roles: Option[];
    storeUrl: string;
    indexUrl: string;
}>();

const { t } = useTrans();

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    role: 'agent',
});

function submit(): void {
    form.post(props.storeUrl);
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-3xl flex-col gap-5 p-4 md:p-6">
            <Link
                :href="indexUrl"
                class="inline-flex min-h-11 items-center gap-2 self-start text-sm font-medium text-ink-soft hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
            >
                <ArrowLeft
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('users.create.back') }}
            </Link>

            <form
                class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5 md:p-6"
                novalidate
                @submit.prevent="submit"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ t('users.create.title') }}
                    </h2>
                    <p class="mt-1.5 flex items-start gap-2 text-sm leading-relaxed text-muted">
                        <Mail
                            :size="18"
                            :stroke-width="2"
                            class="mt-0.5 shrink-0"
                            aria-hidden="true"
                        />
                        {{ t('users.create.intro') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <TextField
                        v-model="form.first_name"
                        :label="t('users.fields.first_name')"
                        autocomplete="off"
                        :error="form.errors.first_name"
                    />
                    <TextField
                        v-model="form.last_name"
                        :label="t('users.fields.last_name')"
                        autocomplete="off"
                        :error="form.errors.last_name"
                    />
                    <TextField
                        v-model="form.email"
                        :label="t('users.fields.email')"
                        type="email"
                        inputmode="email"
                        autocomplete="off"
                        :error="form.errors.email"
                    />
                    <TextField
                        v-model="form.phone"
                        :label="t('users.fields.phone')"
                        type="tel"
                        autocomplete="off"
                        :error="form.errors.phone"
                    />
                    <SelectField
                        v-model="form.role"
                        :label="t('users.fields.role')"
                        :options="roles"
                        :error="form.errors.role"
                    />
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-line pt-5 sm:flex-row sm:justify-end">
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
                        {{ form.processing ? t('users.create.submitting') : t('users.create.submit') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
