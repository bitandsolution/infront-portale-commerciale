<?php

namespace App\Support\Demo;

/**
 * Fictitious data of the demo of 4/12/2026 (D-400, D-438…D-442; DM-08): real names of the Serie A
 * clubs (public domain), invented prices, capacities, clients and proposals. Nothing here is a real
 * commercial datum (CLAUDE.md §7).
 */
final class DemoData
{
    public const SEASON_CODE = '2027-28';

    public const SEASON_NAME = 'Stagione 2027-28';

    public const SEASON_START = '2027-07-01';

    public const SEASON_END = '2028-06-30';

    /** First matchday of the fictitious calendar, then one matchday a week (38 Sundays). */
    public const FIRST_MATCHDAY = '2027-08-22';

    public const KICKOFF = '20:45';

    /** Header of the fictitious calendar file, in the layout D-017 (recognized by the import, D-325). */
    public const CALENDAR_HEADERS = ['Stagione', 'Competizione', 'Giornata', 'Data', 'Ora', 'Squadra casa', 'Squadra ospite', 'Stadio'];

    public const SERIE_A = 'SERIE_A';

    public const SERIE_B = 'SERIE_B';

    public const STANDARD_HOME_MATCHES = 19;

    /** Start of the variant B of the group of the dates (D-440 b). */
    public const JANUARY_START = '2028-01-01';

    public const AGENT_EMAIL = 'agente1@example.test';

    public const SECOND_AGENT_EMAIL = 'agente2@example.test';

    /** The five clubs of the demo, clubs of Infront (D-400). */
    public const DEMO_CLUBS = ['LAZIO', 'ROMA', 'BOLOGNA', 'UDINESE', 'COMO'];

    /**
     * The 20 clubs of the fictitious Serie A: code, name, short name, primary and secondary colour,
     * stadium, top match opponent (D-257: Lazio, Inter, Milan, Juventus, Roma, Napoli).
     *
     * @var list<array{string, string, string, string, string, string, bool}>
     */
    public const CLUBS = [
        ['ATALANTA', 'Atalanta BC', 'Atalanta', '#1E71B8', '#000000', 'BERGAMO', false],
        ['BOLOGNA', 'Bologna FC 1909', 'Bologna', '#1A2F48', '#A21C26', 'DALLARA', false],
        ['CAGLIARI', 'Cagliari Calcio', 'Cagliari', '#002350', '#A70E32', 'CAGLIARI', false],
        ['COMO', 'Como 1907', 'Como', '#003DA5', '#FFFFFF', 'SINIGAGLIA', false],
        ['CREMONESE', 'US Cremonese', 'Cremonese', '#A2001D', '#808080', 'ZINI', false],
        ['FIORENTINA', 'ACF Fiorentina', 'Fiorentina', '#5B2B82', '#FFFFFF', 'FRANCHI', false],
        ['GENOA', 'Genoa CFC', 'Genoa', '#A50B30', '#002147', 'FERRARIS', false],
        ['INTER', 'FC Internazionale Milano', 'Inter', '#0068A8', '#000000', 'MEAZZA', true],
        ['JUVENTUS', 'Juventus FC', 'Juventus', '#000000', '#FFFFFF', 'TORINO_J', true],
        ['LAZIO', 'SS Lazio', 'Lazio', '#87D8F7', '#FFFFFF', 'OLIMPICO', true],
        ['LECCE', 'US Lecce', 'Lecce', '#FFDD00', '#C8102E', 'VIADELMARE', false],
        ['MILAN', 'AC Milan', 'Milan', '#FB090B', '#000000', 'MEAZZA', true],
        ['NAPOLI', 'SSC Napoli', 'Napoli', '#12A0D7', '#FFFFFF', 'MARADONA', true],
        ['PARMA', 'Parma Calcio 1913', 'Parma', '#FFD200', '#1B3D8F', 'TARDINI', false],
        ['PISA', 'Pisa SC', 'Pisa', '#1B3D8F', '#000000', 'GARIBALDI', false],
        ['ROMA', 'AS Roma', 'Roma', '#8E1F2F', '#F0BC42', 'OLIMPICO', true],
        ['SASSUOLO', 'US Sassuolo', 'Sassuolo', '#00A752', '#000000', 'REGGIO', false],
        ['TORINO', 'Torino FC', 'Torino', '#8A1E03', '#FFFFFF', 'GRANDE_TORINO', false],
        ['UDINESE', 'Udinese Calcio', 'Udinese', '#000000', '#FFFFFF', 'FRIULI', false],
        ['VERONA', 'Hellas Verona FC', 'Verona', '#002F6C', '#FFD200', 'BENTEGODI', false],
    ];

