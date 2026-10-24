<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { buttonClasses } from '@/components/buttons';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import AuthLayout from '@/layouts/AuthLayout.vue';

/**
 * Page of the activation link (D-278), also used after a reset of the second factor (D-280):
 * password and second factor on one page, saved together when the code of the app is right.
 * Until then nothing is saved: leaving halfway and reopening the link starts again from here.
 */
const props = defineProps<{
    firstName: string;
    account: string;
    /** True after a reset of the second factor: the account was already active. */
    renewal: boolean;
    qrCode: string;
    secret: string;
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
    <AuthLayout
        :title="renewal ? t('access.renewal_title') : t('access.activate_title')"
        wide
    >
        <p class="mt-2 text-sm leading-relaxed text-muted">
            {{ t('access.activate_intro', { name: firstName }) }}
        </p>

        <form
            class="mt-6 flex flex-col gap-6 text-sm leading-relaxed"
            novalidate
            @submit.prevent="submit"
        >
            <section class="flex gap-3">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-navy text-xs font-semibold text-white">1</span>
                <div class="flex min-w-0 flex-1 flex-col gap-4">
                    <h2 class="font-semibold">
                        {{ t('access.step_password') }}
                    </h2>
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
                </div>
            </section>

            <section class="flex gap-3">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-navy text-xs font-semibold text-white">2</span>
                <div class="flex min-w-0 flex-1 flex-col gap-3">
                    <h2 class="font-semibold">
                        {{ t('access.step_app') }}
                    </h2>
                    <p>{{ t('auth.setup.step_scan') }}</p>
                    <img
                        :src="qrCode"
                        :alt="t('auth.setup.qr_alt')"
                        width="192"
                        height="192"
                        class="size-48 rounded-lg border border-line bg-white p-2"
                    >
                    <p>{{ t('auth.setup.step_key') }}</p>
                    <code class="block rounded-lg bg-surface px-3 py-2.5 font-mono text-[15px] tracking-wider break-all select-all">{{ secret }}</code>
                    <p class="text-[13px] text-muted">
                        {{ t('auth.setup.account', { account }) }}
                    </p>
                </div>
            </section>

            <section class="flex gap-3">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-navy text-xs font-semibold text-white">3</span>
                <div class="flex min-w-0 flex-1 flex-col gap-4">
                    <h2 class="font-semibold">
                        {{ t('access.step_code') }}
                    </h2>
                    <TextField
                        v-model="form.code"
                        :label="t('access.code')"
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
                        {{ form.processing ? t('access.submitting') : t('access.activate_submit') }}
                    </button>
                </div>
            </section>
        </form>
    </AuthLayout>
</template>
