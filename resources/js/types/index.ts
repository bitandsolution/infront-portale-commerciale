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
        profileUrl: string;
        logoutUrl: string;
    };
    /** Season selector of the top bar (D-317). */
    season: {
        label: string;
        enabled: boolean;
        current: number | null;
        options: Option[];
        selectUrl: string;
    };
}

/** Account status of a user (UserPresenter::status on the server). */
export type UserStatus = 'active' | 'pending' | 'inactive';

/** Colour of a status pill (StatusPill.vue); the status is always written as text too. */
export type StatusTone = 'success' | 'notice' | 'muted';

export interface Option {
    value: string;
    label: string;
}

/** One row of the Users page (UserPresenter::row). */
export interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    roleLabel: string;
    status: UserStatus;
    twoFactor: boolean;
    lastLogin: string | null;
    url: string;
}

export interface UserDetail extends UserRow {
    firstName: string;
    lastName: string;
    phone: string;
    createdAt: string | null;
    activatedAt: string | null;
    deactivatedAt: string | null;
    deactivatedBy: string | null;
}

/** One row of the Seasons page (SportsPresenter::seasonRow). */
export interface SeasonRow {
    id: number;
    code: string;
    name: string;
    startsOn: string;
    endsOn: string;
    status: string;
    statusLabel: string;
    competitions: number;
    url: string;
}

/** Season being edited (SeasonController::edit), dates as YYYY-MM-DD. */
export interface SeasonDetail {
    id: number;
    code: string;
    name: string;
    startsOn: string;
    endsOn: string;
    status: string;
    readOnly: boolean;
}

/** Competition of a season with its standard number of home matches (SportsPresenter::editionRow). */
export interface EditionRow {
    id: number;
    competitionCode: string;
    competitionName: string;
    competitionActive: boolean;
    standardHomeMatches: number;
    calendarLoadedAt: string | null;
    enrollments: number;
    showUrl: string;
    updateUrl: string;
    destroyUrl: string;
}

/** One row of the Competitions page (SportsPresenter::competitionRow). */
export interface CompetitionRow {
    id: number;
    code: string;
    name: string;
    shortName: string;
    type: string;
    typeLabel: string;
    tier: number;
    hasTopMatchKpi: boolean;
    isActive: boolean;
    sortOrder: number;
    seasons: number;
    url: string;
}

/** One row of the Stadiums page (SportsPresenter::stadiumRow). */
export interface StadiumRow {
    id: number;
    name: string;
    city: string;
    capacity: number | null;
    isActive: boolean;
    clubs: number;
    url: string;
}

/** One row of the Clubs page (SportsPresenter::clubRow). */
export interface ClubRow {
    id: number;
    code: string;
    name: string;
    shortName: string;
    homeStadiumId: number | null;
    homeStadium: string | null;
    primaryColor: string | null;
    secondaryColor: string | null;
    isInfront: boolean;
    isTopMatchOpponent: boolean;
    isActive: boolean;
    logoUrl: string | null;
    url: string;
}

/** Alias of a club (SportsPresenter::aliasRow). */
export interface ClubAliasRow {
    id: number;
    alias: string;
    source: string;
    sourceLabel: string;
    destroyUrl: string;
}

/** One match of the Matches page (SportsPresenter::matchRow). */
export interface MatchRow {
    id: number;
    stage: string;
    stageLabel: string;
    matchday: number;
    date: string;
    kickoff: string | null;
    homeClub: string;
    awayClub: string;
    stadium: string | null;
    status: string;
    statusLabel: string;
    source: string;
    editUrl: string;
    cancelUrl: string;
    restoreUrl: string;
}

/** A calendar import in the list and in its page (SportsPresenter::importRow). */
export interface ImportRow {
    id: number;
    edition: string;
    competition: string;
    fileName: string;
    fileUrl: string;
    uploadedBy: string;
    uploadedAt: string | null;
    status: 'preview' | 'confirmed' | 'discarded';
    statusLabel: string;
    statusTone: StatusTone;
    inserted: number;
    updated: number;
    unchanged: number;
    discarded: number;
    confirmedAt: string | null;
    showUrl: string;
}

export type ImportOutcome = 'new' | 'update' | 'unchanged' | 'error';

/** A row of the preview of an import (SportsPresenter::importPreviewRow). */
export interface ImportPreviewRow {
    line: number;
    matchday: number | null;
    date: string | null;
    time: string | null;
    home: string;
    away: string;
    stadium: string;
    outcome: ImportOutcome;
    unknown: boolean;
    errors: string[];
    warnings: string[];
    changes: string[];
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
