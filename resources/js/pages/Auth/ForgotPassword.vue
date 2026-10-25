<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { buttonClasses } from '@/components/buttons';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import AuthLayout from '@/layouts/AuthLayout.vue';

/**
 * "Password dimenticata?" (D-279; PR-08): the e-mail of the account, then always the same message,
 * whatever the address (it never says whether an account exists). The link arrives by e-mail and
 * opens the page of the new password, confirmed with the code of the authenticator app.
 */
const props = defineProps<{
    submitUrl: string;
    loginUrl: string;
    /** A request has just been accepted: the same message for every address. */
    sent: boolean;
}>();

const { t } = useTrans();

const form = useForm({ email: '' });

function submit(): void {
    form.post(props.submitUrl, { onSuccess: () => form.reset('email') });
}
</script>

<template>
    <AuthLayout :title="t('auth.forgot.title')">
        <p class="mt-2 text-sm leading-relaxed text-muted">
            {{ t('auth.forgot.intro') }}
        </p>

        <p
            v-if="sent"
            role="status"
            class="mt-4 rounded-[10px] border border-line bg-surface px-3.5 py-2.5 text-[13px] leading-snug text-ink"
        >
            {{ t('auth.forgot.sent') }}
        </p>

        <form
            class="mt-6 flex flex-col gap-4"
            novalidate
            @submit.prevent="submit"
        >
            <TextField
                v-model="form.email"
                :label="t('auth.forgot.email')"
                type="email"
                inputmode="email"
                autocomplete="username"
                :error="form.errors.email"
                autofocus
            />
            <button
                type="submit"
                :class="buttonClasses.primary"
                :disabled="form.processing"
            >
                {{ form.processing ? t('auth.forgot.submitting') : t('auth.forgot.submit') }}
            </button>
        </form>

        <p class="mt-4 text-[13px] leading-relaxed text-muted">
            {{ t('auth.forgot.note') }}
        </p>

        <Link
            :href="loginUrl"
            :class="buttonClasses.secondary"
            class="mt-6 w-full"
        >
            {{ t('auth.forgot.back') }}
        </Link>
    </AuthLayout>
</template>
