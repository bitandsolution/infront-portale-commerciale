<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { buttonClasses } from '@/components/buttons';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData } from '@/types';

/**
 * Personal profile (M01 §5; D-267), every role: name, surname and phone; change of the password
 * with the current one, which closes the other open sessions (D-273).
 */
const props = defineProps<{
    layout: LayoutData;
    profile: {
        firstName: string;
        lastName: string;
        email: string;
        phone: string;
        role: string;
        lastLogin: string | null;
    };
    updateUrl: string;
    passwordUrl: string;
}>();

const { t } = useTrans();

const details = useForm({
    first_name: props.profile.firstName,
    last_name: props.profile.lastName,
    phone: props.profile.phone,
});

const password = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function saveDetails(): void {
    details.put(props.updateUrl, {
        preserveScroll: true,
        onSuccess: () => details.defaults(),
    });
}

function changePassword(): void {
    password.put(props.passwordUrl, {
        preserveScroll: true,
        onFinish: () => password.reset(),
    });
}
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-3xl flex-col gap-5 p-4 md:p-6">
            <form
                class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5 md:p-6"
                novalidate
                @submit.prevent="saveDetails"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('profile.details') }}
                </h2>

                <dl class="grid grid-cols-1 gap-3 rounded-[10px] bg-surface p-4 text-sm sm:grid-cols-3">
                    <div class="min-w-0">
                        <dt class="text-[13px] text-muted">
                            {{ t('users.fields.email') }}
                        </dt>
                        <dd class="font-medium break-all">
                            {{ profile.email }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[13px] text-muted">
                            {{ t('profile.role') }}
                        </dt>
                        <dd class="font-medium">
                            {{ profile.role }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[13px] text-muted">
                            {{ t('profile.last_login') }}
                        </dt>
                        <dd class="font-mono text-[13px] font-medium">
                            {{ profile.lastLogin ?? t('common.never') }}
                        </dd>
                    </div>
                </dl>
                <p class="-mt-2 text-[13px] leading-snug text-muted">
                    {{ t('profile.email_note') }}
                </p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <TextField
                        v-model="details.first_name"
                        :label="t('users.fields.first_name')"
                        autocomplete="given-name"
                        :error="details.errors.first_name"
                    />
                    <TextField
                        v-model="details.last_name"
                        :label="t('users.fields.last_name')"
                        autocomplete="family-name"
                        :error="details.errors.last_name"
                    />
                    <TextField
                        v-model="details.phone"
                        :label="t('users.fields.phone')"
                        type="tel"
                        autocomplete="tel"
                        :error="details.errors.phone"
                    />
                </div>

                <div class="flex justify-end border-t border-line pt-5">
                    <button
                        type="submit"
                        :class="buttonClasses.primary"
                        :disabled="details.processing || !details.isDirty"
                    >
                        {{ details.processing ? t('common.saving') : t('profile.save') }}
                    </button>
                </div>
            </form>

            <form
                class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5 md:p-6"
                novalidate
                @submit.prevent="changePassword"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ t('profile.password') }}
                    </h2>
                    <p class="mt-1.5 text-sm leading-relaxed text-muted">
                        {{ t('profile.password_intro') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2 sm:max-w-[calc(50%-0.5rem)]">
                        <TextField
                            v-model="password.current_password"
                            :label="t('profile.current_password')"
                            type="password"
                            autocomplete="current-password"
                            :error="password.errors.current_password"
                        />
                    </div>
                    <TextField
                        v-model="password.password"
                        :label="t('profile.new_password')"
                        type="password"
                        autocomplete="new-password"
                        :error="password.errors.password"
                    />
                    <TextField
                        v-model="password.password_confirmation"
                        :label="t('profile.new_password_confirmation')"
                        type="password"
                        autocomplete="new-password"
                        :error="password.errors.password_confirmation"
                    />
                </div>

                <div class="flex justify-end border-t border-line pt-5">
                    <button
                        type="submit"
                        :class="buttonClasses.primary"
                        :disabled="password.processing"
                    >
                        {{ password.processing ? t('common.saving') : t('profile.password_submit') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
