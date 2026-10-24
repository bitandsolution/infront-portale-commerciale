<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import AuthLayout from '@/layouts/AuthLayout.vue';

/**
 * Second step of every login (D-136, D-214): code from the authenticator app or, without the
 * smartphone, one of the single-use recovery codes. Wrong codes count towards the lockout.
 */
const props = defineProps<{
    challengeUrl: string;
    loginUrl: string;
}>();

const { t } = useTrans();

const useRecovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });
const fields = ref<HTMLElement | null>(null);

async function toggleMode(): Promise<void> {
    useRecovery.value = !useRecovery.value;
    form.reset();
    form.clearErrors();
    await nextTick();
    fields.value?.querySelector('input')?.focus();
}

function submit(): void {
    form.transform((data) => (useRecovery.value ? { recovery_code: data.recovery_code.trim() } : { code: data.code.replace(/\s+/g, '') }))
        .post(props.challengeUrl, { onFinish: () => form.reset() });
}
</script>

<template>
    <AuthLayout :title="t('auth.challenge.title')">
        <p class="mt-2 text-sm leading-relaxed text-muted">
            {{ useRecovery ? t('auth.challenge.recovery_intro') : t('auth.challenge.intro') }}
        </p>

        <form
            class="mt-6 flex flex-col gap-4"
            novalidate
            @submit.prevent="submit"
        >
            <div ref="fields">
                <TextField
                    v-if="!useRecovery"
                    v-model="form.code"
                    :label="t('auth.challenge.code')"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    :error="form.errors.code"
                    mono
                    autofocus
                />
                <TextField
                    v-else
                    v-model="form.recovery_code"
                    :label="t('auth.challenge.recovery_code')"
                    autocomplete="off"
                    :error="form.errors.recovery_code"
                    mono
                />
            </div>

            <button
                type="submit"
                class="mt-1 inline-flex min-h-11 items-center justify-center rounded-[10px] bg-accent px-5 text-[15px] font-semibold text-white hover:bg-accent-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="form.processing"
            >
                {{ form.processing ? t('auth.challenge.submitting') : t('auth.challenge.submit') }}
            </button>
        </form>

        <div class="mt-6 flex flex-col items-start gap-1 text-sm">
            <button
                type="button"
                class="inline-flex min-h-11 items-center font-medium text-accent underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                @click="toggleMode"
            >
                {{ useRecovery ? t('auth.challenge.use_code') : t('auth.challenge.use_recovery') }}
            </button>
            <Link
                :href="loginUrl"
                class="inline-flex min-h-11 items-center text-muted underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-accent"
            >
                {{ t('auth.challenge.back') }}
            </Link>
        </div>
    </AuthLayout>
</template>
