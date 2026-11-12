# Fetta DM — Demo del 4/12/2026: elenco delle PR

Stato: ✅ approvato (D-394…D-401, 10/11/2026). Demo a Infront il **4/12/2026** (responsabile commerciale, grafico, ufficio commerciale), presentata da Antonio sulla VM.
Origine: e-mail di Infront (Laura) del 10/11/2026 con quattro richieste: (1) offerte composte solo dalla slide riepilogativa, (2) più proposte A, B, C nello stesso documento, (3) simulazioni del logo sul bordo campo migliori, (4) slide personalizzata a contenuto libero. Nel vecchio portale gli errori più frequenti degli agenti sono la **generazione dell'immagine con il logo sul LED** e il **calcolo dei totali in base alle date** di inizio e fine.

Riferimenti: specifiche M06, M07, M08, M09; progettazione-tecnica §5.3.4, §5.3.5, §5.4.4, §5.5; `docs/layout-dinamici.md`; pipeline della S1 (`app/Domain/Documents`); eredità di F4 in `docs/dev/pr-f4.md`.

## Principi

- Tutto è **sviluppo vero**, con CI, test, audit e test di matrice come ogni altra fetta: nessun codice usa e getta.
- Le parti di F5, F6 e F7 sviluppate qui escono da quelle fette (che restano, ridotte): vedi "Cosa resta" in fondo.
- Il documento nasce **solo dopo l'approvazione** (D-090).
- Il configuratore di F4 resta com'è: le varianti sono proposte complete raccolte in un gruppo (D-396).
- **Controllo il 26/11/2026**: ciò che non è solido a quella data si presenta ridotto, non rotto (riduzioni previste nella colonna "Se in ritardo").

## PR

