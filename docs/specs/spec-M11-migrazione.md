# Specifica funzionale: M11 Migrazione — NON APPLICATA

| Campo | Valore |
|---|---|
| Codice | M11 |
| Stato | Approvata (D-154), **non applicata** (D-227) |
| Data | 2026-10-21 |

Il portale parte con la stagione **2027-28**, con disponibilità da zero e **senza migrare lo storico delle proposte** (D-227). L'archivio PDF dello storico è rinviato (D-228, EV-24). La specifica originale resta nel Project su claude.ai come riferimento storico e **non va sviluppata**.

## Cosa resta: script dei dati iniziali (D-229, D-230)

Script **una tantum**, eseguiti da Antonio in produzione durante la finestra di preparazione (D-231). Il dump del vecchio sistema **non entra nel repository** e non si usa in sviluppo né nei test.

| Script | Origine nel dump | Destinazione | Regole |
|---|---|---|---|
| Club | `idoc__teams_list` | `clubs`, `club_aliases`, `external_references` (platform `idoc`) | `deleted = 0` → club Infront; `deleted = 1` sono soprattutto **avversari**: si creano con `is_infront = false`, non disattivati. Duplicati da unire in un solo club: Frosinone (11/39), Atalanta (8/20), Roma (25/36), Bologna (21/35). `alias` → `club_aliases`. `main_color` "r,g,b" → `primary_color` esadecimale. `team_top` → `is_top_match_opponent` (vedi `docs/layout-dinamici.md` §5). Iscrizioni stagionali e stadi **non** si importano: si inseriscono a mano per il 2027-28 |
| Loghi dei club | Archivio interno di Antonio (D-230) | `stored_files` | Ricodificati al caricamento (D-218); mai file dal server compromesso |
| Settori merceologici | `idoc__commodity_sector` (24) | `commodity_sectors` | Correzione delle entità HTML (`ATTIVIT&Agrave;` → `ATTIVITÀ`); `ALTRO/VARIE` con testo libero obbligatorio |
| Utenti | `idoc__user_account` (35 abilitati) | `users` | Nome, cognome, e-mail, ruolo (`C` → agente; `S` → da decidere caso per caso con Antonio). **Nessuna password**: attivazione con link (D-209) |
| KPI iniziali | `idoc__audit_datas` (31) | `club_kpi_values` | Solo dopo verifica dei valori da parte di Antonio e Infront (D-206); solo club Infront |

Note dal dump (2026-10-10):
- I valori numerici sono **stringhe**: confrontare con `'0'`, non con `0`.
- Tabelle MyISAM e `utf8mb3`: leggere come UTF-8 e normalizzare le entità HTML in tutti i testi.
- I clienti **non** si importano: li ricreano gli agenti al primo uso (D-229).

Gli script stanno in `database/scripts/initial-data/` e producono un **report** (righe lette, create, unite, scartate) da far verificare ad Antonio prima della conferma. Si sviluppano con la fetta del modulo di destinazione: club in F1, settori in F3, KPI in F6, utenti in F9.
