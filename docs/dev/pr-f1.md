# Fetta F1 — M02 Anagrafiche sportive: elenco delle PR

Stato: ✅ approvato (D-315…D-322, 25/10/2026). Avanzamento: F1-01 unita e collaudata (PR #31, D-323); F1-02 unita e collaudata (PR #33, D-324).
Periodo previsto: 26/11 – 9/12/2026 (D-243), avviata in anticipo. Riferimenti: pianificazione §6.1.3, `docs/specs/spec-M02-anagrafiche-sportive.md`, progettazione-tecnica §5.3.2.

| PR | Contenuto | Criteri e decisioni | Dipende da |
|---|---|---|---|
| **F1-01** Stagioni e competizioni ✅ (PR #31, unita il 25/10/2026, collaudata) | Tabelle `disciplines`, `seasons`, `competitions`, `competition_seasons`; enum `SeasonStatus`, `CompetitionType`. `SystemDataSeeder`: disciplina CALCIO, Serie A, Serie B, Serie C gironi A/B/C (`tier`, `has_top_match_kpi` solo sulla A). Pagine **Stagioni** e **Competizioni** (solo admin), edizioni con numero standard; stagioni non sovrapposte. **Selettore stagione** reale nella barra superiore (D-317). Area di audit `sports` | M02 F-01…F-03, §8; D-185, D-295, D-317, D-321 | — |
| **F1-02** Stadi e club ✅ (PR #33, unita, collaudata) | Tabelle `stadiums`, `clubs`, `club_aliases`, `external_references`. Pagina **Stadi** (admin e gestore); pagina **Club** (solo admin): dati, colori, stadio abituale, flag Infront e top match, logo con `FileStorage`, alias, disattivazione, nessuna cancellazione. `StoredFilePolicy` per `club_logo` (tutti i ruoli attivi) | F-05, F-07, F-11, RB-07, RB-08; M02-CA7 (parte senza proposte); D-188, D-230, D-289, D-321, D-322 | F1-01 |
| **F1-03** Iscrizioni e partite | Tabelle `club_enrollments`, `matchdays`, `matches`. Iscrizioni nell'edizione (solo admin; un solo campionato per club e stagione). Pagina **Calendari** (panoramica per edizione); pagina **Partite** (elenco filtrabile, inserimento, modifica, annullamento e ripristino; admin e gestore). Stagione `closed` in sola lettura | F-06, F-08, §8; D-185, D-186, D-317, D-319 | F1-02 |
| **F1-04** Import calendario | `openspout/openspout` (D-316), tabella `calendar_imports`. Caricamento, riconoscimento delle colonne D-017, anteprima persistente con errori e avvisi, associazione dei nomi come alias, conferma in transazione (inserimento e aggiornamento sulla chiave naturale), riepilogo; `calendar_loaded_at`; partite assenti dal file segnalate; stadio per nome (D-319). `StoredFilePolicy` per `calendar_import`. Pulizia notturna delle anteprime non confermate | F-09, F-10; M02-CA1, CA2, CA3, CA8; D-017, D-218, D-316, D-319, D-321, D-322 | F1-03 |
| **F1-05** Servizio partite | Servizio F-12: partite in casa di campionato nel periodo (RB-01…RB-03), numero standard riproporzionato con avviso (RB-05, D-187, D-320), top match (RB-06, D-253), inizio e mesi di campagna (RB-04, D-320). Comando in sola lettura `sports:matches` | M02-CA4, CA5, CA6; D-032, D-033, D-187, D-320 | F1-03 |
| **F1-06** Club dal vecchio sistema | Comando una tantum da CSV a colonne fisse (query in `docs/dev/`): club, alias, colori, riferimenti `idoc`, unione dei duplicati, flag top match di D-257, report delle anomalie | D-229, D-257, D-318 | F1-02 |

Note:
- F1-04 e F1-05 possono procedere in parallelo dopo F1-03; F1-06 dopo F1-02.
- Il collaudo di ogni PR si fa sulla VM locale di Antonio (`docs/dev/ambiente-locale.md`). Ogni PR che cambia l'avvio in locale aggiorna la guida.
- Il calendario Excel reale di Infront è atteso entro il 30/11/2026: fino ad allora il collaudo dell'import usa file fittizi nel tracciato D-017.
- M02-CA7 si completa in F4/F5, quando esistono le righe delle proposte (D-322).
