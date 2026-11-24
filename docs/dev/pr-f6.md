# Fetta F6 — M08 Contenuti e KPI: elenco delle PR

Stato: ✅ approvato (D-485…D-494, 24/11/2026). Sviluppo dal 24/11/2026, in anticipo sul calendario (F6 era prevista dal 25/2/2027); collaudi e blocco del codice seguono la data della demo, ancora da confermare (D-485).
Decisioni della F6-02: D-495…D-499 (24/11/2026).
Riferimenti: `docs/specs/spec-M08-contenuti-kpi.md`, progettazione-tecnica §5.3.5, `docs/layout-dinamici.md` (§3, §4.3–§4.7), `docs/dev/pr-demo.md` (parte di M08 già fatta nella fetta DM), D-107…D-119, D-202…D-206, D-250…D-252, D-258, D-402…D-437.

## Cosa c'è già su `main` (fetta DM)

| Spec M08 | Stato nel codice |
|---|---|
| F-01 Libreria slide per ambito e stagione, con attributi e condizioni | ✅ DM-02: `slides`, `slide_conditions`, "Contenuti › Libreria slide" (admin e gestore, D-406), titolo (D-402), disattivazione al posto della cancellazione |
| Sezioni | ✅ Fisse dal seeder (D-406): nessuna pagina, niente da fare |
| Condizioni §4.2 (livello, partnership, format, LED, hospitality, competizione) | ✅ Tutti e sei i tipi (`SlideConditionType`), OR/AND, regola delle varianti (D-118, D-404), ambito per competizione nel composer (D-251) |
| F-02 PNG 1920×1080, 10 MB | ✅ `FilePurpose::SlideImage`; **M08-CA8** con test |
| F-03 Slide dinamiche | 🟡 Solo i layout della demo: `cover`, `network_recap`, `club_recap`, `led_mockup`, `custom_blocks`. Gli altri sei layout (club coinvolti, KPI network, KPI campagna, digital key figures, KPI club, digital club) non esistono |
| `slides.layout_options` (D-250) | 🟡 Colonna presente, mai letta né scritta |
| RB-01…RB-03, §10 "stesso ordine" | ✅ `DocumentComposer` (unica implementazione, già pensata per un ingresso costruito a mano: `CompositionInput`); avviso `duplicateOrder` in libreria; **M08-CA1, CA2, CA3, CA4** con test |
| F-04 Simulatore (**CA5**) | ✅ F6-02: "Contenuti › Simulatore" (D-494…D-498), slide escluse nel composer (D-495), pagine della simulazione LED come nel PDF (D-499) |
| F-05 Schede KPI club e digital (**CA7**) | ❌ Nessuna tabella (`kpi_definitions`, `club_kpi_values`); voce di menu segnaposto `content_kpi` |
| F-06 Aggregazioni network, RB-05 | ❌ |
| F-08 Avviso KPI non aggiornati | ❌ |
| F-09 Controllo di completezza | ❌ (esistono solo gli avvisi di copertina, recap, chiusura, simulazione LED e sfondo della slide composta nella composizione di un gruppo) |
| `kpi_snapshot` del documento (D-111) | 🟡 Contiene solo i KPI di campagna della versione, nessun KPI di club |
| `layout_texts` (D-202) | ❌ Serve ai testi dei layout KPI: con F7 |
| M08-CA6 (recap club con listino, sconto, totale) | ✅ Nei fatti con DM-04 (D-415), senza un test con il nome del CA |

## PR

