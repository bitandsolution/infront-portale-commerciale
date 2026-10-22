<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Menu, X } from '@lucide/vue';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import SeasonSelector from '@/components/SeasonSelector.vue';
import SidebarNav from '@/components/SidebarNav.vue';
import ToastRegion from '@/components/ToastRegion.vue';
import UserBadge from '@/components/UserBadge.vue';
import { useTrans } from '@/composables/useTrans';
import type { LayoutData } from '@/types';

/**
 * Application layout (PR-01; ux-architettura "Navigazione per ruolo" and §4.5 breakpoints):
 * - desktop ≥ 1280 px: full side menu;
 * - tablet 768–1279 px: icon menu, groups open beside the rail;
 * - smartphone < 768 px: menu in a drawer opened from the top bar.
 */
defineProps<{ layout: LayoutData }>();

const { t } = useTrans();

const drawerOpen = ref(false);
const drawerClose = ref<HTMLButtonElement | null>(null);
const drawerToggle = ref<HTMLButtonElement | null>(null);

async function openDrawer(): Promise<void> {
    drawerOpen.value = true;
    await nextTick();
    drawerClose.value?.focus();
}

function closeDrawer(): void {
    if (!drawerOpen.value) {
        return;
    }
    drawerOpen.value = false;
    drawerToggle.value?.focus();
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        closeDrawer();
    }
}

// Prevent the page behind the drawer from scrolling.
watch(drawerOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

let removeNavigateListener: VoidFunction | null = null;

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    removeNavigateListener = router.on('navigate', () => {
        drawerOpen.value = false;
    });
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
    removeNavigateListener?.();
});
</script>

<template>
    <Head :title="layout.title" />

    <div class="flex h-dvh flex-col overflow-hidden bg-surface">
        <header class="flex h-14 shrink-0 items-center gap-3 border-b border-line bg-white px-3 md:gap-4 md:px-5">
            <button
                ref="drawerToggle"
                type="button"
                class="-ml-1 flex size-11 items-center justify-center rounded-[10px] text-ink hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent md:hidden"
                :aria-label="t('menu.open')"
                :aria-expanded="drawerOpen"
                aria-controls="mobile-drawer"
                @click="openDrawer"
            >
                <Menu
                    :size="22"
                    :stroke-width="2"
                    aria-hidden="true"
                />
            </button>

            <AppLogo />
            <div
                class="hidden h-6 w-px bg-line sm:block"
                aria-hidden="true"
            />
            <h1 class="min-w-0 truncate text-[15px] font-medium">
                {{ layout.title }}
            </h1>
            <SeasonSelector
                class="hidden md:flex"
                :season="layout.season"
            />
            <div class="flex-1" />
            <UserBadge :user="layout.user" />
        </header>

        <div class="flex min-h-0 flex-1">
            <!-- Tablet: icon rail -->
            <nav
                :aria-label="t('menu.label')"
                class="hidden w-16 shrink-0 flex-col border-r border-line bg-white py-3 md:flex xl:hidden"
            >
                <SidebarNav
                    :items="layout.navigation"
                    mode="rail"
                />
            </nav>

            <!-- Desktop: full menu -->
            <nav
                :aria-label="t('menu.label')"
                class="hidden w-64 shrink-0 flex-col overflow-y-auto border-r border-line bg-white p-3 xl:flex"
            >
                <SidebarNav
                    :items="layout.navigation"
                    mode="full"
                />
            </nav>

            <main class="min-w-0 flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>

        <!-- Smartphone: drawer -->
        <div
            v-if="drawerOpen"
            class="fixed inset-0 z-40 md:hidden"
        >
            <div
                class="absolute inset-0 bg-ink/45"
                aria-hidden="true"
                @click="closeDrawer"
            />
            <div
                id="mobile-drawer"
                role="dialog"
                aria-modal="true"
                :aria-label="t('menu.label')"
                class="absolute inset-y-0 left-0 flex w-[min(20rem,85vw)] flex-col bg-white shadow-xl"
            >
                <div class="flex h-14 shrink-0 items-center gap-3 border-b border-line px-3">
                    <AppLogo />
                    <div class="flex-1" />
                    <button
                        ref="drawerClose"
                        type="button"
                        class="flex size-11 items-center justify-center rounded-[10px] text-ink hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent"
                        :aria-label="t('menu.close')"
                        @click="closeDrawer"
                    >
                        <X
                            :size="22"
                            :stroke-width="2"
                            aria-hidden="true"
                        />
                    </button>
                </div>
                <div class="border-b border-line p-3">
                    <SeasonSelector :season="layout.season" />
                </div>
                <nav
                    :aria-label="t('menu.label')"
                    class="flex-1 overflow-y-auto p-3"
                >
                    <SidebarNav
                        :items="layout.navigation"
                        mode="full"
                        @navigate="closeDrawer"
                    />
                </nav>
            </div>
        </div>

        <ToastRegion />
    </div>
</template>
