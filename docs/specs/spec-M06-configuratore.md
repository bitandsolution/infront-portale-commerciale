# Specifica funzionale: M06 Configuratore proposta

| Campo | Valore |
|---|---|
| Codice | M06 |
| Nome | Configuratore proposta |
| Versione | 1.0 (approvata, D-094) — agg. D-147 (hospitality max 6) |
| Data | 2026-10-10 |
| Decisioni di riferimento | D-011, D-029, D-032…D-034, D-039, D-045, D-051…D-056, D-063…D-066, D-069, D-071, D-085…D-093 |

Legenda: ✅ confermato (con riferimento) · 💡 proposta · ❓ da chiarire · ⚠️ rischio

> **Note di allineamento (repository, 2026-10-21)**
> - Stato corretto in "approvata" (D-094); nel Project l'intestazione riportava ancora "bozza".
> - Senza calendario il numero standard si riproporziona sul periodo (D-187, RB-05 di M02).
> - Salvataggio automatico delle bozze con `lock_version` (D-165, D-216); tablet-first (D-157); mockup approvati (D-162).
> - **Nessuna migrazione** (D-227): la voce "M11" tra gli utilizzatori al §11 non si applica.

---

## 1. Descrizione
Percorso guidato con cui l'agente costruisce una proposta commerciale per un cliente, su uno o più club. Il configuratore:
- mostra solo opzioni vendibili e solo prezzi di listino;
- non permette di superare le disponibilità;
- calcola prezzi e KPI di campagna in tempo reale;
- salva la proposta in bozza o la invia in approvazione (M07).

## 2. Obiettivi
- Proposte corrette per costruzione: niente opzioni inesistenti, esaurite o fuori listino.
- Tempi di compilazione ridotti: copia su altri club, duplica proposta, campi superflui nascosti.
- Prezzi trasparenti per club e totali, coerenti con il futuro documento.

## 3. Attori
| Attore | Ruolo |
|---|---|
| Agente | Crea, modifica, salva in bozza, duplica e invia le proprie proposte (✅ D-069) |
| Admin / Gestore | 💡 Consultazione in sola lettura; le modifiche passano da M07 |

## 4. Percorso (✅ D-089)