| PR | Contenuto | Criteri e decisioni | Dipende da |
|---|---|---|---|
| **F6-00** Piano e decisioni | Registro D-485…D-494, questo file, `docs/pianificazione.md`, `docs/README.md` | — | — |
| **F6-01** KPI e digital | Tabelle `kpi_definitions` (13 KPI di D-108 dal `ContentSystemDataSeeder`, gruppi club e digital, flag di aggregazione network) e `club_kpi_values` (club × stagione × KPI, valore intero ≥ 0, fonte, periodo, aggiornato il / da); Action `SetClubKpis` con l'audit dei soli valori cambiati (area `content`); pagina **Contenuti › KPI e digital** (club Infront iscritti nella stagione della barra, con valori presenti su 13, ultimo aggiornamento e avviso dei KPI non aggiornati da oltre 6 mesi) e scheda del club con le due sezioni KPI club e Digital club; stagione chiusa in sola lettura; test di matrice. KPI fittizi anche in `demo:prepare`, per i collaudi | M08 F-05, F-08, §5, §9; D-108, D-109, D-203, D-490…D-492 | — |
| **F6-02** Simulatore di composizione | Pagina **Contenuti › Simulatore** (admin e gestore, stagione della barra): uno o più club con livello, format e hospitality (competizione dall'iscrizione), "Documento completo" / "Solo riepilogo"; risultato da `DocumentComposer` con un `CompositionInput` costruito a mano: tipo di documento, slide in ordine per sezione con le miniature, numero di pagine, avvisi di composizione (quelli di completezza arrivano con F6-04); anche le slide escluse con la condizione non soddisfatta. Nessun salvataggio | M08 F-04, FL-01, **M08-CA5**; D-118, D-404, D-494 | F6-01 |
| **F6-03** KPI nei documenti | Servizio `ClubKpis` (KPI in vigore di un club e somme network: solo i club con il valore, club mancanti elencati, solo i club della competizione della slide), unica implementazione per il simulatore e per i layout di F7; KPI dei club del documento copiati nel `kpi_snapshot` alla generazione (valore, fonte, periodo, data); avviso "club senza KPI" nella composizione di un gruppo (dettaglio per chi approva); test con il nome di M08-CA6 sul recap club di DM-04 | M08 F-06, RB-04, RB-05, §10, **M08-CA7**, CA6; D-111, D-204, D-251, D-486, D-493 | F6-01 |
| **F6-04** Controllo di completezza | Servizio `ContentCompleteness` per la stagione: per ogni club vendibile (`SellableOptions`) KPI mancanti, slide obbligatorie del documento club (copertina, recap club, chiusura), una slide per ogni livello e format a listino; per il network le stesse verifiche sulle slide Infront; pannello "Completezza" nella libreria e avvisi di completezza nel simulatore; nella composizione di un gruppo l'avviso "nessuna slide per …" per il livello o il format senza slide (il documento si crea comunque) | M08 F-09, §10; D-405, D-416, D-489 | F6-01, F6-02 |
| **F6-05** Opzioni dei layout | `layout_options` (D-250) nel modulo della libreria per le slide dinamiche esistenti: copertina (`logo_box`, `date_color`, `text_color`), recap club (`accent_color`, `text_color`), recap network (`text_color`); validazione e valori predefiniti; applicate da `CoverLayout` e `RecapStyle`; "Anteprima" della slide con dati fittizi (`RendersPdf::screenshot()`); riferimenti della regressione visiva aggiornati. I layout KPI avranno le loro opzioni in F7 | D-250, D-487; layout-dinamici §3 | — |

Ordine (D-485): F6-01 → F6-02 → F6-03 → F6-04; F6-05 in parallelo. Il simulatore viene subito dopo i KPI, così è pronto per primo se la demo si sposta.

## Calendario (D-485)

| Date | Lavoro |
|---|---|
| 24/11 | F6-00 |
| 25–30/11 | Sviluppo di F6-01 e F6-02 (PR aperte, CI verde) |
| Demo il 4/12, se confermata | Nessun collaudo di F6 dall'1 al 4/12 (prova generale, PPTX); la VM resta sull'istantanea "demo 4/12"; demo all'etichetta `demo-2026-12-04`, senza parti di F6 |
| Demo spostata entro circa 2 settimane | Stessa etichetta; prova generale e PPTX nei 2–3 giorni prima della nuova data; collaudi di F6 su un'istantanea diversa da "demo 4/12" |
| Demo spostata oltre circa 3 settimane | Si valuta un'etichetta nuova da `main` dopo F6-02 (con F5 e il simulatore), con nuova prova generale e nuova istantanea; decisione alla conferma della data |
| Dopo la demo | Collaudi delle PR di F6 (circa 2 settimane con 8–10 ore a settimana); A2 con il calendario reale (`migrate:fresh --seed`, D-355) |
| entro il 15/12 | Controllo dell'eccezione di `npm audit` (D-479) |

## Fuori da F6

- **F7** (D-486): i sei layout dinamici dei KPI con `layout_texts`, le loro opzioni e i loro sfondi standard (D-250, D-258, entro il 10/3/2027), derivati JPEG q85, riduzione del tempo dei controlli, prova sullo staging.
- **F8**: avvisi di KPI e completezza nella dashboard di admin e gestore (ux-architettura §2).
- **Rilascio 1.1**: copia di slide, condizioni e KPI dalla stagione precedente (F-07, D-236).
- KPI del vecchio sistema (D-206): con gli script dei dati iniziali (F9), dopo aver chiesto a Infront se servono ancora (D-488).

## Avanzamento

| PR | Esito |
|---|---|
| F6-00 | ✅ Unita il 24/11/2026 (#133, decisioni D-485…D-494) |
| F6-01 | ✅ Unita e collaudata il 24/11/2026 (#134): elenco, scheda, errori di fonte e periodo, decimali rifiutati, nessuna modifica, rimozione, ruoli, avviso oltre 6 mesi, audit `club_kpis.updated` |
| Fuori fetta | ✅ #135 unita il 24/11/2026: `web-auth/webauthn-lib` 5.3.13 (avvisi di sicurezza del 17/11; vincolo diretto da togliere quando Fortify o `laravel/passkeys` lo richiederanno da soli) |
| F6-02 | Aperta (decisioni D-495…D-499) |
