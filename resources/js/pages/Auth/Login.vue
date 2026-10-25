<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import TextField from '@/components/forms/TextField.vue';
import { useTrans } from '@/composables/useTrans';
import AuthLayout from '@/layouts/AuthLayout.vue';

/**
 * Login (D-135, D-214): e-mail and password, then the second factor. The error message is the
 * same for a wrong e-mail and a wrong password. During a lockout (5 attempts, 15 minutes) the
 * password field stays disabled until the time is up.
 */
const props = defineProps<{
    loginUrl: string;
    /** "Password dimenticata?" (D-279; PR-08). */
    forgotUrl: string;
    /** Seconds left of a lockout just reported by the server (0 when there is none). */
    lockedSeconds: number;
}>();

const { t } = useTrans();

const form = useForm({ email: '', password: '' });

const secondsLeft = ref(0);
const locked = computed(() => secondsLeft.value > 0);
const timeLeft = computed(() => {
    const minutes = Math.floor(secondsLeft.value / 60);
    const seconds = secondsLeft.value % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
});

let timer: number | undefined;

// The lockout arrives with the answer to a login attempt, while the page stays mounted.
watch(
    () => props.lockedSeconds,
    (seconds) => {
        window.clearInterval(timer);
        secondsLeft.value = seconds;
        if (seconds > 0) {
            timer = window.setInterval(() => {
                secondsLeft.value = Math.max(0, secondsLeft.value - 1);
                if (secondsLeft.value === 0) {
                    window.clearInterval(timer);
                }
            }, 1000);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => window.clearInterval(timer));

function submit(): void {
    form.post(props.loginUrl, {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <AuthLayout :title="t('auth.login.title')">
        <p class="mt-2 text-sm leading-relaxed text-muted">
            {{ t('auth.login.intro') }}
        </p>

        <form
            class="mt-6 flex flex-col gap-4"
            novalidate
            @submit.prevent="submit"
        >
            <TextField
                v-model="form.email"
                :label="t('auth.login.email')"
                type="email"
                inputmode="email"
                autocomplete="username"
                :error="form.errors.email"
                autofocus
            />
            <TextField
                v-model="form.password"
                :label="t('auth.login.password')"
                type="password"
                autocomplete="current-password"
                :error="form.errors.password"
                :disabled="locked"
            />

            <p
                v-if="locked"
                role="status"
                class="rounded-[10px] border border-notice-line bg-notice-soft px-3.5 py-2.5 text-[13px] leading-snug text-notice"
            >
                {{ t('auth.login.locked', { time: timeLeft }) }}
            </p>

            <button
                type="submit"
                class="mt-1 inline-flex min-h-11 items-center justify-center rounded-[10px] bg-accent px-5 text-[15px] font-semibold text-white hover:bg-accent-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="form.processing || locked"
            >
                {{ form.processing ? t('auth.login.submitting') : t('auth.login.submit') }}
            </button>
        </form>

        <p class="mt-4 text-center text-sm">
            <Link
                :href="forgotUrl"
                class="font-medium text-accent hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
            >
                {{ t('auth.login.forgot') }}
            </Link>
        </p>

        <p class="mt-6 text-[13px] leading-relaxed text-muted">
            {{ t('auth.login.help') }}
        </p>
    </AuthLayout>
</template>