    /**
     * Stadiums: key, name, city.
     *
     * @var list<array{string, string, string}>
     */
    public const STADIUMS = [
        ['BERGAMO', 'Stadio di Bergamo', 'Bergamo'],
        ['DALLARA', "Stadio Renato Dall'Ara", 'Bologna'],
        ['CAGLIARI', 'Stadio di Cagliari', 'Cagliari'],
        ['SINIGAGLIA', 'Stadio Giuseppe Sinigaglia', 'Como'],
        ['ZINI', 'Stadio Giovanni Zini', 'Cremona'],
        ['FRANCHI', 'Stadio Artemio Franchi', 'Firenze'],
        ['FERRARIS', 'Stadio Luigi Ferraris', 'Genova'],
        ['MEAZZA', 'Stadio Giuseppe Meazza', 'Milano'],
        ['TORINO_J', 'Stadio della Juventus', 'Torino'],
        ['OLIMPICO', 'Stadio Olimpico', 'Roma'],
        ['VIADELMARE', 'Stadio Via del Mare', 'Lecce'],
        ['MARADONA', 'Stadio Diego Armando Maradona', 'Napoli'],
        ['TARDINI', 'Stadio Ennio Tardini', 'Parma'],
        ['GARIBALDI', 'Arena Garibaldi', 'Pisa'],
        ['REGGIO', 'Stadio Città del Tricolore', 'Reggio Emilia'],
        ['GRANDE_TORINO', 'Stadio Olimpico Grande Torino', 'Torino'],
        ['FRIULI', 'Stadio Friuli', 'Udine'],
        ['BENTEGODI', 'Stadio Marcantonio Bentegodi', 'Verona'],
    ];

    /** Price level of each club of the demo, in % of the base prices below. */
    public const PRICE_LEVELS = ['LAZIO' => 100, 'ROMA' => 100, 'BOLOGNA' => 70, 'COMO' => 60, 'UDINESE' => 55];

    /** Cost per 1′ per match of the LED formats, at 100%, in euro. */
    public const LED_PRICES = ['FILA_1' => 3000, 'FILA_2' => 2400, 'JUMBO' => 5400, 'DOMINATION' => 8400];

    /** Season price of the partnership tiers (never the Libera), at 100%, in euro. */
    public const PARTNERSHIP_PRICES = ['TOP' => 250000, 'PREMIUM' => 135000, 'OFFICIAL' => 70000, 'OFFICIAL_2' => 45000];

    /** Price per seat of the hospitality variants, at 100%, in euro. */
    public const SEAT_PRICES = ['PREMIUM' => 3500, 'BASE' => 1800];

    /** Seconds per match of the two rows of the LED (pools). */
    public const POOL_SECONDS = ['FILA_1' => 1800, 'FILA_2' => 1800];

    /** Contracts of the partnership tiers. */
    public const TIER_CONTRACTS = ['TOP' => 1, 'PREMIUM' => 4, 'OFFICIAL' => 8, 'OFFICIAL_2' => 10];

    /** Seats of the hospitality variants. */
    public const SEATS = ['PREMIUM' => 60, 'BASE' => 120];

    /**
     * Fictitious clients: key, company name, sector, owner (agent e-mail), logo (opaque background
     * or transparent, D-425), colours of the logo.
     *
     * @var list<array{string, string, string, string, bool, array{int, int, int}, array{int, int, int}}>
     */
    public const CLIENTS = [
        ['AURORA', 'Birra Aurora S.p.A.', 'BEVANDE_ALCOOLICI', self::AGENT_EMAIL, true, [232, 163, 23], [40, 20, 0]],
        ['VELA', 'Vela Assicurazioni S.p.A.', 'FINANZA_ASSICURAZIONI', self::AGENT_EMAIL, true, [0, 75, 145], [255, 255, 255]],
        ['MONTEVERDE', 'Caffè Monteverde S.r.l.', 'ALIMENTARI', self::AGENT_EMAIL, false, [110, 60, 30], [110, 60, 30]],
        ['TECNOFORMA', 'Tecnoforma S.r.l.', 'INFORMATICA', self::AGENT_EMAIL, false, [0, 140, 120], [0, 140, 120]],
        ['DELTA', 'Autolinee Delta S.p.A.', 'TURISMO_VIAGGI', self::SECOND_AGENT_EMAIL, true, [30, 30, 30], [255, 210, 0]],
    ];

    /**
     * Photos of the LED simulation (D-398, D-423): file of the campi/ folder by club (null = generic
     * Infront) and kind of photo; the generic Infront photos for every club without its own.
     *
     * @var array<string, array<string, string>>
     */
    public const FIELD_PHOTOS = [
        'INFRONT' => ['row_1' => 'INFRONT_FILA1', 'row_2' => 'INFRONT_FILA2', 'both_rows' => 'INFRONT_JUMBO_DOMINATION'],
        'UDINESE' => ['row_1' => 'UDINESE_FILA1', 'row_2' => 'UDINESE_FILA2', 'both_rows' => 'UDINESE_JUMBO_DOMINATION'],
    ];

    /**
     * Rows of the LED measured on the photos of 1768×726 (horizontal LEDs): [top, bottom] of the
     * upper row (2nd row) and of the lower row (1st row). In the "_FILA1" photos the lower row is
     * free, in the "_FILA2" the upper one, in the "_JUMBO_DOMINATION" both.
     *
     * @var array<string, array{upper: array{int, int}, lower: array{int, int}}>
     */
    public const FIELD_AREAS = [
        'INFRONT' => ['upper' => [180, 218], 'lower' => [224, 262]],
        'UDINESE' => ['upper' => [61, 98], 'lower' => [101, 139]],
    ];

    public const FIELD_WIDTH = 1768;
}