| PR | Contenuto | Decisioni | Dipende da | Se in ritardo |
|---|---|---|---|---|
| **DM-00** Decisioni e piano | Registro D-394…D-401, questo file, `pianificazione.md`, EV-26 in `features-evolutive.md`, `docs/README.md` | — | — | — |
| **DM-01** Varianti | Tabella `proposal_groups` (cliente, stagione, agente, impostazioni del documento); su `proposals` il gruppo e la lettera (A…E). Ogni proposta esistente diventa un gruppo di una variante (migrazione dei dati). "Aggiungi variante" nel configuratore (nuova bozza nello stesso gruppo, dal duplica di F4-05: stesso cliente e stagione, date proprie), passaggio tra le varianti, rimozione di una variante in bozza. Impostazione `proposal_max_variants` (5, da 1 a 10). "Le mie proposte" raggruppate. Cliente e stagione del gruppo uguali per tutte le varianti. Audit e cronologia | D-396 | F4 | Nessuna: è la base |
| **DM-02** Libreria slide minima | Tabelle `slide_sections` (dal seeder), `slides` (con `title`, D-402), `slide_conditions` (§5.3.5); pagina **Contenuti › Libreria slide** per admin e gestore (D-406): caricamento PNG 1920×1080 (`FilePurpose::SlideImage`), ambito Infront o club, stagione, tipo di documento, sezione, ordine, condizioni (livello, presenza di partnership, format, presenza di LED, hospitality); slide di sistema per copertina, recap, chiusura, simulazione LED. Servizio **`DocumentComposer`**: elenco ordinato delle slide di un gruppo (condizioni con la regola del network, D-118; ambito per competizione, D-251) | D-115, D-116, D-202, D-397, D-402…D-406 (tipo di documento del gruppo D-403, condizioni con le varianti D-404, slide di sistema D-405) | F4 | Condizioni solo su livello, format e hospitality |
| **DM-03** Invio e approvazione minima (due PR, D-407: **DM-03a** invio, coda, dettaglio, rifiuto; **DM-03b** approvazione, sconto, scelta del cliente) | "Invia in approvazione" del gruppo (riverifica con `DraftEvaluator`, RB-11); pagine **Proposte › Da approvare** e dettaglio del gruppo per admin e gestore (sola lettura delle varianti); **Approva** in un'unica transazione (lock delle proposte e delle capacità in ordine di id, residui, impegni di tutte le varianti, versioni congelate v1 con i KPI, cronologia, audit; fino a 3 ripetizioni sugli stalli); residuo insufficiente = esito per risorsa e approvazione bloccata; **Rifiuta** con motivazione facoltativa (la bozza torna modificabile e si reinvia); **Sconto** sul totale di ogni variante in % o in importo, inserito da chi approva (D-401); **Registra la scelta del cliente** (stato nuovo `not_chosen` per le altre varianti, rilascio con motivo nuovo); FK degli impegni verso le proposte (D-334); test di concorrenza | D-095, D-097, D-099, D-103, D-216, D-334, D-395, D-396, D-401, D-407…D-412 | DM-01 | Scelta del cliente senza pagina dedicata (pulsante nel dettaglio) |
| **DM-04** Documento PDF | Tabella `documents` (§5.3.5, `page_model`, `kpi_snapshot`); lavoro nella coda `documents` accodato dopo il commit dell'approvazione (idempotente); copertina (logo del cliente, data di approvazione, D-252); **recap network** (dalla S1, D-314) e **recap club** (riallineato all'originale, layout-dinamici §4.9) con un recap per variante intitolato "Proposta A / B / C"; slide statiche della libreria; documento club o network secondo D-396; derivati JPEG q85 (`stored_file_derivatives`, D-310); download dal dettaglio (agente sulle proprie, admin e gestore) con `StoredFilePolicy` per `generated_pdf` (D-205); rigenerazione dell'admin | D-090, D-111, D-120, D-121, D-204, D-205, D-252, D-310, D-314, D-396, D-413…D-417 | DM-02, DM-03 | Senza rigenerazione |
| **DM-05** Passo "Documento" | Passo 6 del configuratore (dopo il Riepilogo): elenco delle slide del gruppo da `DocumentComposer` per sezione, con miniature e spunte; copertina e recap obbligatori; "Documento completo" e "Solo riepilogo"; slide escluse salvate nel gruppo e congelate all'approvazione; "Invia in approvazione" in questo passo; chi approva cambia la scelta dal dettaglio | D-397 | DM-02, DM-03 | Miniature generiche per le slide dinamiche |
| **DM-06** Simulazione del logo sul LED | Tabella di configurazione per club e fila (e generica Infront): foto del campo (`FilePurpose` nuovo, ricodificata), area del LED con 4 punti, altezza del logo, spaziatura; pagina admin **Contenuti › Simulazioni LED** con l'anteprima; layout `led_mockup` nella pipeline (prospettiva in CSS, striscia del colore di sfondo del logo o nera, primo logo tagliato; 1ª e 2ª fila su una riga, Domination un logo grande su entrambe le file con la linea, Jumbo su entrambe le righe); slide inclusa in automatico per ogni club con il LED (non nel "Solo riepilogo"); anteprima nel passo "Documento"; regressione visiva | D-398 | DM-04 | Area del LED rettangolare (senza prospettiva) |
| **DM-07** Slide composta a blocchi | Contenuto JSON per gruppo (al massimo 3 slide): schema, blocchi (titolo, testo con grassetto ed elenchi, tabella, immagine, immagine con testo), posizione nel documento; editor nel passo "Documento" per l'agente e nel dettaglio per admin e gestore; riordino con il trascinamento; immagini in `stored_files` (`FilePurpose` nuovo); layout `custom_blocks` con `TextFitter` e avviso se il contenuto non entra; anteprima con la stessa resa del PDF; congelata all'approvazione; audit | D-399 | DM-04 | Riordino con frecce su/giù invece del trascinamento |
| **DM-08** Dati della demo | Comando solo `local` (`demo:prepare`): stagione 2027-28, Serie A e B con calendario fittizio, Lazio, Roma, Bologna, Udinese, Como con listini, capacità, slide di prova e configurazioni LED, agenti e clienti fittizi, un gruppo con tre varianti; caso dei totali "da inizio stagione / da gennaio"; istruzioni per la prova generale e l'istantanea della VM | D-400 | DM-01…DM-07 | Dati preparati a mano dalla checklist |

