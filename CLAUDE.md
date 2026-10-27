# CLAUDE.md — Portale Commerciale Infront

Leggi questo file **per intero** all'inizio di ogni sessione. Contiene le regole che valgono per tutto il codice del repository.

Stato: approvato con il blocco 6.3 (D-254, 2026-10-21). Responsabile: Antonio Tartaglia (bitAND solution).

---

## 1. Il progetto in breve

Portale web in cui gli agenti commerciali di **Infront Italy** configurano offerte di sponsorizzazione sportiva (LED bordocampo, partnership, hospitality) su uno o più club di calcio. L'admin o il gestore le approvano e il sistema genera il **documento commerciale in PDF**. Il portale sostituisce un sistema PHP/FPDF il cui server è da considerare **compromesso**.

- Avvio con la stagione **2027-28**, go-live il **24/5/2027** (D-243). Nessuna migrazione dello storico (D-227).
- Ruoli fissi: **admin** (1), **gestore** (`manager`, 2), **agente** (`agent`, fino a 40).
- Il codice lo scrive Claude. Antonio rivede ogni PR, collauda e rilascia (D-168, D-170).

## 2. Fonti di verità

| Cosa | Dove | Regola |
|---|---|---|
| Requisiti funzionali | `docs/specs/spec-M01…M10-*.md` | Approvati: sono il requisito. Ogni criterio di accettazione numerato (CA) diventa almeno un test |
| Decisioni | `docs/registro-decisioni.md` | Valgono solo quelle **APPROVATE**. Se una specifica e una decisione successiva si contraddicono, **vince la decisione più recente** |
| Progettazione tecnica | `docs/progettazione-tecnica.md` | Modello dati (§5.3), sicurezza e API (§5.4), documenti e code (§5.5) |
| UX | `docs/ux-architettura.md` + mockup (link in `docs/README.md`) | Navigazione per ruolo, stati, errori, breakpoint |
| Layout dei documenti | `docs/layout-dinamici.md` | Misure, font e regole dei 9 layout dinamici |
| Piano | `docs/pianificazione.md` | Fette, ordine, milestone, PR della fetta corrente |
| Fuori perimetro | `docs/features-evolutive.md` | EV-01…EV-24: **non si sviluppano** |

**Se manca un'informazione o due fonti non si conciliano, non decidere da solo.** Fermati e scrivi la domanda nella descrizione della PR o nella risposta ad Antonio, con le opzioni e una raccomandazione. Non inventare requisiti, campi, stati o regole di calcolo.

## 3. Stack (D-174) — non cambiarlo

- **Laravel 13**, **PHP 8.4**, **MariaDB 11.8**, Redis + **Horizon**, **Inertia** + **Vue 3** in **TypeScript** (`<script setup lang="ts">`), Vite.
- Autenticazione **Fortify** con 2FA TOTP; token API **Sanctum** (rilascio 1.1).
- PDF: **Browsershot** (Chromium headless, senza rete) + **qpdf**; Ghostscript solo oltre 20 MB.
- Qualità: **Pest**, **Larastan** al livello massimo raggiungibile (obiettivo 8), **Pint**, ESLint + `vue-tsc`.
- Sviluppo locale con **Laravel Sail**. Produzione nativa: nginx + PHP-FPM, gestita da Antonio, fuori perimetro.
- Nessun pacchetto nuovo senza motivarlo nella PR (licenza, manutenzione, alternativa nativa di Laravel). Prima di aggiungerne uno, chiediti se il framework copre già il caso.

## 4. Architettura

Monolite modulare (D-172): un'unica applicazione Laravel e un worker per le code `documents`, `mail` e `default` (D-220).

```
app/
  Models/                  modelli Eloquent (tutti qui, nomi inglesi)
  Enums/                   enum PHP dei valori di sistema (C8)
  Domain/<Area>/           logica di dominio: Actions/, Services/, Data/ (DTO), Exceptions/
  Http/Controllers/<Area>/ controller sottili: validano, autorizzano, chiamano un'Action
  Http/Requests/<Area>/    FormRequest con le regole di validazione
  Policies/                una Policy per entità (D-215)
  Jobs/                    lavori in coda, idempotenti
  Support/                 formattazione (numeri, minuti, importi), helper trasversali
resources/js/pages/<Area>/ pagine Inertia (convenzione Inertia 3); resources/js/components/ componenti condivisi
resources/js/layouts/       layout applicativo (AppLayout); resources/js/composables/ useTrans (testi da lang/it), useToasts
resources/views/documents/ template Blade dei layout dei documenti
lang/it/                   tutti i testi dell'interfaccia
docs/                      specifiche e decisioni (vedi §2)
```

