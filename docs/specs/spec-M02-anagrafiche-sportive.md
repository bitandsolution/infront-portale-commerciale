# Specifica funzionale: M02 Anagrafiche sportive

| Campo | Valore |
|---|---|
| Codice | M02 |
| Nome | Anagrafiche sportive (centralizzate) |
| Versione | 1.1 (approvata D-042; RB-05 completata da D-187) |
| Data | 2026-10-11 |
| Decisioni di riferimento | D-012, D-013, D-016, D-017, D-023, D-032…D-036, D-038…D-041, D-172, D-185…D-188 |

Legenda: ✅ confermato (con riferimento) · 💡 proposta · ❓ da chiarire · ⚠️ rischio

> **Note di allineamento (repository, 2026-10-21)**
> - **F-04** (nuova stagione dalla precedente): spostata nel **rilascio 1.1** (D-236). Nella 1.0 la stagione 2027-28 si crea da zero.
> - **F-13** (API in lettura per le altre piattaforme): spostata nel **rilascio 1.1** (D-237). Il modello (`external_references`, `api_clients`) resta nella 1.0.
> - Perimetro del gestore come da D-137 (vedi §3 e CA 8).
> - Modello dati in progettazione-tecnica §5.3.2 (D-185…D-188).
> - RB-06 (top match) confermata da D-253: conta l'ospite con il flag; la **Lazio** riceve il flag `is_top_match_opponent`. Verifica sul vecchio sistema in `docs/layout-dinamici.md` §5. Nei documenti network con condizione sulla competizione il conteggio considera solo i club di quella competizione (D-251).

---

## 1. Descrizione
Anagrafica sportiva unica e centralizzata per tutte le piattaforme Infront sullo stesso dominio. Contiene:
- discipline, competizioni, stagioni, club, stadi;
- calendari delle partite.

✅ La popola un solo backoffice; le piattaforme consumatrici la leggono (D-035; 💡 D-038 per il punto di scrittura unico):
- portale commerciale;
- gestionale accrediti, quando verrà revisionato.

## 2. Obiettivi
- Avere un'unica fonte di verità per club, stadi e calendari, ed eliminare i dati duplicati tra sistemi.
- Fornire al portale commerciale:
  - il numero di partite casalinghe nel periodo di contratto, per il calcolo dei prezzi LED;
  - i KPI di campagna (partite, mesi, top match).
- Rendere rapido l'aggiornamento a ogni nuova stagione e a ogni variazione di calendario.

## 3. Attori
| Attore | Ruolo nel modulo |
|---|---|
| Admin | Gestione completa (✅ D-040) |
| Gestore | Operatività: 💡 gestione calendari, import, partite, stadi; ❓ perimetro dettagliato in M01 |
| Agente commerciale | Nessun accesso diretto; consuma i dati tramite il configuratore (M06) |
| Piattaforme consumatrici | Lettura (portale commerciale, gestionale accrediti) |

## 4. Funzionalità

| ID | Funzionalità | Stato |
|---|---|---|
| F-01 | Gestione discipline (MVP: solo Calcio) | 💡 D-016 |
| F-02 | Gestione competizioni: nome, disciplina, girone (Serie C), numero standard di partite casalinghe, flag "KPI top match" | ✅ D-012, D-034, D-036 |
| F-03 | Gestione stagioni: etichetta (es. 2026-27), data di inizio e di fine | ✅ D-013 |
| F-04 | Creazione di una nuova stagione dalla precedente, con copia delle iscrizioni dei club | 💡 — **rilascio 1.1 (D-236)** |
| F-05 | Anagrafica club: nome, nome breve, logo, stadio abituale, flag "club Infront", flag "top match", alias, stato attivo/disattivo | 💡 |
| F-06 | Iscrizione stagionale: club × competizione × stagione (gestisce promozioni e retrocessioni) | 💡 |
| F-07 | Anagrafica stadi: nome, città, capienza | 💡 |
| F-08 | Calendario: inserimento, modifica ed elenco partite filtrabile per stagione, competizione, club, giornata | ✅ D-013 |
| F-09 | Import calendario da Excel con anteprima, errori e conferma | ✅ D-013, 💡 D-017 |
| F-10 | Re-import che aggiorna le partite esistenti (rinvii, anticipi, orari) | 💡 |
| F-11 | Gestione alias club per il riconoscimento in import | 💡 |
| F-12 | Servizio di calcolo partite: dato club, data di inizio e data di fine, restituisce le partite di campionato in casa | ✅ D-032, D-033, D-039 |
| F-13 | Esposizione dei dati in lettura alle altre piattaforme | ✅ D-035; API REST in sola lettura (D-172) — **rilascio 1.1 (D-237)** |

