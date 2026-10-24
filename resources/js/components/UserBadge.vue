<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronDown, LogOut } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useTrans } from '@/composables/useTrans';
import type { LayoutData } from '@/types';

/**
 * User block of the top bar: name, role and a menu with the logout (PR-03).
 * The personal profile joins this menu with PR-04 (D-267).
 */
const props = defineProps<{ user: LayoutData['user'] }>();

const { t } = useTrans();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const toggle = ref<HTMLButtonElement | null>(null);
const leaving = ref(false);

function close(focusToggle = false): void {
    if (!open.value) {
        return;
    }
    open.value = false;
    if (focusToggle) {
        toggle.value?.focus();
    }
}

function onDocumentClick(event: MouseEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) {
        close();
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close(true);
    }
}

function logout(): void {
    leaving.value = true;
    router.post(props.user.logoutUrl, {}, { onFinish: () => (leaving.value = false) });
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div
        ref="root"
        class="relative"
        role="group"
        :aria-label="t('user.area')"
    >
        <button
            ref="toggle"
            type="button"
            class="flex min-h-11 items-center gap-3 rounded-[10px] px-1.5 hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
            :aria-label="t('user.menu')"
            :aria-expanded="open"
            aria-haspopup="menu"
            aria-controls="user-menu"
            @click="open = !open"
        >
            <span class="hidden text-right text-[13px] leading-tight sm:block">
                <span class="block font-semibold">
                    {{ user.name }}
                </span>
                <span class="block text-muted">
                    {{ user.role }}
                </span>
            </span>
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-navy text-[13px] font-semibold text-white"
                aria-hidden="true"
            >
                {{ user.initials }}
            </span>
            <ChevronDown
                :size="16"
                :stroke-width="2"
                class="hidden text-muted sm:block"
                aria-hidden="true"
            />
        </button>

        <div
            v-if="open"
            id="user-menu"
            role="menu"
            class="absolute top-full right-0 z-30 mt-2 w-56 rounded-xl border border-line bg-white p-1.5 shadow-lg"
        >
            <div class="border-b border-line px-3 pt-1.5 pb-2.5 text-[13px] leading-tight sm:hidden">
                <div class="font-semibold">
                    {{ user.name }}
                </div>
                <div class="text-muted">
                    {{ user.role }}
                </div>
            </div>
            <button
                type="button"
                role="menuitem"
                class="flex min-h-11 w-full items-center gap-2.5 rounded-lg px-3 text-left text-sm font-medium text-ink hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent disabled:opacity-60"
                :disabled="leaving"
                @click="logout"
            >
                <LogOut
                    :size="18"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{ t('user.logout') }}
            </button>
        </div>
    </div>
</template>
