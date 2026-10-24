<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { buttonClasses } from '@/components/buttons';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import AuthLayout from '@/layouts/AuthLayout.vue';

/**
 * Page of the reset link sent after the admin reset the password (D-279): new password, confirmed
 * with the code of the authenticator app. The second factor does not change.
 */
const props = defineProps<{
    firstName: string;
    account: string;
    submitUrl: string;
}>();

const { t } = useTrans();

const form = useForm({ password: '', password_confirmation: '', code: '' });

function submit(): void {
    form.transform((data) => ({ ...data, code: data.code.replace(/\s+/g, '') }))
        .post(props.submitUrl, { onFinish: () => form.reset('code') });
}
</script>

<template>
    <AuthLayout :title="t('access.reset_title')">
        <p class="mt-2 text-sm leading-relaxed text-muted">
            {{ t('access.reset_intro', { name: firstName }) }}
        </p>

        <form
            class="mt-6 flex flex-col gap-4"
            novalidate
            @submit.prevent="submit"
        >
            <!-- Hidden user name, so the password manager saves the right account. -->
            <input
                type="email"
                name="email"
                :value="account"
                autocomplete="username"
                class="hidden"
                readonly
            >
            <TextField
                v-model="form.password"
                :label="t('access.password')"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password"
            />
            <TextField
                v-model="form.password_confirmation"
                :label="t('access.password_confirmation')"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
            />
            <TextField
                v-model="form.code"
                :label="t('auth.challenge.code')"
                inputmode="numeric"
                autocomplete="one-time-code"
                :error="form.errors.code"
                mono
            />
            <button
                type="submit"
                :class="buttonClasses.primary"
                :disabled="form.processing"
            >
                {{ form.processing ? t('access.submitting') : t('access.reset_submit') }}
            </button>
        </form>
    </AuthLayout>
</template>
