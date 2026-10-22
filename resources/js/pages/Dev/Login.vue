<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import ToastRegion from '@/components/ToastRegion.vue';
import { useTrans } from '@/composables/useTrans';

/**
 * Development access (D-268): local environment only, removed by PR-03.
 * Lists the users of the local database; "Entra" signs in as that user without a password.
 */
interface DevUser {
    id: number;
    name: string;
    email: string;
    role: string;
    active: boolean;
    loginUrl: string;
}

defineProps<{
    users: DevUser[];
    current: number | null;
    logoutUrl: string;
}>();

const { t } = useTrans();

function signIn(url: string): void {
    router.post(url);
}

function signOut(url: string): void {
    router.post(url);
}

const button = 'inline-flex min-h-11 items-center justify-center rounded-[10px] px-4 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
</script>

<template>
    <Head :title="t('dev_login.title')" />

    <main class="min-h-screen bg-surface p-4 md:p-8">
        <div class="mx-auto flex max-w-4xl flex-col gap-5">
            <div class="flex items-center gap-3">
                <AppLogo />
                <div class="flex-1" />
                <button
                    v-if="current !== null"
                    type="button"
                    :class="[button, 'border border-line-strong bg-white text-ink hover:bg-surface']"
                    @click="signOut(logoutUrl)"
                >
                    {{ t('dev_login.sign_out') }}
                </button>
            </div>

            <h1 class="text-2xl font-semibold">
                {{ t('dev_login.title') }}
            </h1>

            <p
                role="note"
                class="rounded-xl border border-notice-line bg-notice-soft px-4 py-3 text-sm leading-relaxed text-notice"
            >
                {{ t('dev_login.notice') }}
            </p>

            <p
                v-if="users.length === 0"
                class="rounded-[14px] border border-line bg-white p-5 text-[15px] text-muted"
            >
                {{ t('dev_login.empty') }}
            </p>

            <div
                v-else
                class="overflow-x-auto rounded-[14px] border border-line bg-white"
            >
                <table class="w-full text-left text-[15px]">
                    <thead class="border-b border-line text-xs font-semibold tracking-[0.6px] text-muted uppercase">
                        <tr>
                            <th
                                scope="col"
                                class="px-4 py-3"
                            >
                                {{ t('dev_login.name') }}
                            </th>
                            <th
                                scope="col"
                                class="hidden px-4 py-3 md:table-cell"
                            >
                                {{ t('dev_login.email') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3"
                            >
                                {{ t('dev_login.role') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3"
                            >
                                {{ t('dev_login.status') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3"
                            >
                                <span class="sr-only">{{ t('dev_login.sign_in') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="border-b border-line last:border-b-0"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ user.name }}
                            </td>
                            <td class="hidden px-4 py-3 font-mono text-sm md:table-cell">
                                {{ user.email }}
                            </td>
                            <td class="px-4 py-3">
                                {{ user.role }}
                            </td>
                            <td class="px-4 py-3">
                                <span :class="user.active ? 'text-success' : 'text-accent-strong'">
                                    {{ user.active ? t('dev_login.active') : t('dev_login.inactive') }}
                                </span>
                                <span
                                    v-if="user.id === current"
                                    class="ml-2 text-sm text-muted"
                                >
                                    · {{ t('dev_login.current') }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <button
                                    type="button"
                                    :class="[button, 'bg-accent text-white hover:bg-accent-strong']"
                                    :aria-label="t('dev_login.sign_in_as', { name: user.name })"
                                    @click="signIn(user.loginUrl)"
                                >
                                    {{ t('dev_login.sign_in') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Link
                href="/"
                class="text-sm font-semibold text-accent-strong underline underline-offset-2"
            >
                {{ t('errors.back_home') }}
            </Link>
        </div>

        <ToastRegion />
    </main>
</template>