## 5. Flussi utente

**FL-01 Apertura nuova stagione**
1. Admin o gestore crea la stagione partendo da quella precedente (F-04).
2. Aggiorna le iscrizioni: inserisce le promosse, rimuove le retrocesse, sposta i club di Serie C tra i gironi.
3. Verifica il numero standard di partite per competizione.
4. Importa i calendari quando la Lega li pubblica. Fino ad allora il portale usa il numero standard.

**FL-02 Import calendario**
1. Selezione di stagione e competizione, caricamento del file.
2. Anteprima con righe valide e righe in errore: squadra non riconosciuta, data mancante, duplicato, squadra non iscritta.
3. Correzione: l'operatore associa i nomi sconosciuti a un club, e l'associazione si salva come alias.
4. Conferma: inserisce le partite nuove e aggiorna quelle esistenti, riconosciute da stagione, competizione, giornata, squadra di casa e squadra ospite.
5. Riepilogo finale: partite inserite, aggiornate, scartate.

**FL-03 Variazione di una partita (rinvio)**
1. Modifica manuale o re-import.
2. Nessun avviso verso le proposte (✅ D-041).
3. Le proposte in bozza ricalcolano le partite al primo accesso. Quelle approvate restano congelate.

## 6. Dati richiesti

**Tracciato di import calendario** (💡 D-017: standard comune a tutte le piattaforme, ✅ D-035)

| Colonna | Obbligatoria | Esempio | Note |
|---|---|---|---|
| Stagione | Sì* | 2026-27 | *Oppure scelta in fase di import |
| Competizione | Sì* | Serie A / Serie C – Girone A | *Oppure scelta in fase di import |
| Giornata | Sì | 7 | Intero |
| Data | Sì | 18/10/2026 | |
| Ora | No | 20:45 | Spesso definita a ridosso della partita |
| Squadra casa | Sì | UDINESE CALCIO | Nome o alias |
| Squadra ospite | Sì | INTER | Nome o alias |
| Stadio | No | Bluenergy Stadium | Se vuoto, stadio abituale del club di casa |

**Entità concettuali** (schema DB in `progettazione-tecnica.md` §5.3.2, ✅ D-185):
- Disciplina → Competizione → Stagione;
- Club, con alias e iscrizioni stagionali;
- Stadio;
- Partita (stagione, competizione, giornata, data, ora, casa, ospite, stadio).

## 7. Regole di business

| ID | Regola | Stato |
|---|---|---|
| RB-01 | Contano solo le partite di **campionato giocate in casa** (no Coppa Italia, playoff, playout) | ✅ D-033 |
| RB-02 | Per ogni club in contratto si contano le partite in casa con data ≥ data di inizio contratto e ≤ data di fine contratto | ✅ D-032, D-039 |
| RB-03 | La data di fine contratto è per default la fine stagione; è possibile impostarne una specifica | ✅ D-039 |
| RB-04 | La campagna inizia dalla prima partita in casa di uno qualsiasi dei club in contratto, a partire dalla data di inizio; da lì si calcolano i "mesi di campagna" | ✅ D-032 |
| RB-05 | Senza calendario caricato si usa il numero standard di partite della competizione. Se il periodo di contratto non copre l'intera stagione, il numero standard si riproporziona: **standard × giorni del periodo di contratto / giorni della stagione**, arrotondato all'intero più vicino (0,5 per eccesso), con avviso all'agente | ✅ D-034, D-187 |
| RB-06 | Top match = partita in cui l'ospite ha il flag "top match"; KPI calcolato solo per le competizioni con flag attivo (oggi solo Serie A) | ✅ D-023, D-036 |
| RB-07 | Solo i club con flag "club Infront" sono selezionabili nel configuratore; gli altri esistono come avversari. Il flag è sul club, non per stagione | 💡; ✅ D-188 |
| RB-08 | Un club, stadio o partita collegati a proposte non si cancellano: si disattivano | 💡 |
| RB-09 | Le proposte approvate non ricalcolano le partite in seguito a variazioni di calendario | ✅ D-041 |
| RB-10 | Le regole specifiche delle singole piattaforme (es. scadenza accrediti) non stanno nell'anagrafica centrale, ma nella piattaforma che le usa | 💡 |

