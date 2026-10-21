# Specifica funzionale: M10 Audit e reportistica

| Campo | Valore |
|---|---|
| Codice | M10 |
| Nome | Audit e reportistica |
| Versione | 1.0 (approvata, D-134) |
| Data | 2026-10-10 |
| Decisioni di riferimento | D-064, D-072, D-096, D-125, D-127…D-134 |

Legenda: ✅ confermato (con riferimento) · 💡 proposta · ❓ da chiarire · ⚠️ rischio

> **Note di allineamento (repository, 2026-10-21)**
> - Audit append-only garantito dal database: utente applicativo senza UPDATE e DELETE su `audit_events`, trigger, cancellazione dopo 5 anni con un secondo utente MariaDB (D-210, requisito R7). Conservazione dell'IP configurabile separatamente (D-211).
> - L'infrastruttura di audit si sviluppa nella fetta F0; consultazione e report nella fetta F8 (D-234).
> - Checkpoint del 12/3/2027 (D-247): in caso di ritardo, R-01…R-05 e R-07 possono passare a un rilascio 1.0.1; R-06 e l'audit restano nel 1.0.
> - **Nessuna migrazione** (D-227): la voce "M11" tra gli utilizzatori al §9 non si applica.

---

## 1. Descrizione
Il modulo ha due componenti:
- un **audit trasversale**, non modificabile, di ogni operazione rilevante: dati, proposte, documenti, accessi;
- una serie di **report** sull'andamento commerciale e sull'occupazione del magazzino.

## 2. Obiettivi
- Tracciabilità completa: chi ha fatto cosa, quando, con quale valore prima e dopo.
- Sicurezza: controllo degli accessi, compresi i tentativi falliti.
- Visione dell'andamento commerciale e delle disponibilità residue, senza estrazioni manuali.

## 3. Attori
| Attore | Audit | Report |
|---|---|---|
| Admin | ✅ Consultazione completa (D-129) | ✅ Tutti |
| Gestore | ❌ (D-129) | ✅ R-01…R-05 (D-134) |
| Agente | ❌ | Solo il proprio cruscotto |

## 4. Audit (✅ D-127)

### 4.1 Eventi tracciati
| Area | Eventi |
|---|---|
| Accessi | Login riuscito, login fallito, logout, blocco account (✅ D-128) |
| Proposte | Creazione, modifica bozza, invio, ritiro, modifica in approvazione, sconto, approvazione, **forzatura** con motivazione (D-064), rifiuto, nuova versione (D-096), annullamento, duplicazione |
| Documenti | Generazione, rigenerazione (D-125), download |
| Catalogo e listini | Variazioni di prezzi, livelli, format, benefit, varianti hospitality; pubblicazione listino |
| Magazzino | Variazioni di capacità e flag, incluso l'avviso di residuo negativo (D-072) |
| Anagrafiche sportive | Club, stagioni, competizioni, partite, import calendari (esito) |
| Clienti | Creazione, modifica, assegnazione/riassegnazione, disattivazione |
| Contenuti | Caricamento, sostituzione, eliminazione di slide; modifica delle condizioni; variazione KPI |
| Utenti | Creazione, modifica ruolo, disattivazione, reset password |

### 4.2 Dati registrati per ogni evento
Data e ora · utente · ruolo · area · oggetto (tipo e identificativo, es. proposta n. 192) · azione · valore precedente → valore nuovo (per le modifiche) · motivazione (se prevista) · 💡 indirizzo IP (solo per gli accessi).

### 4.3 Regole
| ID | Regola | Stato |
|---|---|---|
| RB-A1 | L'audit non è modificabile né cancellabile da nessun utente | 💡 |
| RB-A2 | Conservazione: 5 anni, poi cancellazione automatica | ✅ D-128 |
| RB-A3 | Consultazione solo admin, con filtri per periodo, utente, area, azione, oggetto | ✅ D-129 |
| RB-A4 | Dalla scheda di una proposta l'admin vede la sua cronologia completa | 💡 |
| RB-A5 | ⚠️ Gli accessi contengono dati personali (utente, IP): finalità di sicurezza da indicare nell'informativa privacy | 💡 |

