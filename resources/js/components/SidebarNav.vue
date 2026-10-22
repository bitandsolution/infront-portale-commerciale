<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import NavIcon from '@/components/NavIcon.vue';
import { useTrans } from '@/composables/useTrans';
import type { NavItem } from '@/types';

/**
 * Side menu by role (ux-architettura, D-161).
 * - full: icons and labels, groups as an accordion (desktop ≥ 1280 px and the smartphone drawer);
 * - rail: icons only, groups open a panel beside the rail (tablet 768–1279 px).
 */
const props = defineProps<{
    items: NavItem[];
    mode: 'full' | 'rail';
}>();

const emit = defineEmits<{ navigate: [] }>();

const { t } = useTrans();

// Accordion: groups containing the active page start open.
const expanded = ref<Set<string>>(new Set(props.items.filter((i) => i.active && i.children.length).map((i) => i.key)));

function toggleGroup(key: string): void {
    const next = new Set(expanded.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    expanded.value = next;
}

// Rail: one flyout panel at a time.
const flyout = ref<string | null>(null);
const root = ref<HTMLElement | null>(null);

function toggleFlyout(key: string): void {
    flyout.value = flyout.value === key ? null : key;
}

function closeFlyout(): void {
    flyout.value = null;
}

function onDocumentClick(event: MouseEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) {
        closeFlyout();
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        closeFlyout();
    }
}

function onNavigate(): void {
    closeFlyout();
    emit('navigate');
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});

const itemBase = 'flex min-h-11 items-center rounded-[10px] transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
const itemIdle = 'text-muted hover:bg-surface hover:text-ink';
const itemActive = 'bg-accent-soft text-accent';
</script>

<template>
    <ul
        ref="root"
        class="flex flex-col gap-1"
        :class="mode === 'rail' ? 'items-center' : ''"
    >
        <li
            v-for="item in items"
            :key="item.key"
            class="relative"
            :class="mode === 'rail' ? '' : 'w-full'"
        >
            <!-- Rail mode -->
            <template v-if="mode === 'rail'">
                <Link
                    v-if="item.href"
                    :href="item.href"
                    :class="[itemBase, 'size-11 justify-center', item.active ? itemActive : itemIdle]"
                    :aria-label="item.label"
                    :title="item.label"
                    :aria-current="item.active ? 'page' : undefined"
                    @click="onNavigate"
                >
                    <NavIcon :name="item.icon" />
                </Link>
                <template v-else>
                    <button
                        type="button"
                        :class="[itemBase, 'size-11 justify-center', item.active ? itemActive : itemIdle]"
                        :aria-label="item.label"
                        :title="item.label"
                        :aria-expanded="flyout === item.key"
                        :aria-controls="`flyout-${item.key}`"
                        @click.stop="toggleFlyout(item.key)"
                    >
                        <NavIcon :name="item.icon" />
                    </button>
                    <div
                        v-if="flyout === item.key"
                        :id="`flyout-${item.key}`"
                        class="absolute top-0 left-full z-30 ml-3 w-60 rounded-xl border border-line bg-white p-2 shadow-lg"
                    >
                        <div class="px-3 pt-1 pb-2 text-xs font-semibold tracking-[0.6px] text-muted uppercase">
                            {{ item.label }}
                        </div>
                        <ul class="flex flex-col gap-0.5">
                            <li
                                v-for="child in item.children"
                                :key="child.key"
                            >
                                <Link
                                    :href="child.href"
                                    :class="[itemBase, 'px-3 text-sm', child.active ? itemActive + ' font-semibold' : 'text-ink hover:bg-surface']"
                                    :aria-current="child.active ? 'page' : undefined"
                                    @click="onNavigate"
                                >
                                    {{ child.label }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </template>
            </template>

            <!-- Full mode -->
            <template v-else>
                <Link
                    v-if="item.href"
                    :href="item.href"
                    :class="[itemBase, 'gap-3 px-3 text-[15px]', item.active ? itemActive + ' font-semibold' : itemIdle]"
                    :aria-current="item.active ? 'page' : undefined"
                    @click="onNavigate"
                >
                    <NavIcon :name="item.icon" />
                    <span>{{ item.label }}</span>
                </Link>
                <template v-else>
                    <button
                        type="button"
                        :class="[itemBase, 'w-full gap-3 px-3 text-left text-[15px]', item.active ? 'text-accent font-semibold' : itemIdle]"
                        :aria-expanded="expanded.has(item.key)"
                        :aria-controls="`group-${item.key}`"
                        @click="toggleGroup(item.key)"
                    >
                        <NavIcon :name="item.icon" />
                        <span class="flex-1">{{ item.label }}</span>
                        <ChevronDown
                            :size="18"
                            :stroke-width="2"
                            class="transition-transform"
                            :class="expanded.has(item.key) ? 'rotate-180' : ''"
                            aria-hidden="true"
                        />
                        <span class="sr-only">{{ t('menu.expand_group', { group: item.label }) }}</span>
                    </button>
                    <ul
                        v-show="expanded.has(item.key)"
                        :id="`group-${item.key}`"
                        class="mt-0.5 flex flex-col gap-0.5 pl-[46px]"
                    >
                        <li
                            v-for="child in item.children"
                            :key="child.key"
                        >
                            <Link
                                :href="child.href"
                                :class="[itemBase, 'px-3 text-sm', child.active ? itemActive + ' font-semibold' : itemIdle]"
                                :aria-current="child.active ? 'page' : undefined"
                                @click="onNavigate"
                            >
                                {{ child.label }}
                            </Link>
                        </li>
                    </ul>
                </template>
            </template>
        </li>
    </ul>
</template>