Ordine: DM-01 → DM-02 (in parallelo) → DM-03 → DM-04 → DM-05 → DM-06 → DM-07 → DM-08. DM-06 richiede le foto dei campi (cartella `CAMPI` di Antonio: foto degli stadi dei club e file `infront_` per i club senza foto e per i documenti network) entro il 20/11/2026.

## Calendario

| Date | Lavoro |
|---|---|
| 10–11/11 | DM-00 |
| 11–14/11 | DM-01 |
| 13–17/11 | DM-02 |
| 16–19/11 | DM-03 |
| 18–22/11 | DM-04 |
| 21–24/11 | DM-05 |
| 23–26/11 | DM-06 |
| **26/11** | Controllo dell'avanzamento |
| 25–29/11 | DM-07 |
| 29/11–1/12 | DM-08, prova generale, istantanea della VM |
| 1–3/12 | Presentazione PPTX con le catture vere e le note del relatore; blocco del codice |
| **4/12** | Demo |

## Domande aperte

- **Simulazione nei documenti network** (DM-06): con le foto generiche Infront, una slide per club con il LED o una per format presente nel gruppo. Da proporre nella PR.

## Cosa resta alle fette successive

- **F5**: forzatura, ritiro, modifica in approvazione, blocco di lavorazione, annullamento, versioni successive di un'approvata, e-mail delle proposte, `notification_logs.proposal_id`, spostamento delle proposte nell'unione dei clienti (D-359), pagina "Tutte le proposte".
- **F6**: KPI club e digital, avvisi di completezza e di aggiornamento, simulatore di composizione, layout dinamici dei KPI.
- **F7**: gli altri layout dinamici (club coinvolti, KPI network, KPI campagna, digital key figures, KPI club, digital club), e-mail dopo il documento (D-221), riduzione del tempo dei controlli, prova sullo staging.
- **F8**: report e dashboard tengono conto dei gruppi (varianti approvate, scelte, non scelte).

## Avanzamento

