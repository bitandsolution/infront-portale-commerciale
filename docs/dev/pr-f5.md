# Fetta F5 — M07 Ciclo di vita e notifiche: elenco delle PR

Stato: ✅ approvato (D-443…D-448, 13/11/2026). Sviluppo dal 16/11/2026, in anticipo sul calendario (F5 era prevista dal 4/2/2027), con la demo del 4/12/2026 fissata all'etichetta `demo-2026-12-04`: la VM della demo **non** si aggiorna con le PR di F5; blocco del codice 1–3/12, il resto riprende dopo la demo.
Riferimenti: `docs/specs/spec-M07-ciclo-vita-approvazione.md`, M01 §7 (e-mail), M04 (impegni), progettazione-tecnica §5.3.4 e §5.4.4, `docs/dev/pr-demo.md` (parte di F5 già fatta nella fetta DM: invio, coda "Da approvare", dettaglio, approvazione con sconto e impegni, rifiuto e reinvio, scelta del cliente, "Tutte le proposte", FK degli impegni; D-395, D-407…D-412).

| PR | Contenuto | Criteri e decisioni | Dipende da |
|---|---|---|---|
| **F5-00** Piano e decisioni | Registro D-443…D-448, questo file, `pianificazione.md` | — | — |
| **F5-01** Ritiro e blocco di lavorazione | **Blocco di lavorazione** sul gruppo in approvazione (`review_locked_by`, `review_locked_at` delle varianti): scatta all'apertura del dettaglio da parte di admin o gestore, si rinnova con l'attività, si libera all'esito o dopo 30 minuti; gli altri vedono "In lavorazione da …" in sola lettura (sconto, approva, rifiuta e slide del documento disattivati); l'admin subentra con una conferma. **Ritiro** del gruppo dall'agente: tutte le varianti tornano in Bozza (cronologia `withdrawn`), rifiutato se il gruppo è bloccato in lavorazione. Audit e test di matrice delle rotte nuove | D-100, D-216, D-444, D-446; M07 F-09, §9 | — |
| **F5-02** Modifica in approvazione | Admin e gestore modificano una variante in approvazione con il **configuratore in modalità revisione** (cliente e stagione fissi; date, club, configurazione e riepilogo; stessi controlli di M06 §8, salvataggio automatico con `lock_version`), solo se tengono il blocco di lavorazione; cronologia `edited_in_review`, audit; ritorno al dettaglio del gruppo | D-098, D-445; M07 F-03, §8 | F5-01 |
| **F5-03** Forzatura | Nel dettaglio, con un'approvazione bloccata dai residui (D-409), solo l'**admin** vede "Forza": motivazione obbligatoria (`forced_reason_min_length`); gli impegni oltre il residuo si salvano con `is_forced` e `forced_reason`; cronologia `forced`, audit; il gestore non vede l'opzione e la rotta lo rifiuta. Forzabile anche la differenza di una nuova versione (F5-04) | D-064, D-070; M07-CA4, M04-CA8 | F5-01 |
| **F5-04** Versioni di un'approvata | L'admin modifica una variante approvata con il configuratore in modalità revisione (sconto compreso): nasce la versione di lavoro dalla versione congelata corrente; al salvataggio finale si crea la **versione n+1** congelata, la disponibilità si controlla **solo sulla differenza** (gli impegni della versione precedente si rilasciano come `superseded` e quelli nuovi si scrivono nella stessa transazione), cronologia `new_version`, audit; **elenco delle versioni** nel dettaglio (autore, data, differenze principali); il documento si rigenera con le sole varianti valide | D-041, D-096, D-198, D-216, D-445, D-448; M07-CA7, M07 F-06 | F5-02, F5-03 |
| **F5-05** Annullamento | L'admin annulla il gruppo approvato con motivazione obbligatoria: tutte le varianti approvate diventano "Annullata", impegni rilasciati con il motivo `cancelled`, cronologia `cancelled`, audit; il documento resta scaricabile con la dicitura "Annullata" nel portale (PDF invariato) | D-095, D-444, D-447; M07-CA8 | F5-01 |
| **F5-06** E-mail delle proposte | Cinque e-mail `PortalMail` (D-299, D-300): invio in approvazione ad admin e gestori attivi; approvazione all'agente **quando il documento è pronto**, con il link alla pagina (mai allegati), o senza link con l'avviso all'admin se la generazione fallisce (D-221); rifiuto con la motivazione; nuova versione; annullamento. Tipi nuovi in `NotificationType`, colonna `notification_logs.proposal_id` (variante A del gruppo) | D-102, D-105, D-221, D-299; M07-CA5, M01-CA6 | — (le e-mail di nuova versione e annullamento si collegano quando ci sono F5-04 e F5-05) |
| **F5-07** Unione dei clienti con le proposte | `MergeClients` sposta sul cliente che resta i gruppi e le proposte del duplicato (l'agente proprietario non cambia), nella stessa transazione, con l'audit dei gruppi spostati; le versioni congelate restano invariate | D-359 | — |

Note:
- Ogni nuova rotta entra nel test di matrice (D-215); ogni scrittura registra il suo evento di audit nell'area `proposals`.
- Approvazione, forzatura, nuova versione e annullamento toccano gli impegni: lock in ordine di id (D-216) e test di concorrenza in `tests/Concurrency`.
- Le versioni congelate restano immutabili (D-198): la versione n+1 è una riga nuova; la n resta consultabile.
- Il configuratore in modalità revisione riusa `SaveDraft`, `DraftEvaluator` e `DraftRowsWriter`: nessun secondo calcolo di prezzi o residui (CLAUDE.md §4).
- Collaudo sulla VM: le PR di F5 si collaudano su un'istantanea **diversa** da "demo 4/12" (o dopo la demo).

Fuori da F5:
- **F6**: KPI club e digital, avvisi di completezza e aggiornamento, simulatore di composizione.
- **F7**: gli altri layout dinamici, riduzione del tempo dei controlli, prova sullo staging.
- **F8**: report e dashboard con i gruppi (varianti approvate, scelte, non scelte, annullate), consultazione dell'audit.

## Avanzamento

| PR | Esito |
|---|---|
| F5-00 | In revisione |
