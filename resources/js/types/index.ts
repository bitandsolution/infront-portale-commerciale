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

/** Clients of an agent on the Users page (M01-CA4, D-360). */
export interface AgentClients {
    count: number;
    owners: Option[];
    listUrl: string;
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

/** A value of the catalogue the admin changes (CatalogPresenter::valueRow; D-331). */
export interface CatalogValueRow {
    id: number;
    kind: 'led_format' | 'partnership_tier' | 'hospitality_variant' | 'benefit_category';
    code: string;
    name: string;
    /** Only LED formats have a description. */
    description: string | null;
    /** Benefit categories have no active flag. */
    isActive: boolean | null;
    sortOrder: number;
    /** Fixed facts from the system data: pools consumed, free package. */
    notes: string[];
    url: string;
}

/** A value of the system data shown read-only on the page "Catalogo › Valori" (D-331). */
export interface CatalogSystemValue {
    code: string;
    name: string;
    isActive: boolean;
    /** Only for the pools. */
    visibilityType?: string;
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

/** One row of the Sectors page (ClientsPresenter::sectorRow). */
export interface CommoditySectorRow {
    id: number;
    code: string;
    name: string;
    requiresFreeText: boolean;
    isActive: boolean;
    sortOrder: number;
    url: string;
}

/** One row of the client lists (ClientsPresenter::clientRow); the owner only for the back office. */
export interface ClientRow {
    id: number;
    companyName: string;
    vatNumber: string | null;
    email: string;
    sector: string;
    contactName: string | null;
    owner: string | null;
    /** Back office only (filters of D-360). */
    ownerId: number | null;
    ownerActive: boolean | null;
    isActive: boolean;
    status: ClientStatus;
    /** Back office only: the client a merged client went into (D-359). */
    mergedInto: string | null;
    logoUrl: string;
    url: string;
}

/** Status of a client: active, not active, merged into another client (D-359). */
export type ClientStatus = 'active' | 'inactive' | 'merged';

/** Admin actions on a client (ClientsPresenter::management; D-082, D-359). */
export interface ClientManagement {
    reassignUrl: string | null;
    deactivateUrl: string | null;
    reactivateUrl: string | null;
    mergeUrl: string | null;
    owners: Option[];
    mergeTargets: Option[];
}

/** The fields of the client form (ClientsPresenter::clientForm). */
export interface ClientForm {
    id: number;
    companyName: string;
    vatNumber: string;
    email: string;
    sectorId: number;
    sectorFreeText: string;
    contactName: string;
    owner: string;
    ownerActive: boolean;
    isActive: boolean;
    status: ClientStatus;
    /** Back office only (D-359). */
    mergedInto: { name: string; url: string } | null;
    logoUrl: string;
}

/** A sector of the client form, with the free-text flag (RB-04). */
export interface SectorOption extends Option {
    requiresFreeText: boolean;
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

/** Price list grids (F2-02, F2-03; PriceListPresenter on the server). */
export interface PriceCell {
    value: string;
    display: string;
    /** Indicative seasonal price of a LED cell (D-340), computed by the server; null elsewhere. */
    seasonal?: { display: string; matches: number; fromCalendar: boolean } | null;
}

export interface PriceRow {
    clubId: number;
    name: string;
    competitionIds: number[];
    prices: Record<string, PriceCell | null>;
    benefitsUrl: string | null;
}

export interface PriceColumn {
    id: number;
    code: string;
    name: string;
}

export interface PriceListInfo {
    id: number;
    status: string;
    statusLabel: string;
    isPublished: boolean;
    publishedAt: string | null;
    publishedBy: string | null;
    updateUrl: string;
    publishUrl: string;
}

/** On-top texts of a price list, general or of a club (F2-03). */
export interface OnTopItem {
    id: number;
    title: string;
    body: string;
    updateUrl: string;
    removeUrl: string;
}

export interface OnTopData {
    items: OnTopItem[];
    createUrl: string;
    clubId: number | null;
}

/** A client the agent can choose at step 1 of the configurator (ProposalsPresenter::clientOptions). */
export interface ProposalClientOption extends Option {
    vatNumber: string | null;
    sector: string;
}

/** A season of step 2 with its limits and the defaults of the period, "Y-m-d" (D-375). */
export interface ProposalSeasonOption extends Option {
    startsOn: string;
    endsOn: string;
    range: string;
    defaultStartsOn: string;
    defaultEndsOn: string;
}

/** A draft open in the configurator (ProposalsPresenter::draft). */
export interface ProposalDraft {
    id: number;
    number: number;
    title: string;
    lockVersion: number;
    savedAt: string;
    clientId: string;
    client: {
        name: string;
        vatNumber: string | null;
        sector: string;
        selectable: boolean;
    } | null;
    seasonId: string;
    startsOn: string;
    endsOn: string;
    /** "Dal 01/07/2027 al 30/06/2028", null without a period. */
    periodLabel: string | null;
    listTotal: string;
    clubs: ProposalDraftClub[];
    hasErrors: boolean;
    /** D-373: no club, or a club without elements: the summary (step 5) is not available. */
    blocksSummary: boolean;
    campaign: ProposalCampaign | null;
    warnings: ProposalWarning[];
    saveUrl: string;
    deleteUrl: string;
    newClientUrl: string;
}

/** An option of a club at step 4 (ProposalsPresenter::clubOptions): shown only when available or sold out. */
export interface ProposalClubOptionValue {
    id: number;
    name: string;
    status: 'available' | 'sold_out';
    statusLabel: string;
    /** Residual of the server: seconds per match, contracts or seats; null for "Libera". */
    remaining: number | null;
}

/** A selectable club of the season (step 3) with the options of step 4. */
export interface ProposalClubOption {
    id: number;
    name: string;
    initials: string;
    competition: string | null;
    tiers: Array<ProposalClubOptionValue & { isFree: boolean; price: string | null }>;
    formats: Array<ProposalClubOptionValue & { remainingLabel: string | null }>;
    variants: Array<ProposalClubOptionValue & { price: string | null }>;
}

/** A club of the draft as evaluated by the server (DraftEvaluator), with its errors by field. */
export interface ProposalDraftClub {
    clubId: number;
    name: string;
    initials: string;
    competition: string | null;
    selectable: boolean;
    tierId: number | null;
    ledFormatId: number | null;
    ledSeconds: number;
    seats: Array<{ variantId: number; seats: number }>;
    matches: number | null;
    matchesLabel: string | null;
    spots: number | null;
    spotsPerMatch: number | null;
    tierName: string | null;
    formatName: string | null;
    ledLabel: string | null;
    hospitalityLabel: string | null;
    price: string | null;
    errors: Record<string, string>;
}

/** KPI of the campaign calculated by the server (ProposalsPresenter::campaign; M06 §6, D-093). */
export interface ProposalCampaign {
    clubs: number;
    totalMatches: number;
    ledMatches: number;
    totalSpots: number;
    months: number;
    range: string | null;
    topMatchesApplicable: boolean;
    /** A number, "n.d." when a calendar is missing, null when not applicable. */
    topMatches: string | null;
    fromCalendar: boolean;
}

/** A warning of the summary: an error of a saved choice, or information (standard number, top matches). */
export interface ProposalWarning {
    kind: 'error' | 'info';
    text: string;
}

/** Prices recalculated at the reopening of a draft (ProposalsPresenter::repricing; RB-12). */
export interface ProposalRepricing {
    clubs: Array<{ name: string; text: string }>;
    total: string;
}

/** A club of the draft as the agent edits it (sent with the automatic save). */
export interface ProposalClubFields {
    club_id: number;
    partnership_tier_id: number | null;
    led_format_id: number | null;
    led_seconds: number;
    seats: Array<{ variant_id: number; seats: number }>;
}

/** One draft of "Le mie proposte" (ProposalsPresenter::draftRow). */
export interface ProposalDraftRow {
    id: number;
    number: number;
    client: string | null;
    season: string | null;
    period: string | null;
    listTotal: string;
    updatedAt: string;
    editUrl: string;
    deleteUrl: string;
}
