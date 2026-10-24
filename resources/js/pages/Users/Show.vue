<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Info, KeyRound, Mail, Power, ShieldOff } from '@lucide/vue';
import { computed, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import SelectField from '@/components/forms/SelectField.vue';
import TextField from '@/components/forms/TextField.vue';
import UserStatusBadge from '@/components/users/UserStatusBadge.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, Option, UserDetail } from '@/types';

/**
 * One user, for the admin (M01 §5; D-139, D-140): data and role, access (resets of password and
 * second factor, new link), status (deactivation with confirmation, ux-architettura §4.5, and
 * reactivation). The server sends only the actions allowed now (never on the admin himself,
 * D-281); the rules are checked again on the server.
 */
type AccessAction = 'deactivate' | 'reactivate' | 'resetPassword' | 'resetTwoFactor' | 'resendLink';

const props = defineProps<{
    layout: LayoutData;
    user: UserDetail;
    roles: Option[];
    isSelf: boolean;
    actions: { update: string } & Record<AccessAction, string | null>;
    profileUrl: string | null;
    indexUrl: string;
}>();

const { t } = useTrans();

const form = useForm(fromUser(props.user));

function fromUser(user: UserDetail) {
    return {
        first_name: user.firstName,
        last_name: user.lastName,
        email: user.email,
        phone: user.phone,
        role: user.role,
    };
}

function save(): void {
    form.put(props.actions.update, {
        preserveScroll: true,
        // Without validation errors the form shows the values saved on the server: also when a rule
        // refused the change (e.g. the role of the last active admin), so unsaved data never look saved.
        onSuccess: () => {
            form.defaults(fromUser(props.user));
            form.reset();
        },
    });
}

/** Actions that ask for a confirmation first (ux §4.5). */
const confirming = ref<AccessAction | null>(null);
const running = ref<AccessAction | null>(null);

const confirmTitle = computed(() => {
    switch (confirming.value) {
        case 'deactivate':
            return t('users.confirm.deactivate_title', { name: props.user.name });
        case 'resetPassword':
            return t('users.confirm.reset_password_title', { name: props.user.name });
        case 'resetTwoFactor':
            return t('users.confirm.reset_two_factor_title', { name: props.user.name });
        default:
            return '';
    }
});

const confirmLabel = computed(() => {
    switch (confirming.value) {
        case 'deactivate':
            return t('users.confirm.deactivate');
        case 'resetPassword':
            return t('users.confirm.reset_password');
        default:
            return t('users.confirm.reset_two_factor');
    }
});

function run(action: AccessAction): void {
    const url = props.actions[action];
    if (url === null) {
        return;
    }
    running.value = action;
    router.post(url, {}, {
        preserveScroll: true,
        onFinish: () => {
            running.value = null;
            confirming.value = null;
        },
    });
}

const statusText = computed(() => {
    if (props.user.status === 'inactive') {
        return props.user.deactivatedBy
            ? t('users.show.inactive_by_text', { date: props.user.deactivatedAt ?? '', name: props.user.deactivatedBy })
            : t('users.show.inactive_text', { date: props.user.deactivatedAt ?? '' });
    }
    return props.user.status === 'pending' ? t('users.show.pending_text') : t('users.show.active_text');
});

const hasAccessActions = computed(
    () => props.actions.resetPassword !== null || props.actions.resetTwoFactor !== null || props.actions.resendLink !== null,
);
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-4xl flex-col gap-5 p-4 md:p-6">
            <Link
                :href="indexUrl"
                class="inline-flex min-h-11 items-center gap-2 self-start text-sm font-medium text-ink-soft hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
            >
                <ArrowLeft
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('users.show.back') }}
            </Link>

            <header class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-[14px] border border-line bg-white p-5">
                <div class="min-w-0 flex-1">
                    <h2 class="text-xl font-semibold">
                        {{ user.name }}
                    </h2>
                    <p class="text-sm break-all text-muted">
                        {{ user.email }} · {{ user.roleLabel }}
                    </p>
                </div>
                <UserStatusBadge :status="user.status" />
                <p
                    v-if="user.createdAt"
                    class="w-full text-[13px] text-muted"
                >
                    {{ t('users.show.created_at', { date: user.createdAt }) }}
                </p>
            </header>

            <p
                v-if="isSelf"
                role="note"
                class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-[10px] border border-line-strong bg-white px-4 py-3 text-sm text-ink-soft"
            >
                <Info
                    :size="18"
                    :stroke-width="2"
                    class="shrink-0"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1">{{ t('users.show.self_note') }}</span>
                <Link
                    v-if="profileUrl"
                    :href="profileUrl"
                    class="font-semibold text-accent underline-offset-2 hover:underline"
                >
                    {{ t('users.show.open_profile') }}
                </Link>
            </p>

            <!-- Data and role -->
            <form
                class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-5 md:p-6"
                novalidate
                @submit.prevent="save"
            >
                <h3 class="text-base font-semibold">
                    {{ t('users.show.details') }}
                </h3>
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
                    <div class="flex flex-col gap-1.5">
                        <SelectField
                            v-model="form.role"
                            :label="t('users.fields.role')"
                            :options="roles"
                            :error="form.errors.role"
                            :disabled="isSelf"
                        />
                        <p class="text-[13px] leading-snug text-muted">
                            {{ t('users.show.role_note') }}
                        </p>
                    </div>
                </div>
                <div class="flex justify-end border-t border-line pt-5">
                    <button
                        type="submit"
                        :class="buttonClasses.primary"
                        :disabled="form.processing || !form.isDirty"
                    >
                        {{ form.processing ? t('common.saving') : t('users.show.save') }}
                    </button>
                </div>
            </form>

            <!-- Access -->
            <section class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5 md:p-6">
                <h3 class="text-base font-semibold">
                    {{ t('users.show.access') }}
                </h3>
                <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-[13px] text-muted">
                            {{ t('users.show.two_factor') }}
                        </dt>
                        <dd class="font-medium">
                            {{ user.twoFactor ? t('users.two_factor.on') : t('users.two_factor.off') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[13px] text-muted">
                            {{ t('users.show.last_login') }}
                        </dt>
                        <dd class="font-mono text-[13px] font-medium">
                            {{ user.lastLogin ?? t('common.never') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[13px] text-muted">
                            {{ t('users.show.activated_at') }}
                        </dt>
                        <dd class="font-mono text-[13px] font-medium">
                            {{ user.activatedAt ?? '—' }}
                        </dd>
                    </div>
                </dl>

                <div
                    v-if="hasAccessActions"
                    class="flex flex-col divide-y divide-line border-t border-line"
                >
                    <div
                        v-if="actions.resendLink"
                        class="flex flex-col gap-3 py-4 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="max-w-xl text-sm leading-relaxed text-ink-soft">
                            {{ t('users.show.resend_link_help') }}
                        </p>
                        <button
                            type="button"
                            :class="buttonClasses.secondary"
                            class="shrink-0"
                            :disabled="running !== null"
                            @click="run('resendLink')"
                        >
                            <Mail
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('users.show.resend_link') }}
                        </button>
                    </div>
                    <div
                        v-if="actions.resetPassword"
                        class="flex flex-col gap-3 py-4 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="max-w-xl text-sm leading-relaxed text-ink-soft">
                            {{ t('users.show.reset_password_help') }}
                        </p>
                        <button
                            type="button"
                            :class="buttonClasses.secondary"
                            class="shrink-0"
                            :disabled="running !== null"
                            @click="confirming = 'resetPassword'"
                        >
                            <KeyRound
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('users.show.reset_password') }}
                        </button>
                    </div>
                    <div
                        v-if="actions.resetTwoFactor"
                        class="flex flex-col gap-3 py-4 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="max-w-xl text-sm leading-relaxed text-ink-soft">
                            {{ t('users.show.reset_two_factor_help') }}
                        </p>
                        <button
                            type="button"
                            :class="buttonClasses.secondary"
                            class="shrink-0"
                            :disabled="running !== null"
                            @click="confirming = 'resetTwoFactor'"
                        >
                            <ShieldOff
                                :size="18"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ t('users.show.reset_two_factor') }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- Status -->
            <section class="flex flex-col gap-4 rounded-[14px] border border-line bg-white p-5 md:p-6">
                <h3 class="text-base font-semibold">
                    {{ t('users.show.status') }}
                </h3>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex max-w-xl flex-col gap-1 text-sm leading-relaxed text-ink-soft">
                        <p>{{ statusText }}</p>
                        <p v-if="actions.deactivate">
                            {{ t('users.show.deactivate_help') }}
                        </p>
                        <p v-if="actions.reactivate">
                            {{ t('users.show.reactivate_help') }}
                        </p>
                    </div>
                    <button
                        v-if="actions.deactivate"
                        type="button"
                        :class="buttonClasses.danger"
                        class="shrink-0"
                        :disabled="running !== null"
                        @click="confirming = 'deactivate'"
                    >
                        <Power
                            :size="18"
                            :stroke-width="2"
                            aria-hidden="true"
                        />
                        {{ t('users.show.deactivate') }}
                    </button>
                    <button
                        v-if="actions.reactivate"
                        type="button"
                        :class="buttonClasses.secondary"
                        class="shrink-0"
                        :disabled="running !== null"
                        @click="run('reactivate')"
                    >
                        <Power
                            :size="18"
                            :stroke-width="2"
                            aria-hidden="true"
                        />
                        {{ t('users.show.reactivate') }}
                    </button>
                </div>
            </section>
        </div>

        <ConfirmDialog
            :open="confirming !== null"
            :title="confirmTitle"
            :confirm-label="confirmLabel"
            :processing="running !== null"
            @cancel="confirming = null"
            @confirm="confirming && run(confirming)"
        >
            <template v-if="confirming === 'deactivate'">
                <p>{{ t('users.show.deactivate_help') }}</p>
                <p v-if="user.role === 'agent'">
                    {{ t('users.show.deactivate_agent_help') }}
                </p>
            </template>
            <template v-else-if="confirming === 'resetPassword'">
                <p>{{ t('users.show.reset_password_help') }}</p>
                <p>{{ t('users.confirm.link_to', { email: user.email }) }}</p>
            </template>
            <template v-else-if="confirming === 'resetTwoFactor'">
                <p>{{ t('users.show.reset_two_factor_help') }}</p>
                <p>{{ t('users.confirm.link_to', { email: user.email }) }}</p>
            </template>
        </ConfirmDialog>
    </AppLayout>
</template>
