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
| **DM-02** Libreria slide minima | Tabelle `slide_sections` (dal seeder), `slides`, `slide_conditions` (§5.3.5); pagina admin **Contenuti › Libreria slide**: caricamento PNG 1920×1080 (`FilePurpose::SlideImage`), ambito Infront o club, stagione, tipo di documento, sezione, ordine, condizioni (livello, presenza di partnership, format, presenza di LED, hospitality); slide di sistema per copertina, recap, chiusura, simulazione LED. Servizio **`DocumentComposer`**: elenco ordinato delle slide di un gruppo (condizioni con la regola del network, D-118; ambito per competizione, D-251) | D-115, D-116, D-202, D-397 | F4 | Condizioni solo su livello, format e hospitality |
| **DM-03** Invio e approvazione minima | "Invia in approvazione" del gruppo (riverifica con `DraftEvaluator`, RB-11); pagine **Proposte › Da approvare** e dettaglio del gruppo per admin e gestore (sola lettura delle varianti); **Approva** in un'unica transazione (lock delle proposte e delle capacità in ordine di id, residui, impegni di tutte le varianti, versioni congelate v1 con i KPI, cronologia, audit; fino a 3 ripetizioni sugli stalli); residuo insufficiente = esito per risorsa e approvazione bloccata; **Rifiuta** con motivazione facoltativa (la bozza torna modificabile e si reinvia); **Sconto** sul totale di ogni variante in % o in importo, inserito da chi approva (D-401); **Registra la scelta del cliente** (stato nuovo `not_chosen` per le altre varianti, rilascio con motivo nuovo); FK degli impegni verso le proposte (D-334); test di concorrenza | D-095, D-097, D-099, D-103, D-216, D-334, D-395, D-396, D-401 | DM-01 | Scelta del cliente senza pagina dedicata (pulsante nel dettaglio) |
| **DM-04** Documento PDF | Tabella `documents` (§5.3.5, `page_model`, `kpi_snapshot`); lavoro nella coda `documents` accodato dopo il commit dell'approvazione (idempotente); copertina (logo del cliente, data di approvazione, D-252); **recap network** (dalla S1, D-314) e **recap club** (riallineato all'originale, layout-dinamici §4.9) con un recap per variante intitolato "Proposta A / B / C"; slide statiche della libreria; documento club o network secondo D-396; derivati JPEG q85 (`stored_file_derivatives`, D-310); download dal dettaglio (agente sulle proprie, admin e gestore) con `StoredFilePolicy` per `generated_pdf` (D-205); rigenerazione dell'admin | D-090, D-111, D-120, D-121, D-204, D-205, D-252, D-310, D-314, D-396 | DM-02, DM-03 | Senza rigenerazione |
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
| DM-01 | 🔄 PR aperta l'11/11/2026 |
