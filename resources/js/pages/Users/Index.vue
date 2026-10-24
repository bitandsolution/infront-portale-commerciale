<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Plus, Search, UserCog } from '@lucide/vue';
import { computed, ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import SelectField from '@/components/forms/SelectField.vue';
import EmptyState from '@/components/states/EmptyState.vue';
import UserStatusBadge from '@/components/users/UserStatusBadge.vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LayoutData, Option, UserRow } from '@/types';

/**
 * Users page (M01 §5; D-139: admin only): role, status, second factor and last login of every
 * user. A few dozen users at most (D-148): the whole list arrives from the server and the filters
 * work in the browser.
 */
const props = defineProps<{
    layout: LayoutData;
    users: UserRow[];
    roles: Option[];
    createUrl: string;
}>();

const { t } = useTrans();

const search = ref('');
const role = ref('');
const status = ref('');

const statusOptions = computed<Option[]>(() =>
    (['active', 'pending', 'inactive'] as const).map((value) => ({ value, label: t(`users.status.${value}`) })),
);

const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('it');

    return props.users.filter(
        (user) =>
            (role.value === '' || user.role === role.value) &&
            (status.value === '' || user.status === status.value) &&
            (term === '' || user.name.toLocaleLowerCase('it').includes(term) || user.email.includes(term)),
    );
});
</script>

<template>
    <AppLayout :layout="layout">
        <div class="mx-auto flex max-w-6xl flex-col gap-5 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted">
                    {{ t('users.count', { count: String(filtered.length) }) }}
                </p>
                <Link
                    :href="createUrl"
                    :class="buttonClasses.primary"
                >
                    <Plus
                        :size="18"
                        :stroke-width="2.2"
                        aria-hidden="true"
                    />
                    {{ t('users.new') }}
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-3 rounded-[14px] border border-line bg-white p-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
                <div class="flex flex-col gap-1.5">
                    <label
                        for="users-search"
                        class="text-sm font-medium text-ink"
                    >
                        {{ t('users.search') }}
                    </label>
                    <div class="relative">
                        <Search
                            :size="18"
                            :stroke-width="2"
                            class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted"
                            aria-hidden="true"
                        />
                        <input
                            id="users-search"
                            v-model="search"
                            type="search"
                            autocomplete="off"
                            class="min-h-11 w-full rounded-[10px] border border-line-strong bg-white pr-3.5 pl-10 text-[15px] text-ink outline-none focus:border-accent focus:ring-2 focus:ring-accent/20"
                        >
                    </div>
                </div>
                <SelectField
                    v-model="role"
                    :label="t('users.filter_role')"
                    :options="roles"
                    :placeholder="t('users.all_roles')"
                />
                <SelectField
                    v-model="status"
                    :label="t('users.filter_status')"
                    :options="statusOptions"
                    :placeholder="t('users.all_statuses')"
                />
            </div>

            <div class="overflow-hidden rounded-[14px] border border-line bg-white">
                <EmptyState
                    v-if="filtered.length === 0"
                    :title="t('users.empty_title')"
                    :message="t('users.empty_message')"
                >
                    <template #icon>
                        <UserCog
                            :size="34"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                    </template>
                </EmptyState>

                <!-- Tablet and desktop: table -->
                <table
                    v-if="filtered.length > 0"
                    class="hidden w-full text-left text-sm md:table"
                >
                    <thead class="border-b border-line bg-surface text-[13px] text-muted">
                        <tr>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('users.columns.name') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('users.columns.role') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('users.columns.status') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('users.columns.two_factor') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 font-medium"
                            >
                                {{ t('users.columns.last_login') }}
                            </th>
                            <th
                                scope="col"
                                class="w-12 px-2 py-3"
                            >
                                <span class="sr-only">{{ t('users.columns.name') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in filtered"
                            :key="user.id"
                            class="border-b border-line last:border-b-0 hover:bg-surface/60"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="user.url"
                                    class="font-semibold text-ink underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-accent"
                                >
                                    {{ user.name }}
                                </Link>
                                <div class="text-[13px] break-all text-muted">
                                    {{ user.email }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                {{ user.roleLabel }}
                            </td>
                            <td class="px-4 py-3">
                                <UserStatusBadge :status="user.status" />
                            </td>
                            <td class="px-4 py-3">
                                {{ user.twoFactor ? t('users.two_factor.on') : t('users.two_factor.off') }}
                            </td>
                            <td class="px-4 py-3 font-mono text-[13px] whitespace-nowrap">
                                {{ user.lastLogin ?? t('common.never') }}
                            </td>
                            <td class="px-2 py-3">
                                <Link
                                    :href="user.url"
                                    class="flex size-11 items-center justify-center rounded-[10px] text-muted hover:bg-surface hover:text-ink focus-visible:outline-2 focus-visible:outline-accent"
                                    :aria-label="t('users.open', { name: user.name })"
                                >
                                    <ChevronRight
                                        :size="18"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Smartphone: cards -->
                <ul
                    v-if="filtered.length > 0"
                    class="divide-y divide-line md:hidden"
                >
                    <li
                        v-for="user in filtered"
                        :key="user.id"
                    >
                        <Link
                            :href="user.url"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold">
                                    {{ user.name }}
                                </div>
                                <div class="text-[13px] break-all text-muted">
                                    {{ user.email }}
                                </div>
                                <div class="mt-1.5 flex flex-wrap items-center gap-2 text-[13px]">
                                    <span>{{ user.roleLabel }}</span>
                                    <UserStatusBadge :status="user.status" />
                                </div>
                            </div>
                            <ChevronRight
                                :size="18"
                                :stroke-width="2"
                                class="shrink-0 text-muted"
                                aria-hidden="true"
                            />
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
