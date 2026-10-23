/** Shapes of the props sent by the server (app/Http/Middleware/HandleInertiaRequests.php and controllers). */

export type ToastType = 'error' | 'success' | 'info';

export interface Toast {
    id: string;
    type: ToastType;
    message: string;
    /** Support code of a system error (D-265); absent when the server did not answer. */
    code?: string | null;
}

/** Nested interface texts from lang/it/ui.php. */
export interface Translations {
    [key: string]: string | Translations;
}

export interface NavChild {
    key: string;
    label: string;
    href: string;
    active: boolean;
}

export interface NavItem {
    key: string;
    label: string;
    icon: string;
    href: string | null;
    active: boolean;
    children: NavChild[];
}

export interface LayoutData {
    title: string;
    navigation: NavItem[];
    user: {
        name: string;
        initials: string;
        role: string;
        logoutUrl: string;
    };
    season: {
        label: string;
        enabled: boolean;
    };
}

export interface SharedProps {
    app: {
        name: string;
        locale: string;
    };
    ui: Translations;
    toast: Toast | null;
    [key: string]: unknown;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedProps;
    }
}