Aree (`<Area>`): `Users` (M01), `Sports` (M02), `Catalog` (M03), `Inventory` (M04), `Clients` (M05), `Proposals` (M06, M07), `Content` (M08), `Documents` (M09), `Audit` (M10), `Reports` (M10), più le aree trasversali `Files` (archiviazione dei file, C10), `Settings` (impostazioni di sistema, PR-07), `Notifications` (invio e registro delle e-mail, PR-08) e `Operations` (salute, avvisi delle code, Sentry: PR-09).

Regole:
- **La logica di dominio sta nelle Action e nei Service**, mai nei controller, nei modelli o nei componenti Vue. Calcoli di prezzo, partite, residui e composizione dei documenti hanno **una sola implementazione**, lato server, usata anche dal simulatore e dai riepiloghi.
- Il frontend non calcola prezzi né residui: li riceve dal server.
- Ogni scrittura su dati di backoffice o di proposta passa da un'Action che registra l'evento di **audit** (D-127). Nessuna scrittura diretta da controller.

## 5. Convenzioni del modello dati (C1–C12, D-184)

1. Tabelle **InnoDB**, `utf8mb4`, collation `utf8mb4_uca1400_ai_ci`.
2. Tabelle e colonne in **inglese**, `snake_case`, tabelle al plurale. Interfaccia e documenti in italiano. Usa il **glossario** del §8.
3. Chiave `id` BIGINT UNSIGNED; FK `<entità>_id` con **ON DELETE RESTRICT** (CASCADE solo per figli puri: alias, righe di bozza, condizioni delle slide). Le entità esposte via API hanno un **`code`** stabile (`LAZIO`, `SERIE_A`, `2027-28`).
4. Nessun `deleted_at`: le anagrafiche hanno `is_active`; la cancellazione fisica è ammessa solo per le bozze, con evento di audit.
5. Importi `DECIMAL(12,2)` in euro IVA esclusa, percentuali `DECIMAL(5,2)`. **Mai FLOAT.** In PHP gli importi si calcolano con aritmetica decimale esatta (`brick/math` o equivalente), mai con `float`.
6. Durate LED in **secondi interi** (`SMALLINT UNSIGNED`). Il passo di 30″ sta in `settings` (D-063).
7. Timestamp di sistema in **UTC**, mostrati in Europe/Rome. Date e ore sportive come `DATE` + `TIME` locali, senza conversione.
8. Stati e tipi di sistema come **enum PHP** salvati in `VARCHAR(32)`, non `ENUM` MySQL. I valori gestiti da backoffice stanno in tabelle.
9. Vincoli nel database dove possibile (`NOT NULL`, `UNIQUE`, `CHECK`). Le regole su più righe stanno nel codice, con test.
10. Tutti i file in **`stored_files`** (disco privato, SHA-256, dimensioni, MIME); le entità puntano a `…_file_id`. I file non si sovrascrivono mai.
11. Dati congelati: colonne relazionali per ciò che serve a magazzino e report, JSON per il resto in sola lettura.
12. `created_at`/`updated_at` ovunque; `created_by` solo dove serve alla logica. Lo storico delle modifiche sta nell'audit.

Le migrazioni devono essere **compatibili all'indietro di una versione** (D-178): niente rinomina o cancellazione di colonne nello stesso rilascio che smette di usarle.

## 6. Invarianti di dominio (ognuna ha test dedicati)