| PR | Esito |
|---|---|
| DM-00 | ✅ Unita il 10/11/2026 (PR #97; D-394…D-401) |
| DM-01 | ✅ Unita e collaudata l'11/11/2026 (PR #98) |
| DM-02 | ✅ Unita e collaudata l'11/11/2026 (decisioni D-402…D-406 con la PR #99, codice con la PR #100). Collaudo sulla VM: sei slide Infront di prova, recap network con documento "Entrambi" rifiutato, `documents:compose 205` → documento network Lazio + Roma con Copertina, Benefit Top, Simulazione LED (solo Roma, l'unico club con il LED), Recap (Proposta A), Grazie; Benefit Premium escluso; dopo la disattivazione di Benefit Top 4 slide e 4 pagine; variante B con due istanze del recap; agente senza accesso alla libreria; audit `slide.created` / `slide.updated`, tutto ✅ |
| DM-03a | ✅ Unita e collaudata il 12/11/2026 (PR #108; D-407…D-412 con la PR #107) |
| DM-03b | ✅ Unita e collaudata il 12/11/2026 (PR #109) |
| DM-04 | ✅ Unita e collaudata il 12/11/2026 (PR #111; D-413…D-417 con la PR #110; domande aperte chiuse con D-421, D-422) |
| DM-05 | ✅ Unita e collaudata il 12/11/2026 (decisioni D-418…D-422 con la PR #112, codice con la PR #113). Collaudo sulla VM: gruppo 211 A (Lazio, Roma) + B (Roma) per il cliente Prova; passo "Documento" con le slide per sezione e le miniature (4 derivati), esclusione di "Benefit Premium", "Solo riepilogo" e ritorno a "Documento completo" con l'esclusione conservata, scelta comune alle varianti, invio dal passo 6, "Grazie" tolta dall'admin in approvazione; PDF di 3 pagine (copertina, recap A e B) in "Solo riepilogo", rigenerazione uguale; audit `proposal.document_slides_updated` corretto, tutto ✅. ⚠️ Per DM-08: lo sfondo di prova del recap è scuro e rende illeggibili le righe dei club: servono sfondi chiari come nell'originale |

### Eredità di DM-02 per le PR successive

- **DM-03**: le versioni congelate all'approvazione devono portare anche la scelta delle slide del gruppo (D-397, con DM-05); `CompositionInput::fromGroup()` legge la versione di lavoro di ogni variante o, senza, l'ultima congelata (`currentVersion`).
- **DM-04**: `DocumentComposer::forGroup()` dà le slide e le pagine (`instances`: recap per variante, simulazione LED per club); `ComposedSlide::competitionTiers` restringe i dati delle slide network ai club di quella competizione (D-251); `DocumentLayout::view()` e `PdfPipeline::layoutData()` lanciano `notBuilt()` per `cover` e `club_recap` finché DM-04 non li disegna; il recap club si riallinea all'originale (layout-dinamici §4.9). Miniature leggere dai derivati JPEG (D-310).
- **DM-04 fatta** (PR DM-04): `DocumentBuilder` compone dalle versioni congelate delle varianti approvate (`CompositionInput::fromVersions()`), `RequestDocument` accoda `GenerateDocument` dopo il commit dell'approvazione, `DocumentGenerator` salva il PDF; `cover` e `club_recap` sono disegnati (`CoverLayout`, `RecapStyle::club()`), `led_mockup` resta `notBuilt()` fino a DM-06 e nel documento si salta (elencato in `page_model.skipped`). ⚠️ Rinviati: i derivati JPEG in `stored_file_derivatives` (la conversione resta nella cartella del lavoro, D-310) e i link al PDF negli elenchi delle proposte (il download sta nel dettaglio del gruppo).
- **DM-05**: il passo "Documento" mostra la `Composition` del gruppo (`mandatory`, `inSummary` per "Solo riepilogo"); `StoredFilePolicy` per `slide_image` va estesa all'agente sulle slide del proprio gruppo (D-406). Modalità e slide escluse sul gruppo (D-418, D-419), copiate sulle versioni congelate da `ApproveGroup`; `DocumentBuilder` le legge dalla versione congelata; miniature in `stored_file_derivatives` (D-420).
- **DM-06**: `led_mockup` (`DocumentLayout::LedMockup`) è già selezionabile in libreria, senza immagine; una pagina per club con il LED (D-405); resta la domanda sulla simulazione nei documenti network.


### Eredità di DM-05 per le PR successive

- **DM-05 fatta** (PR DM-05): passo 6 "Documento" del configuratore (`ProposalController::DOCUMENT_STEP`) con il componente `proposals/DocumentSlides`, usato anche nel dettaglio del gruppo da admin e gestore mentre è in approvazione; scelta salvata da `SaveDocumentSlides` sul gruppo e copiata da `ApproveGroup` sulle versioni congelate (D-418, D-419); `DocumentComposer` segna ogni slide con `included` e `DocumentBuilder` usa `includedSlides()`; miniature in `stored_file_derivatives` dalla rotta `files.thumbnail` (D-420).
- **DM-06**: la slide `led_mockup` nel passo "Documento" ha un segnaposto senza miniatura (`kind` = `led`); l'anteprima vera della simulazione può sostituirlo. In "Solo riepilogo" la simulazione non entra mai (D-398, già nel composer).
- **DM-07**: le slide composte a blocchi del gruppo vanno mostrate nello stesso elenco del passo "Documento" (D-399: l'agente le compone lì), con una spunta come le altre; la posizione nel documento e il congelamento seguono D-419 (contenuto JSON sulle versioni all'approvazione).
