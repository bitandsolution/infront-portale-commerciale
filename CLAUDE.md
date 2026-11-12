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
| Fuori perimetro | `docs/features-evolutive.md` | EV-01…EV-26: **non si sviluppano** |

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

Aree (`<Area>`): `Users` (M01), `Sports` (M02), `Catalog` (M03), `Inventory` (M04), `Clients` (M05), `Proposals` (M06, M07), `Content` (M08: libreria slide e composizione dei documenti dalla DM-02), `Documents` (M09), `Audit` (M10), `Reports` (M10), più le aree trasversali `Files` (archiviazione dei file, C10), `Settings` (impostazioni di sistema, PR-07), `Notifications` (invio e registro delle e-mail, PR-08) e `Operations` (salute, avvisi delle code, Sentry: PR-09).

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
| Valori del catalogo modificabili dall'admin (nome, ordine; descrizione solo dei format; attivo tranne le categorie) | `CatalogValueKind` (`led_format`, `partnership_tier`, `hospitality_variant`, `benefit_category`), Action `UpdateCatalogValue` (`App\Domain\Catalog\Actions`), pagina "Catalogo › Valori" (`catalog_values`, D-331, D-339); area di audit `catalog` |
| Dati di sistema del catalogo (codici, bacini consumati dai format, flag della Libera) | `CatalogSystemDataSeeder`, chiamato da `SystemDataSeeder` (D-332) |
| Listino | `PriceList` · `price_lists` (`status`: `PriceListStatus` draft (Bozza) / published (Pubblicato): la pubblicazione non si annulla, i prezzi restano modificabili, D-333) |
| Righe e colonne delle griglie del listino (club Infront attivi iscritti a una competizione della stagione; format attivi di un tipo di visibilità attivo) e club senza offerte prima della pubblicazione | `PriceListGrid` (`App\Domain\Catalog\Services`, D-337, D-341): `clubs()`, `columns(PriceKind)`, `clubsWithoutOffers()` (LED o partnership) |
| Tipo di prezzo del listino (griglia club × valore del catalogo) | `PriceKind`: `led` (costo per 1′ a partita del format), `partnership` (prezzo stagionale del livello, mai per la Libera), `hospitality` (prezzo a posto della variante); segmento delle pagine e della rotta `catalog.prices.update` |
| Creazione, prezzo di una cella, pubblicazione del listino | `CreatePriceList`, `SetPrice` → `PriceChange` (created / updated / removed / unchanged), `PublishPriceList` (`App\Domain\Catalog\Actions`); eventi `price_list.created`, `led_price.updated` / `partnership_price.updated` / `hospitality_price.updated` (uno per prezzo, prima e dopo), `price_list.published` |
| Benefit di club e livello (un testo per categoria, facoltativo) / testi on top (di un club o generali) | `SetPartnershipBenefits` (evento `partnership_benefit.updated` per testo) / `SaveOnTopAsset` (`create`, `update`, `remove`; eventi `on_top_asset.*`) (D-335) |
| Regola del catalogo che rifiuta l'operazione | `CatalogActionNotAllowed` (toast) |
| Pagine "Catalogo › Listini LED", "Partnership", "Hospitality" (griglia comune, modifica in cella dell'admin, gestore in sola lettura) e pagina dei benefit e testi on top di un club | `catalog_led`, `catalog_partnership`, `catalog_hospitality`, `PriceListController`, `BenefitController`, `OnTopAssetController`; `PriceListPresenter` (`App\Support\Catalog`); componenti `PriceGridTable`, `OnTopTexts` |
| Importi in euro: notazione italiana nei campi ("3.157,89"), stringhe decimali esatte nel codice | `Amounts` (`App\Support`, `brick/math`, D-336) |
| Calcolo dei prezzi di listino (LED = costo per 1′ × secondi / 60 × partite, un solo arrotondamento al centesimo sul club; partnership a prezzo fisso; hospitality = posti × prezzo a posto) | `PriceCalculator` (`App\Domain\Catalog\Services`, F2-06): `quote()` → `Quote` con un `ClubQuote` per club (righe `HospitalityLine`), dalla scelta `ClubSelection`; solo listino pubblicato; `ledPrice()`, `hospitalityPrice()`; prezzo stagionale indicativo della pagina Listini LED `seasonalLedPrice()` → `SeasonalLedPrice` (D-340); comando `catalog:price` |
| Costo per 1′ a partita (costo/cad) | `unit_price` su `led_prices` |
| Prezzo stagionale partnership | `season_price` su `partnership_prices` |
| Prezzo a posto hospitality | `seat_price` su `hospitality_prices` |
| Benefit | `PartnershipBenefit` · `partnership_benefits` |
| Asset on top | `OnTopAsset` · `on_top_assets` |
| Capacità (magazzino) | `PoolCapacity`, `PartnershipCapacity`, `HospitalityCapacity` |
| Bacino gestito (flag) | `is_enabled` |
| Impegno di magazzino | `InventoryCommitment` · `inventory_commitments` (`resource_type`: `InventoryResource` pool / partnership / hospitality; immutabile per trigger, solo rilascio una volta con `release_reason` `CommitmentReleaseReason` superseded / cancelled / not_chosen, D-192, D-410; FK verso proposta, versione e riga club dalla DM-03b, D-334; nei test `InventoryCommitmentFactory::proposalRow()`) |
| Capacità di un club nella stagione (gestito + quantità, salvate insieme, conferma se sotto l'impegnato) | `SetClubCapacities` (`App\Domain\Inventory\Actions`) con `CapacityInput`; `CapacityBelowCommitted` (conferma, RB-10); eventi `pool_capacity.updated`, `partnership_capacity.updated`, `hospitality_capacity.updated`; area di audit `inventory` |
| Residuo di magazzino (capacità − impegni aperti, metodo stagionale) | `InventoryAvailability` (`App\Domain\Inventory\Services`) → `ResourceAvailability`; con `per_match` rifiuta (D-293) |
| Regola del magazzino che rifiuta l'operazione | `InventoryActionNotAllowed` (toast) |
| Opzioni vendibili di un club per il configuratore (listino pubblicato, prezzo presente, bacini e contatori gestiti; esaurito distinto da non gestito) | `SellableOptions` (`App\Domain\Inventory\Services`, F2-05): `forClub()` → `ClubSellability` con le `SellableOption` di format, livelli e varianti; `selectableClubs()`. Stato `SellableStatus` (`available`, `sold_out`, `not_managed`, `not_available`); club non selezionabile `ClubSellabilityReason` (`not_in_season`, `no_price_list`, `price_list_draft`, `no_prices`) |
| Pagina "Magazzino › Disponibilità" (admin e gestore, sola lettura) | `inventory_availability`, `AvailabilityController`, `AvailabilityPresenter` (`App\Support\Inventory`); comando `inventory:availability` |
| Durate LED (secondi ↔ "28′30″", minuti digitati "28,5") | `LedDuration` (`App\Support`) |
| Pagine "Magazzino › Capacità" e capacità di un club | `inventory_capacity`, `CapacityController`, rotte `inventory.capacities.edit` / `.update`; `InventoryPresenter` (`App\Support\Inventory`) |
| Residuo | `remaining` (calcolato, mai salvato) |
| Forzatura | `is_forced`, `forced_reason` |
| Impostazioni | `Setting` · `settings` |
| Chiave di un'impostazione (tipo, predefinito, limiti, modificabilità) | `SettingKey` (`availability_method`, `led_step_seconds`, `hospitality_max_seats`, `forced_reason_min_length`, `audit_ip_retention_days`, `proposal_max_variants`) |
| Lettura delle impostazioni (predefinito se la riga manca) / modifica dall'admin | `Settings` (`App\Domain\Settings\Services`) / `UpdateSettings` (`App\Domain\Settings\Actions`) |
| Metodo di calcolo della disponibilità | `AvailabilityMethod`: `seasonal` (Stagionale), `per_match` (Per partita, futuro) |
| Dati di sistema (impostazioni, poi cataloghi) | `SystemDataSeeder`, idempotente, in ogni ambiente e a ogni deploy |
| Settore merceologico | `CommoditySector` · `commodity_sectors` (`code` stabile; `requires_free_text` solo per ALTRO_VARIE = `CommoditySector::OTHER`, RB-04); 24 voci da `ClientsSystemDataSeeder` (D-357); pagina "Clienti › Settori" (`clients_sectors`, solo admin, `Ability::CommoditySectorsManage`), Action `CreateCommoditySector` (codice dal nome, `codeFor()`) e `UpdateCommoditySector` (nome, ordine, attivo; mai cancellato, RB-08); area di audit `clients`, eventi `commodity_sector.created` / `.updated` |
| Cliente | `Client` · `clients` |
| Ragione sociale | `company_name` (+ `company_name_normalized`) |
| P.IVA | `vat_number` |
| Referente | `contact_name` |
| Agente proprietario del cliente | `owner_id` (sempre un agente attivo; l'admin lo sceglie quando crea un cliente, D-358) |
| Ragione sociale normalizzata (maiuscole, senza accenti, spazi, punteggiatura e forma societaria finale) / P.IVA italiana (11 cifre, cifra di controllo; spazi e prefisso IT tolti) | `CompanyNameNormalizer` / `VatNumber`, regola `ItalianVatNumber` (`App\Domain\Clients`, D-361) |
| Controllo dei duplicati su tutta l'anagrafica (stessa P.IVA, oppure stesso nome normalizzato salvo P.IVA entrambe presenti e diverse) | `DuplicateClients` → `DuplicateClient` (dialog bloccante; messaggio neutro all'agente, nome e agente all'admin); scritture sotto il lock `ClientRegistry` (RB-02, RB-03, D-197) |
| Creazione e modifica di un cliente (logo nuovo = file nuovo) | `CreateClient`, `UpdateClient` (`App\Domain\Clients\Actions`); eventi `client.created`, `client.updated`; regola che rifiuta `ClientsActionNotAllowed` (toast) |
| Pagine "I miei clienti" (agente; l'admin va all'elenco), "Elenco clienti" (admin e gestore, con l'agente), nuovo cliente e scheda (gestore in sola lettura) | `clients_mine`, `clients`, `ClientController`, rotte `clients.create` / `.store` / `.edit` / `.update`, `ClientPolicy`; pagine `Clients/Index`, `Clients/Form` |
| Riassegnazione di un cliente a un altro agente attivo (le proposte restano all'agente originale) | `ReassignClient` (`App\Domain\Clients\Actions`, D-082, D-083), evento `client.reassigned` (`owner_id`, `owner_name`); usata anche dalla disattivazione dell'agente (F3-04) |
| Disattivazione e riattivazione di un cliente (mai cancellato; resta nel controllo dei duplicati) | `ChangeClientStatus` (`deactivate()`, `reactivate()`; RB-06), eventi `client.deactivated` / `client.reactivated` |
| Unione di un duplicato nel cliente che resta (attivo e non unito; il duplicato diventa non attivo con `merged_into_id`, in sola lettura, mai riattivato; i clienti già uniti al duplicato passano a quello che resta) | `MergeClients` (D-359), `Client::isMerged()`, evento `client.merged` (`merged_into_id`, `merged_into_name`); stato nelle pagine `ClientStatus` (`active`, `inactive`, `merged`) |
| Azioni dell'admin sul cliente (scheda, sezione "Gestione del cliente") e filtri dell'elenco (agente, stato, "solo clienti di agenti disattivati", anche con `?agenti=disattivati`, D-360) | `ClientManagementController`, rotte `clients.reassign` / `.deactivate` / `.reactivate` / `.merge`, `ClientPolicy::manage()` (`Ability::ClientsManageAll`); `ClientsPresenter::management()` |
| Clienti dell'agente disattivato (dialogo "Disattiva utente": numero dei clienti e agente attivo facoltativo che li riceve tutti, nella stessa transazione; i clienti uniti restano) | `DeactivateUser::handle($user, $actor, $clientsOwner)` → `Deactivation` (`reassignedClients`, `clientsOwner`), campo `clients_owner_id` (`DeactivateUserRequest`); prop `clients` della pagina Utenti (`AgentClients`; M01-CA4, D-360) |
| Proposta | `Proposal` · `proposals` (agente proprietario `agent_id`, D-376; si cancella solo la bozza senza versioni congelate, in cascata, trigger `proposals_before_delete`, M07 RB-08) |
| Numero della proposta (progressivo dalla 204, mai riusato, assegnato alla nascita della bozza) | `number`, dalla sequenza MariaDB `proposal_numbers` con `ProposalNumbers::next()` (`App\Domain\Proposals\Services`, D-371); `migrate:fresh` non cancella le sequenze: la migrazione usa `CREATE OR REPLACE` |
| Versione (di lavoro / congelata) | `ProposalVersion` · `proposal_versions` (`number` nullo = di lavoro, una sola per proposta con la colonna generata `working_slot`; congelata = `number`, `frozen_at`, `frozen_by`, immutabile: modello e trigger, D-198) |
| Modifica di una versione congelata, delle sue righe o della cronologia (errore di programmazione) | `ProposalImmutable` (`App\Domain\Proposals\Exceptions`) |
| Riga club della versione | `ProposalVersionClub` · `proposal_version_clubs` (fonte delle partite `matches_source`: `MatchesSource` calendar / standard) |
| Posti hospitality della riga | `ProposalVersionClubSeat` · `proposal_version_club_seats` |
| Cronologia della proposta | `ProposalEvent` · `proposal_events` (solo inserimenti, trigger; `type`: `ProposalEventType`, D-199) |
| Stati della proposta | `ProposalStatus`: `draft` (Bozza), `submitted` (In approvazione), `approved` (Approvata), `rejected` (Rifiutata), `cancelled` (Annullata), `not_chosen` (Non scelta: variante non scelta dal cliente, D-410); nessun `historical` (D-376) |
| Permessi sulle proposte (l'agente solo sulle proprie, modifica ed eliminazione solo in bozza; admin e gestore in consultazione) | `ProposalPolicy` (`viewAny`, `view`, `create`, `update`, `delete`, `duplicate`); area di audit `proposals` (D-372, D-376) |
| Bozza del configuratore: nascita con la scelta del cliente (mai vuota), salvataggio automatico con `lock_version`, eliminazione | `CreateDraft` (cronologia `created`), `SaveDraft` → `DraftSaved` (conflitto `DraftConflict`, errori del periodo per campo), `DeleteDraft` (`App\Domain\Proposals\Actions`, D-372…D-375); eventi `proposal.draft_created`, `proposal.draft_updated` (solo i campi cambiati), `proposal.draft_deleted` con `ProposalsAuditValues`; rifiuti `ProposalsActionNotAllowed` (toast) |
| Stagione e periodo della proposta (stagioni non chiuse; predefiniti: stagione della barra, inizio stagione o oggi, fine stagione; controlli di M06 §8) | `ProposalPeriod` (`App\Domain\Proposals\Services`): `seasons()`, `defaultSeason()`, `defaultStart()`, `defaultEnd()`, `errors()` |
| Pagine del configuratore (passi 1–2 in F4-02, 3–4 in F4-03, 5 in F4-04, 6 "Documento" in DM-05, passo nell'indirizzo `?passo=`) e "Le mie proposte" (bozze: riapri, duplica, elimina) | `proposals_new`, `proposals_mine`, `ProposalController`, `ProposalClientController` (cliente nuovo dal passo 1), rotte `proposals.store` / `.edit` / `.update` (al massimo 60 salvataggi al minuto) / `.destroy`, `proposals.clients.store` / `.update`; `ProposalsPresenter` (`App\Support\Proposals`); pagine `Proposals/Configurator`, `Proposals/Mine`, componente `proposals/NewClientDialog` |
| Club e configurazione per club della bozza (passi 3–4: club selezionabili, pacchetto, un format con i minuti a passi di 30″ fino al residuo, posti hospitality fino al massimo per club e al residuo) | `DraftClubData` (in `DraftData::$clubs`; nullo = club non inviati, restano quelli salvati), righe `proposal_version_clubs` e `proposal_version_club_seats` scritte da `SaveDraft` con partite, prezzi e totale calcolati dal server nella stessa richiesta; nell'audit un campo `club_<codice del club>` per club e `list_total`; componenti `proposals/ClubPicker`, `proposals/ClubConfiguration`, `proposals/NumberStepper` (F4-03) |
| Valutazione della bozza (tutti gli errori, club per club, senza fermarsi al primo: club non selezionabile o non iscritto a un campionato, opzione esaurita o non più in vendita, minuti o posti oltre il residuo, LED senza partite, club senza elementi) | `DraftEvaluator` (`App\Domain\Proposals\Services`, usa `SellableOptions` e `PriceCalculator`) → `DraftEvaluation` con una `ClubEvaluation` per club (errori per campo: `club`, `tier`, `led_format`, `led_seconds`, `matches`, `seats`, `seats.<id variante>`, `elements`); una scelta nuova non valida è rifiutata da `SaveDraft` (errore `clubs.<id club>.<campo>`), una già salvata resta con l'errore (M06 §9, D-373, D-382) |
| Riepilogo della bozza (passo 5: tabella per club, KPI di campagna, totale di listino, avvisi; si apre solo se ogni club ha almeno un elemento, D-373) | componente `proposals/DraftSummary`; `DraftEvaluation::blocksSummary()`, `ClubEvaluation::lacksElements()`; avvisi `ProposalsPresenter::warnings()` (numero standard, top match non disponibili, errori delle scelte salvate); `ProposalController::SUMMARY_STEP` (F4-04) |
| KPI di campagna (club, partite in casa, partite con LED, spot = secondi / 15 × partite, mesi, top match solo nelle competizioni con il KPI) | `CampaignKpis` (`App\Domain\Proposals\Data`, da `DraftEvaluator` con `MatchCounter` su tutti i club con un prezzo); salvati sulla versione di lavoro da `SaveDraft` (`campaign_starts_on`, `campaign_months`, `total_matches`, `total_spots`, `top_matches`) e nell'audit (D-093, F4-04) |
| Ricalcolo alla riapertura ("da € X a € Y": prezzi salvati sulle righe contro quelli correnti, con il motivo: listino o partite) | `DraftRepricing` (`App\Domain\Proposals\Services`) → `DraftPriceChanges` con un `ClubPriceChange` per club; prop `reopened` della pagina, calcolata solo all'apertura; aprire la bozza non scrive nulla (M06 RB-12, M03-CA6, F4-04) |
| Copia su altri club (pacchetto, format e minuti, posti di un club sostituiscono la configurazione degli altri club scelti; le opzioni non vendibili, oltre il residuo o il LED senza partite si saltano e si segnalano club per club, l'elemento saltato resta com'era) | `CopyClubConfiguration` (`App\Domain\Proposals\Actions`, valuta con `DraftEvaluator` e salva con `SaveDraft`: evento `proposal.draft_updated`) → `ClubsCopied`; rotta `proposals.copy-clubs`, componente `proposals/CopyClubsDialog`, prop `copied` del configuratore (M06 §5, M06-CA7, F4-05) |
| Duplica proposta (nuova bozza da una proposta dell'agente, anche per un altro cliente attivo e non unito; stagione non chiusa e date ricalcolate su oggi; listino, partite e residui correnti; club non più validi copiati con l'errore e segnalati) | `DuplicateProposal` (`App\Domain\Proposals\Actions`) → `ProposalDuplicated`; `duplicated_from_id` (D-377), cronologia `duplicated` con la nota dell'origine, evento `proposal.draft_duplicated`; rotta `proposals.duplicate` da "Le mie proposte", prop `duplicated` del configuratore (D-091, M06-CA8, F4-05) |
| Gruppo di varianti di una proposta (A…J nello stesso documento, stesso cliente e stessa stagione; una proposta singola è la variante A del suo gruppo) | `ProposalGroup` · `proposal_groups` (agente `agent_id`, `client_id`, `season_id` allineati alle varianti da `SaveDraft`); su `proposals` `proposal_group_id` e `variant_letter`; lettere `ProposalVariants::LETTERS`, `nextLetter()` (prima lettera libera), `newGroup()` (D-396, DM-01) |
| Aggiungi variante (copia della bozza nel suo gruppo, al massimo `proposal_max_variants`; con più varianti cliente e stagione non cambiano) | `AddVariant` (`App\Domain\Proposals\Actions`, blocca la riga del gruppo; usa `DuplicateProposal` con il gruppo) → `ProposalDuplicated`; cronologia `variant_added`, evento `proposal.variant_added`; rotta `proposals.variants.store`, `ProposalVariantController`; rifiuti `ProposalsActionNotAllowed::tooManyVariants()` / `variantsShareHeader()`; impostazione `SettingKey::ProposalMaxVariants`, `Settings::proposalMaxVariants()` (D-396) |
| Scrittura delle righe club e dei posti di una versione di lavoro da una valutazione / totale e KPI della versione | `DraftRowsWriter` (`App\Domain\Proposals\Services`): `write()`, `versionTotals()`; usato da `SaveDraft` e `DuplicateProposal`; controller `ProposalCopyController` (F4-05) |
| Proposta modificabile dal configuratore (bozza o rifiutata) / inviabile | `Proposal::isEditable()`, `Proposal::isSubmittable()`; il trigger `proposals_before_delete` ammette la cancellazione di bozze e rifiutate senza versioni congelate (D-412) |
| Invio in approvazione del gruppo (tutte le varianti insieme, ricontrollate su listino e residui; reinvio dopo un rifiuto) | `SubmitGroup` (`App\Domain\Proposals\Actions`, blocca gruppo e varianti; valuta con `DraftEvaluator::evaluateProposal()`); cronologia `submitted` / `resubmitted`, evento `proposal.submitted` per variante; rifiuti `ProposalsActionNotAllowed::groupNotReady()` (lettere delle varianti) / `groupNotSubmittable()`; rotta `proposals.groups.submit` (D-395, M07-CA6, DM-03a) |
| Rifiuto del gruppo (tutte le varianti, motivazione facoltativa) | `RejectGroup` (`App\Domain\Proposals\Actions`), `RejectGroupRequest`; `decided_at`, `decided_by`, `last_rejection_reason`; cronologia `rejected` con la nota, evento `proposal.rejected` con il motivo; rifiuto `groupNotSubmitted()`; rotta `proposals.groups.reject` (D-412, DM-03a) |
| Pagine "Proposte › Da approvare" (admin e gestore, dalla più vecchia) e dettaglio di un gruppo (sola lettura, uguale per admin, gestore e agente del gruppo) | `proposals_pending`, rotta `proposals.groups.show`, `ProposalGroupController`, `ProposalGroupPolicy` (`view`, `submit`, `review`); `ProposalGroupsPresenter` (`App\Support\Proposals`: `queue()`, `group()`, `status()`); pagine `Proposals/Pending`, `Proposals/Group`; `DraftSummary` con `readOnly` (D-411, DM-03a) |
| Approvazione del gruppo (lock di gruppo, varianti e capacità in ordine di id; ricontrollo di tutte le varianti e della somma delle loro richieste sui residui; prezzi correnti congelati; sconto; versione 1; impegni; fino a 3 ripetizioni) | `ApproveGroup` (`App\Domain\Proposals\Actions`), `ApproveGroupRequest` con `DiscountForm` (`App\Support\Proposals`, sconti per id della proposta, anche come parametro `sconti` dell'anteprima nel dettaglio); cronologia `discount_set` e `approved`, evento `proposal.approved`; rifiuto `ProposalsActionNotAllowed::approvalBlocked()` con l'elenco dei problemi; rotta `proposals.groups.approve` (D-216, D-396, D-408, D-409, DM-03b) |
| Controllo del gruppo prima dell'approvazione / richieste di magazzino di un club (LED su ogni bacino del format, un contratto del livello tranne la Libera, posti) | `ApprovalCheck` → `ApprovalReview` (valutazioni, `InventoryNeed` per club e risorsa, problemi) / `GroupInventory` (`demand()`, `needs()`) (`App\Domain\Proposals\Services`, D-051, D-052, D-396) |
| Sconto sul totale di una variante (percentuale 0…100 o importo fino al listino; arrotondamento al centesimo; percentuale equivalente) | `Discounts::apply()` → `AppliedDiscount`; inserito come `DiscountInput` (D-097, D-103, D-408; M07-CA2, CA3) |
| Scelta del cliente (gruppo approvato con 2 o più varianti, una volta sola; le altre `not_chosen` con impegni rilasciati) | `RecordClientChoice` (`App\Domain\Proposals\Actions`), `ChooseVariantRequest`; cronologia `client_chosen`, evento `proposal.client_chosen`; rifiuti `groupNotApproved()` / `variantNotInGroup()`; rotta `proposals.groups.choose` (D-410, DM-03b) |
| Pagina "Proposte › Tutte" (admin e gestore: tutti i gruppi, dal modificato più di recente, filtro `?stato=`, al massimo 200; il dettaglio di un gruppo già deciso torna qui) | `proposals_all`, `ProposalGroupController::all()`, `ProposalGroupsPresenter::list()` e `statusOptions()`; pagina `Proposals/All` (DM-03b) |
| Riepilogo di una versione congelata nella forma del configuratore (righe, prezzi, partite e KPI salvati, mai ricalcolati) | `ProposalsPresenter::frozen()`; nel dettaglio del gruppo `DraftSummary` con `inGroup` e `hideNote` (DM-03b) |
| Ritorno alla pagina dopo una sessione scaduta su un invio (salvataggio automatico, CSRF scaduto) | `ReturnAfterLogin` (`App\Support\Navigation`): pagina d'origine dal Referer, solo del portale; 419 durante una visita Inertia → login (D-375, D-379) |
| Sconto | `discount_type` (`DiscountType`: percent / amount), `discount_value`, `discount_amount` |
| Totale di listino / finale | `list_total` / `final_total` |
| Blocco di lavorazione | `review_locked_by`, `review_locked_at` |
| Sezione delle slide | `SlideSection` · `slide_sections` (fisse, da `ContentSystemDataSeeder`: INTRO, CLUB, PARTNERSHIP, BENEFIT, BRAND_EXPOSURE, RECAP, CLOSING = `SlideSection::CLOSING`; nessuna pagina, D-406) |
| Slide (statica / dinamica) | `Slide` · `slides` (`title` obbligatorio, D-402; `scope`: `SlideScope` infront / club; `document_type`: `SlideDocumentType` network / club / both; `kind`: `SlideKind` static / dynamic; immagine `image_file_id`, nulla solo per la simulazione LED; mai cancellata, si disattiva, D-406) |
| Condizione di inclusione | `SlideCondition` · `slide_conditions` (`condition_type`: `SlideConditionType` partnership_tier, has_partnership, led_format, has_led, has_hospitality, competition_tier; valori come codici stabili, `"true"` / `"false"` per i sì/no, il livello della competizione `"1"`…`"3"`; stesso tipo = OR, tipi diversi = AND, D-202) |
| Layout dinamico | `layout_code` (enum `DocumentLayout`: `cover`, `network_recap`, `club_recap`, `led_mockup`; obbligatorie copertina e recap, `isMandatory()`; recap una pagina per variante; simulazione LED senza immagine, una pagina per club con il LED, D-405) |
| Libreria slide (admin e gestore, stagione della barra, stagione chiusa in sola lettura) | `CreateSlide`, `UpdateSlide` (`App\Domain\Content\Actions`, condizioni con `SlideConditions`), dati `SlideData`; eventi `slide.created`, `slide.updated` con `ContentAuditValues` (area di audit `content`); rifiuti `ContentActionNotAllowed` (toast); `SlidePolicy`, `SlideController`, `SlideRequest` (`slideImage()`, valori ammessi delle condizioni `allowedValues()`), `ContentPresenter` (`App\Support\Content`); pagina `content_slides`, rotte `content.slides.create` / `.store` / `.edit` / `.update`; pagine `Content/Slides/Index`, `Content/Slides/Form` (DM-02, D-402…D-406) |
| Tipo di documento (club con un solo club distinto in tutte le varianti, network con 2 o più) | `DocumentType` (`forClubs()`, D-121, D-403) |
| Composizione del documento di un gruppo (slide candidate per stagione, lingua, tipo e ambito; condizioni vere se le soddisfa almeno un club di almeno una variante; ordine sezione → ordine → id; pagine per variante o per club; avvisi per copertina, recap e chiusura mancanti) | `DocumentComposer` (`App\Domain\Content\Services`): `forGroup()`, `compose(CompositionInput)` → `Composition` con le `ComposedSlide` (`mandatory`, `inSummary`, `instances` di `SlideInstance`, `competitionTiers` per D-251); ingresso `CompositionInput::fromGroup()` con `VariantFacts` e `ClubFacts`; comando `documents:compose` (D-118, D-396, D-403…D-405) |
| Slide scelte per il documento di un gruppo (modalità "Documento completo" / "Solo riepilogo" e slide escluse; copertina e recap sempre; in "Solo riepilogo" solo copertina, recap e chiusura; scelta di lavoro sul gruppo, congelata sulle versioni all'approvazione) | `DocumentMode` (`complete`, `summary`), `DocumentSelection` (`App\Domain\Content\Data`: `includes()`, `fromColumns()`); colonne `document_mode`, `excluded_slide_ids` su `proposal_groups` e `proposal_versions`; `ComposedSlide::$included`, `Composition::includedSlides()` (D-397, D-418, D-419, DM-05) |
| Passo "Documento" del configuratore (passo 6: slide per sezione con miniature e spunte, "Invia in approvazione") e slide del documento nel dettaglio del gruppo (admin e gestore mentre è in approvazione) | `SaveDocumentSlides` (`App\Domain\Proposals\Actions`, lock del gruppo; agente su bozze e rifiutate, chi approva in approvazione: `editableBy()`; rifiuto `ProposalsActionNotAllowed::documentNotEditable()`), evento `proposal.document_slides_updated`; `ProposalGroupPolicy::editDocument`, `DocumentSlidesRequest`, `ProposalGroupDocumentController`, rotta `proposals.groups.slides`; `DocumentSlidesPresenter` (`App\Support\Proposals`), prop `documentSlides` del configuratore e del dettaglio; `ProposalController::DOCUMENT_STEP`; componente `proposals/DocumentSlides` (DM-05) |
| File derivato (miniatura JPEG 480×270 di un'immagine, fatta alla prima richiesta e mai sovrascritta; chi vede l'originale vede la miniatura) | `StoredFileDerivative` · `stored_file_derivatives` (`kind`: `FileDerivativeKind` thumbnail; solo inserimenti, trigger), `FileDerivatives` (`App\Domain\Files\Services`: `thumbnail()`), `FilePurpose::Thumbnail`; rotta `files.thumbnail` (solo immagini delle slide), `StoredFileThumbnailController` (D-310, D-420) |
| Simulazione del logo sul LED: configurazione per club e tipo di foto, o generica Infront (club nullo); foto del campo, aree del LED con 4 punti (una per fila, due per Jumbo/Domination), altezza del logo in % del LED, spaziatura; solo l'admin la modifica | `LedMockupSetting` · `led_mockup_settings` (`photo_kind`: `LedPhotoKind` row_1 / row_2 / both_rows, `forPools()`; `club_slot` generata per l'UQ con le generiche), `FilePurpose::FieldPhoto`; `SaveLedMockupSetting` (`App\Domain\Content\Actions`, evento `led_mockup.saved`, area `content`); `LedMockupSettingPolicy`, `LedMockupController`, `LedMockupRequest`, `LedMockupsPresenter`; pagina `content_led_mockups` ("Contenuti › Simulazioni LED"), rotte `content.led-mockups.edit` / `.update` / `.preview`; pagine `Content/LedMockups/Index`, `Content/LedMockups/Edit` (D-398, DM-06) |
| Scelta della configurazione per club e format (del club, altrimenti generica; nulla = pagina saltata con avviso, D-427) / stile (Domination un logo grande sulle due file con la linea, gli altri il logo su ogni fila) | `LedMockups` (`App\Domain\Content\Services`): `kindForFormat()`, `settingFor()`, `styleForFormat()`, `missingWarning()`; `SlideInstance::$formatCode` (una pagina per coppia club + format, D-423) |
| Pagina della simulazione LED (foto a tutta pagina, striscia del colore di sfondo del logo o nera, logo ripetuto con il primo tagliato, prospettiva con `matrix3d` dai 4 punti) | `LedMockupLayout` (`App\Domain\Documents\Layouts`: `page()`, `viewData()`, `matrix3d()`), vista `documents.layouts.led-mockup`, `LogoStripColor` (D-424, D-425); `LayoutPage::$backgroundPath` nullo; anteprima con `RendersPdf::screenshot()` (D-398, DM-06) |
| Slide composta a blocchi di un gruppo (al massimo 3; titolo, schema, sezione in fondo alla quale entra, immagine dello schema "immagine a sinistra", blocchi in JSON, spunta) | `ProposalCustomSlide` · `proposal_custom_slides` (`layout`: `CustomSlideLayout` one_column / two_columns / image_text; `MAX_PER_GROUP`); copia congelata `proposal_versions.custom_slides`; dato `CustomSlide` (`App\Domain\Content\Data`: `fromModel()`, `fromArray()`, `toArray()`) (D-399, D-428…D-432, DM-07) |
| Blocchi della slide composta (titolo, testo con `**grassetto**` e righe "- ", tabella fino a 6 × 4 con intestazione, immagine, immagine con testo; al massimo 6 blocchi e 2 immagini) | `CustomBlockType`, regole e forma normalizzata `CustomSlideContent` (`normalize()`, `imageCount()`), testo `CustomText` → `CustomParagraph` (D-430) |
| Sfondo della slide composta nella libreria (mai una pagina da solo) / impaginazione dei blocchi (riduzione fino al 70%, poi taglio con "…") | `DocumentLayout::CustomBlocks` (`isCustomBackground()`) / `CustomBlocksLayout` (`arrange()` → `CustomArrangement`, `page()`, `viewData()`), vista `documents.layouts.custom-blocks`, immagini `CustomSlideImages` (D-428, D-431; layout-dinamici §4.11) |
| Scrittura delle slide composte (stessi permessi della scelta delle slide; audit sulla prima variante) | `CreateCustomSlide`, `SaveCustomSlide` (con `CustomSlideInput`), `DeleteCustomSlide`, `AttachCustomSlideImage` (`App\Domain\Proposals\Actions`), lock `GroupDocumentLock`; eventi `proposal.custom_slide_created` / `_updated` / `_deleted` con `CustomSlideAuditValues`; rifiuti `ProposalsActionNotAllowed::tooManyCustomSlides()`, `tooManyImages()`, `customImageTarget()`; spunta nel documento con `SaveDocumentSlides` (`excluded_custom_slide_ids`) |
| Immagini delle slide composte (le vede chi vede il gruppo) | `FilePurpose::CustomSlideImage` (JPEG o PNG fino a 5 MB), `ProposalGroupFile` · `proposal_group_files` |
| Editor e anteprima delle slide composte | `ProposalCustomSlideController`, `CustomSlideRequest`, `CustomSlideImageRequest`, rotte `proposals.groups.custom-slides.store` / `.update` / `.destroy` / `.image` / `.preview`; `CustomSlidesPresenter` (chiave `custom` della prop `documentSlides`); componenti `proposals/CustomSlides`, `proposals/CustomSlideEditor`, coda dei salvataggi `composables/visitQueue` |
| Testi fissi dei layout | `LayoutText` · `layout_texts` |
| Definizione KPI / valore KPI | `KpiDefinition` · `kpi_definitions` / `ClubKpiValue` · `club_kpi_values` |
| Documento (club / network) | `Document` · `documents` (`document_type`: club / network; uno per generazione, del gruppo `proposal_group_id`; corrente con `superseded_at` nullo, colonna generata `current_slot`; `proposal_id` e `proposal_version_id` della prima variante del documento, D-421; `generated_by` nullo per il documento dell'approvazione, l'admin per la rigenerazione, D-422; `page_model` con varianti, modalità, slide escluse e slide, `kpi_snapshot` per variante, D-413) |
| Stato del documento | `DocumentStatus`: `queued` (In coda), `generating` (In preparazione), `ready` (Pronto), `failed` (Non riuscito) |
| Richiesta e rigenerazione del documento di un gruppo (approvazione; admin) / lavoro in coda / generazione | `RequestDocument` (`App\Domain\Documents\Actions`; eventi `document.requested`, `document.regenerated`) / job `GenerateDocument` (coda `documents`, 3 tentativi) / `DocumentGenerator` (eventi `document.generated`, `document.failed`; area di audit `documents`); rotta `documents.regenerate`, `DocumentController`, `DocumentPolicy` (`download`, `regenerate`); componente `proposals/DocumentPanel` (DM-04, D-413…D-416) |
| Composizione delle pagine del documento dalle versioni congelate delle varianti approvate (copertina, recap per variante, slide statiche) | `DocumentBuilder` → `BuiltDocument` (`App\Domain\Documents`); `CompositionInput::fromVersions()`; slide obbligatoria mancante o sfondo senza immagine `DocumentNotComposable` (documento "Non riuscito", D-416); rifiuto della rigenerazione `DocumentsActionNotAllowed` (toast) |
| Resa in PDF (contratto) | `RendersPdf` (`App\Domain\Documents\Contracts`), implementato da `PdfPipeline`; nei test `Tests\Fakes\FakePdfRenderer` |
| Copertina / stile del recap (network, club) | `CoverLayout` (data e logo del cliente, D-252) / `RecapStyle` (`network()`, `club()`: stessa griglia, intestazione nel colore del club, D-415; titolo "Proposta A" della variante, D-396); vista comune `documents.layouts.positioned` |
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
| Visibile all'utente (scope per proprietario) | `visibleTo($user)` (trait `HasOwner`; colonna `owner_id`, oppure quella di `ownerColumn()`: `agent_id` per le proposte, D-376) |
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
| Tipo d'uso del file (con le sue regole) | `FilePurpose`: `client_logo`, `club_logo`, `slide_image` (scaricabile da admin e gestore, dall'agente se la slide è attiva: D-406, DM-05), `calendar_import`, `generated_pdf`, `thumbnail` (D-420) |
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
5. Se durante lo sviluppo emerge un'idea fuori perimetro, aggiungila in `docs/features-evolutive.md` (ultima: EV-26) e **non svilupparla**.
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
./vendor/bin/sail artisan sports:import-legacy-clubs storage/app/private/club-idoc.csv --merge=8,20 --merge=11,39 --merge=21,35 --merge=25,36 --dry-run  # club dal vecchio sistema, una tantum (F1-06; senza --dry-run salva)
./vendor/bin/sail artisan inventory:availability 2027-28 LAZIO  # disponibilità e opzioni vendibili di un club (sola lettura, F2-05)
./vendor/bin/sail artisan catalog:price 2027-28 LAZIO --format=FILA_1 --minutes=1,5 --tier=PREMIUM --seats=PREMIUM:2 --from=01/10/2027  # prezzi di listino di un club (sola lettura, F2-06)
./vendor/bin/sail artisan documents:compose 205  # slide del documento del gruppo di una proposta, con pagine e avvisi (sola lettura, DM-02)
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
- Non sviluppare le evolutive (EV-01…EV-26) né le funzioni del rilascio 1.1 (D-236, D-237) durante la 1.0.
- Non introdurre tabelle di permessi configurabili: i ruoli sono fissi (D-208).
- Non installare i font nel sistema e non committarli.
- Non usare risorse esterne (CDN, font web, immagini remote) nei documenti PDF.
- Non modificare una migrazione già unita a `main`: crea una nuova migrazione.