## 5. Reportistica (✅ D-130)

| Report | Contenuto | Visibilità |
|---|---|---|
| R-01 Dashboard proposte | Conteggio per stato; valore di listino e finale delle approvate; sconto medio | Admin, Gestore |
| R-02 Proposte per agente | Numero, valore, tasso di approvazione, proposte in attesa | Admin, Gestore |
| R-03 Proposte per club | Valore approvato, partnership per livello, minuti LED, posti hospitality | Admin, Gestore |
| R-04 Occupazione magazzino | Per club e stagione: capacità, impegnato, residuo per bacino, partnership e hospitality; **evidenza dei residui negativi e delle forzature** | Admin, Gestore |
| R-05 Clienti per settore | Proposte e valore per settore merceologico | Admin, Gestore |
| R-06 Cruscotto agente | Le proprie proposte per stato e valore | Agente |
| R-07 Accessi | Login per utente e periodo, tentativi falliti, utenti inattivi | Admin |

**Filtri comuni:** stagione, periodo (data di approvazione o di creazione), club, agente, stato.

**Regole dei report**
| ID | Regola | Stato |
|---|---|---|
| RB-R1 | Il valore si attribuisce alla **stagione** della proposta; in alternativa si filtra per data di approvazione | 💡 |
| RB-R2 | I valori usano i prezzi congelati dell'ultima versione approvata | ✅ D-041, D-096 |
| RB-R3 | Le proposte annullate sono escluse dal venduto ma visibili con un filtro dedicato | 💡 |
| RB-R4 | Nessun export Excel nell'MVP | ✅ D-131 |
| RB-R5 | Obiettivi e budget per agente o club: non gestiti, ma il modello dati li prevede | ✅ D-132 |

## 6. Flussi utente
**FL-01 Verifica di una forzatura:** l'admin filtra l'audit per "forzatura" nella stagione, apre la proposta e ne vede motivazione, utente e residuo risultante.

**FL-02 Controllo accessi:** l'admin apre R-07 e vede tentativi falliti ripetuti su un utente.

**FL-03 Andamento stagionale:** l'admin apre R-03 e R-04 per la stagione e vede il venduto e il residuo per club.

## 7. Validazioni
- Filtri di periodo: data di fine ≥ data di inizio.
- Report su intervalli molto ampi: 💡 paginazione e limiti di tempo di risposta.

## 8. Eccezioni
| Caso | Gestione |
|---|---|
| Utente disattivato | I suoi eventi restano nell'audit con il nome dell'utente |
| Oggetto eliminato (es. bozza) | L'evento resta con identificativo e descrizione |
| Volumi elevati dopo anni | 💡 Archiviazione degli eventi più vecchi e conservazione per 5 anni |

## 9. Dipendenze
- **Dipende da:** tutti i moduli (eventi), M01 (utenti, ruoli, accessi), M04 (residui), M07 (stati, versioni).

## 10. Criteri di accettazione
1. Ogni azione elencata al §4.1 genera un evento con utente, data, oggetto e, per le modifiche, valore prima e dopo.
2. Un login fallito viene registrato con data, utente tentato e IP.
3. Nessun utente, nemmeno l'admin, può modificare o cancellare un evento.
4. Il gestore e l'agente non vedono l'audit.
5. R-04 mostra per Hellas Verona capacità, impegnato e residuo per bacino, ed evidenzia i residui negativi.
6. L'agente vede in R-06 solo le proprie proposte.
7. Gli eventi più vecchi di 5 anni vengono eliminati in automatico.

## 11. Questioni aperte
- 💡 RB-R1: attribuzione del valore per stagione, con filtro alternativo per data di approvazione.
