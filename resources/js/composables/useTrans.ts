import { usePage } from '@inertiajs/vue3';
import type { Translations } from '@/types';

/**
 * Interface texts from lang/it/ui.php, shared by the server as props.ui (CLAUDE.md §9).
 *
 * t('menu.open') → "Apri il menu"; t('season.label', { season: '2027-28' }) replaces :season,
 * like Laravel's __(). A missing key returns the key itself, so it shows up during review.
 */
export function useTrans() {
    const page = usePage();

    function t(key: string, replace: Record<string, string> = {}): string {
        let value: string | Translations | undefined = page.props.ui;

        for (const part of key.split('.')) {
            if (typeof value !== 'object') {
                value = undefined;
                break;
            }
            value = value[part];
        }

        if (typeof value !== 'string') {
            return key;
        }

        return Object.entries(replace).reduce(
            (text, [name, replacement]) => text.replaceAll(`:${name}`, replacement),
            value,
        );
    }

    return { t };
}