- Minuti LED solo a **passi di 30″** (D-063); "0,30′" significa 30 secondi.
- Prezzo LED = costo per 1′ a partita × secondi / 60 × partite in casa di campionato nel periodo (D-054). Arrotondamento a 2 decimali **solo sul prezzo del club** (D-190).
- Senza calendario: partite = standard × giorni del contratto / giorni della stagione, arrotondato all'intero più vicino con 0,5 per eccesso (D-187).
- Contano solo le partite di **campionato in casa**, fase regolare, non annullate (D-033).
- La **partnership non consuma LED**; scala solo il contatore del suo livello; la **Libera** non scala nulla (D-051, D-052).
- **Jumbo e Domination** consumano gli stessi secondi sia sulla 1ª sia sulla 2ª fila (D-051).
- **Hospitality**: sempre selezionabile, anche senza partnership; **massimo 6 posti per club** (D-055, D-147).
- **Sconto solo sul totale**, in % o in importo; le righe club restano a listino; il totale finale non è mai negativo (D-103).
- L'agente vede **solo prezzi di listino**, nessun campo sconto, e solo i **propri** clienti, proposte e documenti (D-011, D-076).
- La disponibilità si impegna **solo all'approvazione**, in un'unica transazione con lock (D-216). Le versioni congelate sono **immutabili** (D-198).
- **Forzatura** oltre la disponibilità: solo admin, con motivazione (D-070).
- Documento solo per proposte **approvate**; 1 club → documento club, 2 o più → network (D-090, D-121).
- KPI salvati in **snapshot** nel documento (D-111).
- Top match: conta la partita se l'**ospite** ha il flag (RB-06, D-253); con condizione sulla competizione, i dati delle slide network considerano solo i club di quella competizione (D-251).

## 7. Sicurezza (D-176, D-214…D-218)

- **Mai dati reali** in sviluppo, nei test o nei seeder: solo dati fittizi. Il dump del vecchio sistema non entra nel repository.
- **Mai segreti** nel repository: solo `.env.example` con valori finti.
- Autorizzazione sempre **lato server**: Policy per entità, Gate per aree, e per l'agente anche lo **scope per proprietario** sulle query. Ogni nuova rotta entra nel **test di matrice** rotta × ruolo (D-215).
- Upload: tipo verificato dal contenuto; immagini **sempre ricodificate**; Excel senza macro. File solo su disco privato; download solo da rotte con Policy (D-218).
- HTML dei documenti: **escape di ogni dato**; nessuna risorsa esterna; Chromium senza rete.
- Font dei documenti: Interstate **Regular, Bold e Black** (D-249), con licenza di Infront; nessun Calibri. **I file non vanno nel repository**: si leggono da `DOCUMENT_FONTS_PATH`, con `@font-face` e peso esplicito. Nei test si usa un font libero sostitutivo.
- Niente `dd()`, `dump()` o log con dati personali. In Sentry nessun dato personale (D-179).

## 8. Glossario IT → EN (usalo per nomi di tabelle, classi e variabili)

