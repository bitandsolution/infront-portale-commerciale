<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Info, LayoutTemplate, Plus } from '@lucide/vue';
import { ref } from 'vue';
import { buttonClasses } from '@/components/buttons';
import CustomSlideEditor from '@/components/proposals/CustomSlideEditor.vue';
import { useTrans } from '@/composables/useTrans';
import type { VisitQueue } from '@/composables/visitQueue';
import type { CustomSlides } from '@/types';

/**
 * Slides composed with blocks of a group (D-399, D-428…D-432; DM-07), under the slides of the
 * document: the agent composes them in the step "Documento", admin and manager change them in the
 * detail of the group while it waits; read only otherwise. At most three per group; every slide has
 * its own editor (CustomSlideEditor), saved shortly after the last change. flush() sends the pending
 * changes before the group is sent or approved.
 */
const props = defineProps<{
    custom: CustomSlides;
    queue: VisitQueue;
    frozen: boolean;
}>();

const { t } = useTrans();

const adding = ref(false);
const editors = ref<Array<InstanceType<typeof CustomSlideEditor>>>([]);

function add(): void {
    if (!props.custom.canAdd || adding.value) {
        return;
    }
    adding.value = true;
    props.queue.run('custom-slides-add', (done) => {
        router.post(props.custom.storeUrl, {}, {
            preserveState: true,
            preserveScroll: true,
            only: ['documentSlides'],
            onFinish: () => {
                adding.value = false;
                done();
            },
        });
    });
}

/** Sends the pending changes of every editor (the parent waits on the queue). */
function flush(): void {
    editors.value.forEach((editor) => editor.flush());
}

defineExpose({ flush });
</script>

<template>
    <section
        class="flex flex-col gap-4 border-t border-line pt-5"
        :aria-label="t('proposals.custom_slides.title')"
    >
        <div class="flex flex-col gap-1.5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="flex items-center gap-2 text-base font-semibold text-ink">
                    <LayoutTemplate
                        :size="18"
                        :stroke-width="2"
                        aria-hidden="true"
                    />
                    {{ t('proposals.custom_slides.title') }}
                    <span class="text-sm font-normal text-muted">· {{ custom.slides.length }}/{{ custom.max }}</span>
                </h3>
                <button
                    v-if="custom.editable"
                    type="button"
                    :class="buttonClasses.secondary"
                    :disabled="!custom.canAdd || adding"
                    @click="add"
                >
                    <Plus
                        :size="18"
                        :stroke-width="2"
                        aria-hidden="true"
                    />
                    {{ t('proposals.custom_slides.add') }}
                </button>
            </div>
            <p class="text-sm text-muted">
                {{ frozen ? t('proposals.custom_slides.frozen') : custom.editable ? t('proposals.custom_slides.intro', { max: String(custom.max) }) : t('proposals.custom_slides.read_only') }}
            </p>
            <p
                v-if="custom.editable && !custom.canAdd"
                class="text-[13px] text-muted"
            >
                {{ t('proposals.custom_slides.limit_reached', { max: String(custom.max) }) }}
            </p>
        </div>

        <p
            v-if="custom.noBackground"
            class="flex items-start gap-2 rounded-[10px] bg-notice-soft px-3.5 py-3 text-sm text-ink"
            role="status"
        >
            <Info
                :size="18"
                :stroke-width="2"
                class="mt-0.5 shrink-0 text-notice"
                aria-hidden="true"
            />
            {{ custom.noBackground }}
        </p>

        <p
            v-if="custom.slides.length === 0"
            class="text-sm text-muted"
        >
            {{ t('proposals.custom_slides.empty') }}
        </p>

        <CustomSlideEditor
            v-for="slide in custom.slides"
            :key="slide.id"
            ref="editors"
            :slide="slide"
            :options="custom"
            :queue="queue"
        />
    </section>
</template>