## 8. Validazioni
- Import: squadra di casa ≠ squadra ospite; data valida e compresa tra inizio e fine stagione; giornata intera > 0; entrambe le squadre iscritte alla competizione nella stagione.
- Nessun duplicato con la stessa chiave stagione + competizione + giornata + casa + ospite.
- Un club non può giocare due partite nella stessa data e competizione: avviso, non blocco.
- Stagione: data di fine successiva alla data di inizio; nessuna sovrapposizione tra stagioni della stessa disciplina.
- Numero standard di partite > 0 per ogni competizione attiva.

## 9. Eccezioni
| Caso | Gestione |
|---|---|
| Partita in stadio diverso (lavori, campo neutro) | 💡 Conta comunque come partita in casa; ❓ impatto sui LED da valutare in M04 |
| Partita a porte chiuse o annullata | 💡 Stato partita "annullata" esclusa dal conteggio delle proposte in bozza |
| Stadi condivisi (Olimpico, Ferraris) | Nessun impatto: l'inventario è per club |
| Club in gironi diversi di Serie C | Gestito dall'iscrizione stagionale per competizione e girone |
| Rinvio fuori dal periodo di contratto | Per le approvate nessun ricalcolo (D-041); per le bozze ricalcolo automatico |
| File di import con colonne in ordine diverso o intestazioni diverse | 💡 Mappatura colonne in anteprima |

## 10. Dipendenze
- **Usato da:**
  - M03 (listini per club e stagione);
  - M04 (magazzino per club);
  - M06 (calcolo partite e prezzi);
  - M08 (loghi);
  - M09 (KPI di campagna);
  - gestionale accrediti (futuro).
- **Dipende da:** M01 per i ruoli admin e gestore.

## 11. Criteri di accettazione
1. Un file Excel standard di Serie A con 380 partite si importa con anteprima e senza errori; le partite risultano consultabili per club.
2. Un nome di squadra sconosciuto in import si associa a un club; il re-import successivo lo riconosce in automatico.
3. Un re-import con la data di una partita modificata aggiorna la partita esistente senza crearne una nuova.
4. Con data di inizio 01/10/2026 e fine stagione, il servizio di calcolo (F-12) restituisce esattamente le partite di campionato in casa del club da quella data in poi.
5. Senza calendario caricato, il calcolo restituisce il numero standard della competizione; con un periodo di contratto pari a metà stagione restituisce metà del numero standard, arrotondata (D-187).
6. Il conteggio top match restituisce un valore solo per la Serie A.
7. Un club con proposte collegate non si può cancellare, solo disattivare.
8. Il gestore può importare un calendario; le operazioni riservate all'admin gli sono negate (perimetro da M01).

## 12. Questioni aperte
- ~~❓ Perimetro esatto del ruolo Gestore~~ → D-137 (M01).
- ~~❓ Chi può impostare una data di fine contratto diversa dalla fine stagione~~ → l'agente, verificata dall'admin (D-092).
- ~~❓ RB-05: riproporzionare il numero standard per contratti a stagione iniziata senza calendario~~ → risolto da D-187.
- ~~❓ Modalità tecnica di condivisione con le altre piattaforme~~ → API REST in sola lettura (D-172); id storici degli accrediti tramite `external_references` (D-186).
- ❓ Requisiti specifici del gestionale accrediti da verificare in sede di revisione di quel software.