| Step | Contenuto | Riferimenti |
|---|---|---|
| 1. Cliente | Ricerca tra i propri clienti, oppure creazione al volo | M05 |
| 2. Stagione e periodo | Stagione; data di inizio; data di fine (default fine stagione, modificabile dall'agente) | ✅ D-032, D-039, D-087, D-092 |
| 3. Club | Selezione multipla tra i club selezionabili | 💡 D-085 |
| 4. Configurazione per club | Una sezione per club, vedi §5 | M03, M04 |
| 5. Riepilogo | Dettaglio per club, KPI di campagna, totale di listino, avvisi | ✅ D-011 |
| 6. Salva / Invia | Salva bozza oppure Invia in approvazione | → M07 |

L'agente può tornare a uno step precedente. Se cambia stagione o date, il sistema ricalcola partite, prezzi e residui. Se cambia stagione, le configurazioni dei club non più valide vengono segnalate.

## 5. Configurazione per club (step 4)

| Campo | Comportamento | Rif. |
|---|---|---|
| Tipo pacchetto | "Libera" oppure i livelli disponibili per il club, ciascuno con le **partnership residue**; i livelli esauriti sono visibili ma non selezionabili | ✅ D-052, D-071 |
| Format LED | **Uno solo per club** (✅ D-088), tra quelli con bacini attivi (D-066) e prezzo a listino; mostra i **minuti residui**; facoltativo se c'è una partnership | ✅ D-051, D-065 |
| Minuti a partita | Multipli di 30″; > 0; ≤ residuo | ✅ D-063, D-064 |
| Hospitality Premium / Base | Posti; massimo 6 in totale per club (D-147); ≤ residuo; facoltativa (se vuota, non prevista e costo 0) | ✅ D-055, D-056 |
| Territorio | Nascosto finché solo "Italia" è attivo | ✅ D-030; 💡 D-085 |
| Tipo visibilità | Nascosto finché solo "Classica" è attiva | ✅ D-021; 💡 D-085 |
| Prezzo del club | Partnership + LED + hospitality, aggiornato a ogni modifica | §7 |

**Funzioni di supporto**
- **Copia su altri club**: copia pacchetto, format, minuti e posti sugli altri club della proposta. Le opzioni non disponibili o eccedenti il residuo vengono saltate e segnalate club per club.
- **Duplica proposta** (✅ D-091): crea una nuova bozza da una proposta esistente dell'agente, anche per un altro cliente. Date, listino e residui vengono ricalcolati sui valori correnti.
- **Rimuovi club**: elimina il club dalla proposta.

## 6. Riepilogo (step 5)
- Tabella per club: pacchetto, format, minuti a partita, partite nel periodo, spot a partita (minuti × 4), hospitality, prezzo.
- ✅ KPI di campagna (D-093): numero club, partite totali, spot totali, mesi di campagna, top match (solo Serie A, ✅ D-036). Sono gli stessi dati del documento.
- Totale di listino. **Nessun campo sconto** per l'agente (💡 D-086, ✅ D-011).
- Avvisi: calendario non caricato (numero standard), listino o residui cambiati dall'ultima apertura.

## 7. Regole di business
| ID | Regola | Stato |
|---|---|---|
| RB-01 | Date di inizio e fine uniche per tutta la proposta | ✅ D-087 |
| RB-02 | La campagna inizia dalla prima giornata ≥ data di inizio in cui uno dei club scelti gioca in casa; per ogni club si contano le sue partite di campionato in casa tra data di inizio e data di fine | ✅ D-032, D-033, D-087 |
| RB-03 | Senza calendario caricato si usa il numero standard di partite della competizione, con avviso | ✅ D-034 |
| RB-04 | Prezzo LED = costo per 1′ a partita × minuti a partita × partite nel periodo | ✅ D-054 |
| RB-05 | Prezzo partnership = prezzo fisso stagionale, anche a stagione iniziata | ✅ D-029 |
| RB-06 | Prezzo hospitality = prezzo a posto stagionale × posti, pieno anche a stagione iniziata | ✅ D-055, D-058 |
| RB-07 | Totale proposta = somma dei prezzi dei club, solo prezzi di listino | ✅ D-011 |
| RB-08 | Ogni club deve avere almeno un elemento (partnership non Libera, LED o hospitality) | 💡 |
| RB-09 | Selezionabili solo i club Infront attivi con listino pubblicato nella stagione | 💡 D-085 |
| RB-10 | L'agente non può superare i residui; le bozze non impegnano disponibilità | ✅ D-027, D-064 |
| RB-11 | All'invio in approvazione si riverificano listino e residui | 💡 |
| RB-12 | Una bozza riaperta si ricalcola su listino e residui correnti, con avviso delle differenze | ✅ D-045 |
| RB-13 | Il documento non si genera da una bozza né da una proposta in attesa, solo dopo l'approvazione | ✅ D-090 |
| RB-14 | L'agente vede e modifica solo le proprie proposte | 💡 coerente con D-076 |

## 8. Validazioni
- Cliente obbligatorio.
- Stagione obbligatoria; data di inizio obbligatoria e compresa nella stagione; data di fine ≥ data di inizio e ≤ fine stagione.
- Almeno un club.
- Per ogni club: almeno un elemento (RB-08); minuti in multipli di 0,5 e ≤ residuo; posti hospitality ≤ 6 in totale e ≤ residuo.
- Un club con 0 partite nel periodo e LED selezionato: **errore** ("nessuna partita in casa nel periodo").

## 9. Eccezioni
| Caso | Gestione |
|---|---|
| Residuo esaurito tra il salvataggio della bozza e l'invio | All'invio il sistema blocca e indica il club e la risorsa da correggere |
| Listino modificato dopo la creazione della bozza | Ricalcolo con avviso (RB-12) |
| Club che non ha più un listino pubblicato | Club segnalato in errore nella bozza; l'agente lo rimuove |
| Combinazione di due format sullo stesso club | Non ammessa; serve una nuova proposta (D-088) |
| Proposta per la stagione successiva senza calendario | Numero standard di partite e avviso (RB-03) |
| Duplica di una proposta con club non più disponibili | La copia segnala i club non validi |

## 10. Dati della proposta (concettuali)
- **Testata:** codice proposta, agente, cliente, stagione, data di inizio, data di fine, stato (→ M07), date di creazione e modifica.
- **Righe per club:** club, pacchetto, format LED, minuti a partita, partite nel periodo, posti Premium, posti Base, prezzi unitari applicati, prezzo del club.
- **Totali:** totale di listino (lo sconto e il totale finale sono gestiti in M07).

## 11. Dipendenze
- **Dipende da:**
  - M02 (club, calendario, conteggio partite);
  - M03 (opzioni e prezzi);
  - M04 (residui);
  - M05 (clienti);
  - M01 (utenti).
- **Usato da:**
  - M07 (invio, approvazione, stati);
  - M09 (dati del documento);
  - M10 (statistiche).

## 12. Criteri di accettazione
1. Il configuratore mostra solo i club Infront con listino pubblicato nella stagione scelta.
2. Per la Lazio con 1ª fila disattivata non compaiono i format 1ª fila, Jumbo e Domination.
3. Proposta Bologna + Como dal 01/10/2026 a fine stagione: le partite per club corrispondono alle partite di campionato in casa nel calendario dopo quella data.
4. 1′30″ di 1ª fila Udinese a costo C per N partite: prezzo LED = C × 1,5 × N.
5. Non è possibile inserire 1′15″, né minuti oltre il residuo, né 7 posti hospitality per lo stesso club.
6. Il campo sconto non è visibile all'agente.
7. "Copia su altri club" applica la configurazione ai club compatibili e segnala quelli in cui un'opzione non è disponibile.
8. "Duplica proposta" crea una nuova bozza con prezzi e residui ricalcolati.
9. Da una bozza o da una proposta in attesa non è possibile generare il documento.
10. Un club senza alcun elemento configurato blocca il salvataggio con messaggio.

## 13. Questioni aperte
Nessuna. D-085 e D-086 si considerano approvate con la specifica.