| Italiano | Inglese nel codice |
|---|---|
| Disciplina | `Discipline` · `disciplines` |
| Stagione | `Season` · `seasons` |
| Competizione (campionato, girone) | `Competition` · `competitions` |
| Edizione (competizione × stagione) | `CompetitionSeason` · `competition_seasons` |
| Numero standard di partite in casa | `standard_home_matches` |
| Disciplina predefinita (1.0: solo calcio) | `Discipline::default()`, `Discipline::FOOTBALL` (`CALCIO`); nessuna pagina, dal seeder (D-321) |
| Stato della stagione | `SeasonStatus`: `planned` (In preparazione), `open` (Aperta), `closed` (Chiusa, anagrafiche in sola lettura, D-317) |
| Tipo di competizione | `CompetitionType`: `league` (Campionato), `cup` (Coppa) |
| Stagioni non sovrapposte (M02 §8) | `SeasonOverlap` (`App\Domain\Sports\Services`) |
| Stagione della barra superiore (in sessione) | `CurrentSeason` (`App\Domain\Sports\Services`, chiave di sessione `sports.season_id`, D-317), Action `SelectSeason`, rotta `season.select` |
| Regola delle anagrafiche che rifiuta l'operazione | `SportsActionNotAllowed` (toast) |
| Campi delle anagrafiche negli eventi di audit | `SportsAuditValues`; area di audit `sports` (D-321) |
| Dati di sistema delle anagrafiche (calcio, Serie A, B, gironi C) | `SportsSystemDataSeeder`, chiamato da `SystemDataSeeder` |
| Iscrizione stagionale | `ClubEnrollment` · `club_enrollments` |
| Club / società sportiva | `Club` · `clubs` |
| Club Infront / avversario | `is_infront` |
| Avversario da top match | `is_top_match_opponent` |
| Alias del club | `ClubAlias` · `club_aliases` |
| Origine dell'alias | `AliasSource`: `manual` (a mano), `import` (risolto in un import calendario), `legacy` (dal vecchio sistema, F1-06) |
| Nome normalizzato (maiuscole, senza accenti né punteggiatura) | `NameNormalizer` (`App\Support\Sports`): riconoscimento di club e stadi nei calendari (D-319) |
| Aggiunta di un alias (un alias non indica mai due club né ripete il nome di un altro club) | `AddClubAlias`, `RemoveClubAlias` |
| Nuovo logo del club (file mai sovrascritto) | `ChangeClubLogo`, evento `club.logo_changed` |
| Stadio | `Stadium` · `stadiums` |
| Giornata | `Matchday` · `matchdays` (`stage`: enum `MatchdayStage`, regular / playoff / playout) |
| Partita | `FixtureMatch`, perché `Match` è parola riservata di PHP · `matches` (`status`: `MatchStatus` scheduled / cancelled; `source`: `MatchSource` manual / import) |
| Iscrizione dei club / tolta di un club (un solo campionato per club e stagione) | `EnrollClubs`, `RemoveEnrollment` (`App\Domain\Sports\Actions`) |
| Inserimento e modifica di una partita (stadio predefinito = abituale della squadra di casa; avvisi non bloccanti) | `SaveMatch` → `SavedMatch`; annullamento e ripristino `ChangeMatchStatus` (D-319) |
| Import calendario | `CalendarImport` · `calendar_imports` (`status`: `CalendarImportStatus` preview / confirmed / discarded; anteprima `CalendarPreview` in JSON); ultima importazione della partita `matches.last_import_id` |
| Colonne del tracciato D-017 (riconosciute dall'intestazione o scelte a mano, D-325) | `CalendarColumn` |
| Lettura del file del calendario / controllo delle righe e anteprima | `CalendarFileReader` (OpenSpout, D-316) → `CalendarSheet` / `CalendarAnalyzer` (`App\Domain\Sports\Services`) |
| Passi dell'import (caricamento, ricalcolo, colonne, associazione dei nomi, conferma, scarto) | `UploadCalendar`, `RefreshCalendarPreview`, `MapCalendarColumns`, `ResolveCalendarName`, `ConfirmCalendarImport` (evento `calendar_import.confirmed`), `DiscardCalendarImport`; anteprime ferme da 7 giorni: comando `calendar-imports:prune` |
| Servizio partite (F-12): partite in casa di campionato di club e periodo, campagna, top match | `MatchCounter` (`App\Domain\Sports\Services`) → `MatchCount` (stato dei top match: available / not_available / not_applicable) con un `ClubMatchCount` per club; verifica da server `sports:matches` (D-320, D-328) |
| Club dal vecchio sistema (CSV di `idoc__teams_list`, una tantum) | `LegacyClubFile` → `LegacyClubRow`, `ImportLegacyClubs` → `LegacyClubReport`; comando `sports:import-legacy-clubs` (`docs/dev/club-vecchio-sistema.md`, D-318, D-329) |
| Riferimento esterno | `ExternalReference` · `external_references` (`platform`: `idoc` = `ExternalReference::PLATFORM_IDOC`, poi `accrediti`; `entity_type`: `club` = `ExternalReference::ENTITY_CLUB`) |
| Territorio | `Territory` · `territories` |
| Tipo visibilità (Classica, Virtual) | `VisibilityType` · `visibility_types` |
| Bacino (1ª fila, 2ª fila…) | `InventoryPool` · `inventory_pools` |
| Format LED (Jumbo, Domination…) | `LedFormat` · `led_formats` (+ `led_format_pools`) |
| Livello di partnership / pacchetto | `PartnershipTier` · `partnership_tiers` |
| Pacchetto Libera | `is_free_package` |
| Variante hospitality (Premium, Base) | `HospitalityVariant` · `hospitality_variants` |
| Categoria di benefit | `BenefitCategory` · `benefit_categories` |
| Listino | `PriceList` · `price_lists` (`status`: draft / published) |
| Costo per 1′ a partita (costo/cad) | `unit_price` su `led_prices` |
| Prezzo stagionale partnership | `season_price` su `partnership_prices` |
| Prezzo a posto hospitality | `seat_price` su `hospitality_prices` |
| Benefit | `PartnershipBenefit` · `partnership_benefits` |
| Asset on top | `OnTopAsset` · `on_top_assets` |
| Capacità (magazzino) | `PoolCapacity`, `PartnershipCapacity`, `HospitalityCapacity` |
| Bacino gestito (flag) | `is_enabled` |
| Impegno di magazzino | `InventoryCommitment` · `inventory_commitments` |
| Residuo | `remaining` (calcolato, mai salvato) |
| Forzatura | `is_forced`, `forced_reason` |
| Impostazioni | `Setting` · `settings` |
| Chiave di un'impostazione (tipo, predefinito, limiti, modificabilità) | `SettingKey` (`availability_method`, `led_step_seconds`, `hospitality_max_seats`, `forced_reason_min_length`, `audit_ip_retention_days`) |
| Lettura delle impostazioni (predefinito se la riga manca) / modifica dall'admin | `Settings` (`App\Domain\Settings\Services`) / `UpdateSettings` (`App\Domain\Settings\Actions`) |
| Metodo di calcolo della disponibilità | `AvailabilityMethod`: `seasonal` (Stagionale), `per_match` (Per partita, futuro) |
| Dati di sistema (impostazioni, poi cataloghi) | `SystemDataSeeder`, idempotente, in ogni ambiente e a ogni deploy |
| Settore merceologico | `CommoditySector` · `commodity_sectors` |
| Cliente | `Client` · `clients` |
| Ragione sociale | `company_name` (+ `company_name_normalized`) |
| P.IVA | `vat_number` |
| Referente | `contact_name` |
| Agente proprietario del cliente | `owner_id` |
| Proposta | `Proposal` · `proposals` |
| Versione (di lavoro / congelata) | `ProposalVersion` · `proposal_versions` (`number` nullo = di lavoro) |
| Riga club della versione | `ProposalVersionClub` · `proposal_version_clubs` |
| Posti hospitality della riga | `ProposalVersionClubSeat` · `proposal_version_club_seats` |
| Cronologia della proposta | `ProposalEvent` · `proposal_events` |
| Stati della proposta | `ProposalStatus`: `draft` (Bozza), `submitted` (In approvazione), `approved` (Approvata), `rejected` (Rifiutata), `cancelled` (Annullata) |
| Sconto | `discount_type` (percent / amount), `discount_value`, `discount_amount` |
| Totale di listino / finale | `list_total` / `final_total` |
| Blocco di lavorazione | `review_locked_by`, `review_locked_at` |
| Sezione delle slide | `SlideSection` · `slide_sections` |
| Slide (statica / dinamica) | `Slide` · `slides` (`kind`: static / dynamic) |
| Condizione di inclusione | `SlideCondition` · `slide_conditions` |
| Layout dinamico | `layout_code` (enum `DocumentLayout`) |
| Testi fissi dei layout | `LayoutText` · `layout_texts` |
| Definizione KPI / valore KPI | `KpiDefinition` · `kpi_definitions` / `ClubKpiValue` · `club_kpi_values` |
| Documento (club / network) | `Document` · `documents` (`document_type`: club / network) |
| Modello di pagine | `page_model` |
| Evento di audit | `AuditEvent` · `audit_events` |
| Registro e-mail | `NotificationLog` · `notification_logs` (`status`: queued / sent / failed; mai contenuto, link o token) |
| Tipo di e-mail | `NotificationType` (`activation`, `two_factor_reset`, `password_reset`, `password_forgotten`, `login_locked`, `login_locked_admin`, `job_failed`, `queue_stalled`; F5 aggiunge le proposte) |
| Invio delle e-mail e registro | `Notifications` (`App\Domain\Notifications\Services`): `send($user, $mail)`; le e-mail sono `PortalMail` (coda `mail`, cifrate, dopo il commit), testi in `lang/it/mail.php` |
| Conservazione del registro e-mail (12 mesi) / consultazione da server | comandi `notifications:prune` (settimanale) / `notifications:list` |
| Code di lavoro | `documents` (2 processi), `mail` (2), `default` (1) con Horizon (`config/horizon.php`); pannello `/horizon` solo admin (`Ability::QueuesMonitor`) |
| Password dimenticata (reset self-service) | `RequestPasswordReset` (`App\Domain\Users\Actions`), rotte `password.request` / `password.email`, link `AccessLinkType::PasswordForgotten` |
| E-mail di blocco all'utente e agli admin | listener `SendLockoutNotifications` su `LoginLockedOut` |
| Piattaforma che legge l'API | `ApiClient` · `api_clients` |
| Ruoli | `Role`: `admin`, `manager` (Gestore), `agent` (Agente) |
| Permesso d'area (riga della matrice M01 §4) | `Ability` (enum; ogni caso è un Gate) |
| Utente disattivato | `is_active` = false, `deactivated_at`, `deactivated_by` |
| Visibile all'utente (scope per proprietario) | `visibleTo($user)` (trait `HasOwner`) |
| Verifica in due passaggi (2FA) | `two_factor_*` su `users` (Fortify); configurata = `two_factor_confirmed_at` valorizzato |
| Codici di recupero | `recoveryCodes()` (8, monouso) |
| Blocco dopo i tentativi falliti | `LoginRateLimiter` (`App\Domain\Users\Auth`) |
| Scadenza per inattività | `Role::idleTimeoutMinutes()`, middleware `EnforceIdleTimeout` |
| Link di accesso (attivazione, reset) | `AccessLinks` (token in `password_reset_tokens`, 48 h, monouso), `AccessLinkType`, `SendAccessLink` |
| In attesa di attivazione | `activated_at` nullo |
| Almeno un admin attivo | `ActiveAdminGuard` (`App\Domain\Users\Services`) |
| Regola di gestione utenti che rifiuta l'operazione | `UserActionNotAllowed` |
| Scrittura degli eventi di audit | `AuditLog` (`App\Domain\Audit\Services`): `record()` per le Action, `access()` per gli accessi (con IP) |
| Azione / area dell'evento di audit | `AuditAction` (nomi stabili, es. `user.created`, `access.login_failed`) / `AuditArea` |
| Oggetto dell'evento di audit | `AuditSubject` (`object_type` breve e stabile, es. `user`; `object_label` leggibile) |
| Autore "sistema" (comandi da server) | `user_id` nullo, `user_name` e `user_role` = `AuditEvent::SYSTEM` |
| Motivo di un login fallito o di una sessione chiusa | `AccessReason` (es. `unknown_email`, `locked`, `idle`) |
| Blocco appena scattato (5 tentativi) | evento `LoginLockedOut` (`App\Domain\Users\Auth`) |
| Conservazione dell'audit (5 anni, IP) | `AuditRetention`, comando `audit:prune` (notturno) |
| Utenti MariaDB: migrazioni / applicativo / manutenzione | connessioni `migrator` / predefinita / `audit_maintenance` (`docs/dev/database-utenti.md`) |
| File salvato (logo, slide, Excel, PDF) | `StoredFile` · `stored_files`; le entità puntano a `…_file_id` (C10) |
| Tipo d'uso del file (con le sue regole) | `FilePurpose`: `client_logo`, `club_logo`, `slide_image`, `calendar_import`, `generated_pdf` |
| Tipo di file riconosciuto dal contenuto | `FileType`: `png`, `jpeg`, `xlsx`, `pdf` |
| Archiviazione dei file / verifica del contenuto / ricodifica delle immagini | `FileStorage` / `FileInspector` / `ImageReencoder` (`App\Domain\Files\Services`) |
| File rifiutato (messaggio in italiano) | `InvalidFile`; regola di validazione dei campi `AcceptableFile` |
| Disco privato dei file | disco `files` (`FILES_ROOT`, predefinito `storage/app/private/files`); download solo dalla rotta `files.download` |
| Pulizia dei file orfani | comando `files:prune-orphans` (notturno) |
| Salute interna (database, Redis, Horizon, code, disco, scheduler) | `HealthCheck` (`App\Domain\Operations\Services`), rotta pubblica `/health` (200 `ok` / 503 `fail`, senza dettagli), comando `health:check` con i dettagli; soglie in `config/health.php` |
| Segnale di vita dello scheduler | `SchedulerHeartbeat`, scritto ogni minuto (`routes/console.php`) |
| Avvisi delle code agli admin (lavoro non riuscito, coda ferma) | `OperationsAlerts`, listener `SendJobFailedAlert` e `SendQueueStalledAlert`; e-mail `JobFailedMail` e `QueueStalledMail` inviate subito con `Notifications::sendNow()` |
| Dati inviati a Sentry senza informazioni personali | `SentryScrubber` (`before_send` di `config/sentry.php`) |
| Intestazioni di sicurezza e CSP con nonce | middleware `AddSecurityHeaders`; il nonce arriva a Vite, alle pagine di errore e a Inertia (meta `csp-nonce`) |
| Font dei documenti (Interstate o sostitutivo DejaVu) / pesi | `DocumentFonts` (`App\Domain\Documents\Services`, famiglia CSS `DocumentFont`, `@font-face` con sorgente `file://`) / `DocumentFontWeight` (400, 700, 900) |
| Chromium headless configurato per la macchina (Node, sandbox, timeout) | `Chromium` (`App\Domain\Documents\Services`), che restituisce un `Browsershot` per una pagina già scritta su file (mai HTML in stringa, D-312); sandbox spegnibile solo in `local` e `testing` |
| Strumento della pipeline mancante o non configurato (font, sandbox) | `DocumentToolUnavailable` (messaggi in `lang/it/documents.php`) |
| Pagina di un documento: slide statica / pagina di layout (sfondo + dati) | `DocumentPage`: `StaticSlidePage` / `LayoutPage` (`App\Domain\Documents\Data`) |
| Pipeline del PDF (cartella del lavoro, JPEG q85, HTML, Chromium, qpdf, controlli) / esito | `PdfPipeline` (`render()`, `screenshot()`) / `PdfReport` (pagine, peso, tempi, font, avvisi) |
| Strumenti sul PDF (ottimizzazione e metadati con qpdf, pagine, font) | `PdfTools`; metadati `PdfMetadata` (titolo, autore "Infront Italy", stagione) |
| Conversione delle slide in JPEG q85 | `SlideImages` (D-310) |
| Riduzione del corpo fino al 70% e taglio con "…" | `TextFitter` → `FittedText` (layout-dinamici §6.3) |
| Misure e impaginazione di un layout | `<Layout>Layout` in `App\Domain\Documents\Layouts` (S1: `NetworkRecapLayout`, 10 righe per slide) |
| Recap economico: righe, totali | `NetworkRecap`, `RecapRow` (club o riga hospitality), `RecapTotals` |
| Numeri dei documenti (importi, minuti LED) | `DocumentNumbers` (`App\Support\Documents`) |
| PDF che non supera i controlli (pagine, linearizzazione, font) | `DocumentCheckFailed` |
| Materiale fittizio della prova PDF (sfondi, slide, loghi, recap) | `SampleDeck` (`App\Support\Documents`, D-309) |

Termini nuovi: aggiungili qui nella stessa PR che li introduce.

## 9. Lingue

- Codice, nomi, commenti e messaggi di commit in **inglese**.
- Interfaccia, e-mail, messaggi di errore e documenti in **italiano**, sempre da `lang/it/*.php` o dai file di traduzione del frontend. Nessun testo dell'interfaccia scritto nel codice.
- Descrizioni delle PR in **italiano**, perché le legge Antonio.

## 10. Flusso di lavoro

1. Si lavora **una fetta alla volta** (`docs/pianificazione.md`), una PR per funzione. Non anticipare funzioni di fette successive.
2. Branch: `feat/<fetta>-<breve-descrizione>` (es. `feat/f0-two-factor`), `fix/…`, `docs/…`. **Mai push su `main`.**
3. Ogni PR contiene: obiettivo; criteri di accettazione coperti (es. `M01-CA2`) con i test che li verificano; decisioni applicate (`D-xxx`); domande aperte; istruzioni di collaudo manuale; **elenco dei file creati e modificati con percorso**.
4. La CI deve essere verde prima della revisione.
5. Se durante lo sviluppo emerge un'idea fuori perimetro, aggiungila in `docs/features-evolutive.md` (ultima: EV-24) e **non svilupparla**.
6. Se una decisione approvata va cambiata, non cambiarla nel codice: proponila ad Antonio. Il registro si aggiorna solo con la sua conferma.

## 11. Test

- **Pest**. Un file di test per Action o regola. Nome del test con il riferimento al criterio, es. `it('M04-CA2: Domination da 1′ scala 1′ da 1ª e 2ª fila')`.
- Obbligatori:
  - **test di matrice** rotta × ruolo (200/403/redirect);
  - test delle **invarianti del §6**, con i valori numerici delle specifiche (es. 155.680 € − 15% = 132.328 €);
  - test di **concorrenza** sull'approvazione (due approvazioni sulle stesse risorse);
  - test dei **trigger** (audit e versioni congelate non modificabili);
  - per i layout, **regressione visiva** su immagini di riferimento (`docs/layout-dinamici.md` §6).
- Database di test: MariaDB 11.8, come in produzione. Niente SQLite, perché trigger, CHECK e lock devono essere verificati davvero.
- Factory e seeder con dati **fittizi** e realistici (club, prezzi e calendari inventati o di pubblico dominio).

## 12. Comandi

Da creare con la PR `PR-00`; aggiorna questa sezione se cambiano.

```bash
./vendor/bin/sail up -d                 # ambiente locale (app con qpdf e poppler-utils, mariadb, redis, mailpit; D-308)
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan users:create-admin   # primo admin (interattivo)
./vendor/bin/sail artisan db:seed --class=SystemDataSeeder  # dati di sistema mancanti (PR-07; anche con migrate --seed)
./vendor/bin/sail artisan test          # Pest
./vendor/bin/sail artisan test --group=pdf  # test della pipeline PDF: Chromium, qpdf, pdffonts (S1-01; in CI job `pdf`)
./vendor/bin/sail npx puppeteer browsers install chrome-headless-shell  # Chrome for Testing in .cache/puppeteer (D-308; npm ci non lo scarica: .npmrc ignore-scripts)
./vendor/bin/sail composer analyse      # Larastan
./vendor/bin/sail composer format       # Pint
./vendor/bin/sail npm run dev           # Vite
./vendor/bin/sail npm run typecheck     # vue-tsc
./vendor/bin/sail npm run lint          # ESLint
./vendor/bin/sail artisan horizon       # worker delle code documents, mail, default (pannello /horizon, solo admin; PR-08)
./vendor/bin/sail artisan notifications:list   # ultime e-mail del registro (sola lettura, PR-08)
./vendor/bin/sail artisan notifications:prune  # conservazione del registro e-mail, 12 mesi (settimanale in produzione)
./vendor/bin/sail artisan audit:list    # ultimi eventi di audit (sola lettura, PR-05)
./vendor/bin/sail artisan audit:prune   # conservazione dell'audit (notturna in produzione)
./vendor/bin/sail artisan files:prune-orphans  # file del disco privato senza riga (notturno in produzione, PR-06)
./vendor/bin/sail artisan calendar-imports:prune  # import del calendario in anteprima da oltre 7 giorni: scartati (notturno in produzione, F1-04)
./vendor/bin/sail artisan sports:matches 2027-28 LAZIO --from=01/10/2027  # partite in casa, campagna e top match di club e periodo (sola lettura, F1-05)
./vendor/bin/sail artisan sports:import-legacy-clubs storage/app/private/club-idoc.csv --merge=21,35 --dry-run  # club dal vecchio sistema, una tantum (F1-06; senza --dry-run salva)
./vendor/bin/sail artisan files:try     # prova dei controlli sui file (solo sviluppo, PR-06)
./vendor/bin/sail artisan documents:try # prova della pipeline PDF: deck di 40 slide, tempi, peso, PDF scaricabile da admin (solo sviluppo, S1-02; --samples con DOCUMENT_SAMPLES_PATH)
./vendor/bin/sail artisan health:check  # salute interna con i dettagli di ogni controllo (PR-09; /health risponde solo ok/fail)
./vendor/bin/sail artisan schedule:run  # lavori pianificati dovuti e segnale dello scheduler (sulla VM non gira da solo)
```

## 13. Definizione di "fatto"

Una funzione è fatta quando:
1. tutti i criteri di accettazione coinvolti hanno un test che passa;
2. CI verde: test, Larastan, Pint, typecheck, lint, build, audit delle dipendenze;
3. permessi coperti dal test di matrice per ogni nuova rotta;
4. scritture tracciate nell'audit;
5. testi dell'interfaccia in `lang/it`, con gli stati di `docs/ux-architettura.md` §4.5 (vuoto, caricamento, errore) e la resa su tablet a 1024 px;
6. migrazioni reversibili e compatibili all'indietro;
7. glossario e `docs/` aggiornati se cambiano;
8. PR descritta secondo il §10, con istruzioni di collaudo per Antonio.

## 14. Cosa non fare

- Non usare `float` per importi o minuti.
- Non cambiare stack, versioni o architettura.
- Non sviluppare le evolutive (EV-01…EV-24) né le funzioni del rilascio 1.1 (D-236, D-237) durante la 1.0.
- Non introdurre tabelle di permessi configurabili: i ruoli sono fissi (D-208).
- Non installare i font nel sistema e non committarli.
- Non usare risorse esterne (CDN, font web, immagini remote) nei documenti PDF.
- Non modificare una migrazione già unita a `main`: crea una nuova migrazione.
