import { readonly, ref } from 'vue';
import type { Toast, ToastType } from '@/types';

/** Shared queue of toasts (ux-architettura §4.5). Errors stay until closed; confirmations close by themselves. */
const toasts = ref<Toast[]>([]);
const SUCCESS_TIMEOUT_MS = 5000;

let counter = 0;

function dismiss(id: string): void {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function push(toast: Toast): void {
    if (toasts.value.some((existing) => existing.id === toast.id)) {
        return;
    }
    toasts.value = [...toasts.value, toast];

    if (toast.type === 'success') {
        window.setTimeout(() => dismiss(toast.id), SUCCESS_TIMEOUT_MS);
    }
}

function show(type: ToastType, message: string, code: string | null = null): void {
    counter += 1;
    push({ id: `local-${counter}`, type, message, code });
}

export function useToasts() {
    return { toasts: readonly(toasts), push, show, dismiss };
}
