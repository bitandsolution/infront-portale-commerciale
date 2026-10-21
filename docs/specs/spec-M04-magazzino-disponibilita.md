# Specifica funzionale: M04 Magazzino e disponibilità

| Campo | Valore |
|---|---|
| Codice | M04 |
| Nome | Magazzino e disponibilità |
| Versione | 1.0 (approvata, D-073) — agg. D-147 (hospitality max 6) |
| Data | 2026-10-10 |
| Decisioni di riferimento | D-027, D-041, D-046, D-048, D-051…D-053, D-056, D-062…D-072 |

Legenda: ✅ confermato (con riferimento) · 💡 proposta · ❓ da chiarire · ⚠️ rischio

> **Note di allineamento (repository, 2026-10-21)**
> - Stato corretto in "approvata" (D-073); nel Project l'intestazione riportava ancora "bozza".
> - **F-05** (copia delle capacità dalla stagione precedente): spostata nel **rilascio 1.1** (D-236).
> - **Nessuna migrazione** (D-227): l'eccezione "Proposte approvate dallo storico (M11)" del §10 e la dipendenza da M11 del §11 non si applicano. La stagione 2027-28 parte con disponibilità da zero.
> - Modello: capacità con flag "gestito" anche per partnership e hospitality (D-191); impegni immutabili con periodo (D-192); approvazione transazionale con lock delle capacità in ordine di id (D-216).

---

## 1. Descrizione
Il modulo gestisce le quantità vendibili per club e stagione:
- minuti LED per bacino;
- contratti di partnership per livello;
- posti hospitality per variante.

Le quantità si scalano quando una proposta viene approvata (✅ D-027). Il modulo fornisce i residui al configuratore (M06) e i controlli all'approvazione (M07).

## 2. Obiettivi
- Non vendere più di quanto disponibile, salvo forzatura consapevole e tracciata.
- Dare all'agente residui esatti e aggiornati mentre configura la proposta.
- Configurazione semplice e manuale da parte dell'admin.
- Poter passare in futuro alla disponibilità calcolata per partita senza perdere dati (✅ D-062).

## 3. Attori
| Attore | Ruolo nel modulo |
|---|---|
| Admin | Imposta capacità e flag; consulta impegni; approva, anche con forzatura (✅ D-064, D-070) |
| Gestore | Consulta disponibilità e impegni; approva proposte (✅ D-068); **non** può forzare (✅ D-070) |
| Agente | Vede i residui esatti nel configuratore (✅ D-065); nessuna modifica (✅ D-069) |

## 4. Risorse gestite

| Risorsa | Unità | Granularità | Scala con | Rif. |
|---|---|---|---|---|
| Bacino LED 1ª fila | Minuti a partita | Club × stagione | Format 1ª fila, Jumbo, Domination | ✅ D-051 |
| Bacino LED 2ª fila | Minuti a partita | Club × stagione | Format 2ª fila, Jumbo, Domination | ✅ D-051 |
| Bacini Corner bassi, Sotto tribuna | Minuti a partita | Club × stagione | Previsti, oggi disattivati | ✅ D-046 |
| Bacino Virtual | — | — | Previsto, oggi non usato | ✅ D-021 |
| Partnership | Numero contratti | Club × livello × stagione | Proposta approvata con quel livello; la Libera non scala | ✅ D-048, D-052 |
| Hospitality | Posti | Club × variante (Premium/Base) × stagione | Posti nella proposta (max 6 per club, D-147) | ✅ D-053, D-056 |

## 5. Funzionalità
| ID | Funzionalità | Stato |
|---|---|---|
| F-01 | Per club e stagione: flag di attivazione e capacità per ogni bacino LED | ✅ D-066 |
| F-02 | Per club, livello e stagione: numero di partnership vendibili | ✅ D-048, D-052 |
| F-03 | Per club, variante e stagione: posti hospitality disponibili | ✅ D-053 |
| F-04 | Configurazione solo manuale (nessun import Excel) | ✅ D-067 |
| F-05 | Copia delle capacità dalla stagione precedente | 💡 — **rilascio 1.1 (D-236)** |
| F-06 | Vista disponibilità per club: capacità · impegnato (approvate) · in attesa (da approvare) · residuo | 💡 |
| F-07 | Dettaglio impegni: elenco delle proposte che consumano una risorsa | 💡 |
| F-08 | Consumo automatico all'approvazione; ricalcolo alla modifica di una proposta approvata; rilascio all'annullamento (M07) | ✅ D-027, D-041; 💡 D-031 |
| F-09 | Servizio residui per il configuratore (minuti, posti, partnership per livello) | ✅ D-065, D-071 |
| F-10 | Forzatura in approvazione oltre la disponibilità, con motivazione e tracciamento; solo admin | ✅ D-064, D-070 |
| F-11 | Metodo di calcolo della disponibilità come impostazione di sistema: "stagionale" (attivo) / "per partita" (futuro) | ✅ D-062 |

## 6. Flussi utente
**FL-01 Configurazione di inizio stagione**
1. L'admin copia le capacità dalla stagione precedente (F-05), oppure le inserisce da zero.
2. Per ogni club attiva o disattiva i bacini e imposta minuti, numero di partnership e posti.
3. Da quel momento i residui sono visibili nel configuratore.

**FL-02 Configurazione della proposta (agente)**
1. Per ogni club il configuratore mostra solo i format i cui bacini sono attivi (D-066), con i minuti residui esatti.
2. L'agente sceglie i minuti in multipli di 30″. Il sistema non accetta valori oltre il residuo (D-063, D-064).
3. Allo stesso modo vede i posti hospitality residui e le partnership residue per livello (D-071). Le opzioni esaurite (residuo 0) restano visibili ma non selezionabili.

**FL-03 Approvazione (admin o gestore)**
1. Il sistema ricalcola la disponibilità al momento dell'approvazione.
2. Se ci sono altre proposte in attesa sulle stesse risorse, le mostra.
3. Se la disponibilità è sufficiente, l'approvazione scala gli impegni.
4. Se è insufficiente, l'approvazione è bloccata, salvo **forzatura** con motivazione obbligatoria, consentita solo all'admin (D-064, D-070). Il gestore vede il conflitto e non può approvare. La forzatura viene tracciata e il residuo può diventare negativo, evidenziato in rosso.

**FL-04 Modifica di una proposta approvata (admin)**
1. Il sistema calcola la differenza rispetto agli impegni esistenti e verifica solo quella.
2. Salva e notifica l'agente (D-041).

## 7. Dati richiesti (concettuali)
| Entità | Attributi |
|---|---|
| Capacità bacino | Club, stagione, bacino, attivo sì/no, minuti a partita |
| Capacità partnership | Club, stagione, livello, numero |
| Capacità hospitality | Club, stagione, variante, posti |
| Impegno | Proposta, club, risorsa, quantità, **data inizio e data fine contratto**, forzato sì/no, motivazione, utente, data |
| Impostazione | Metodo di calcolo disponibilità: stagionale / per partita |

⚠️ Il periodo di contratto sull'impegno è il dato che rende possibile il passaggio futuro al calcolo per partita (D-062). Va salvato fin da subito, anche se oggi non si usa.

## 8. Regole di business
| ID | Regola | Stato |
|---|---|---|
| RB-01 | Residuo = capacità − somma degli impegni delle proposte approvate (stesso club, risorsa, stagione) | ✅ D-062 |
| RB-02 | Jumbo e Domination impegnano gli stessi minuti sia sulla 1ª che sulla 2ª fila | ✅ D-051 |
| RB-03 | La partnership non impegna minuti LED; impegna 1 unità del livello scelto; la Libera non impegna nulla | ✅ D-051, D-052 |
| RB-04 | Hospitality: impegna i posti richiesti, massimo 6 per club in una proposta | ✅ D-147 |
| RB-05 | Minuti in multipli di 30″ (0,5′) | ✅ D-063 |
| RB-06 | Nel configuratore la richiesta non può superare il residuo | ✅ D-063, D-064 |
| RB-07 | In approvazione la verifica è vincolante; solo la forzatura tracciata permette di superarla | ✅ D-064 |
| RB-08 | Flag disattivo = bacino non gestito (format non vendibile per quel club); capacità 0 con flag attivo = esaurito | ✅ D-066 |
| RB-09 | Le proposte in attesa non impegnano disponibilità, ma sono visibili a chi approva | ✅ D-027; 💡 visibilità |
| RB-10 | Impostare una capacità inferiore al già impegnato genera un avviso non bloccante con le proposte coinvolte; il residuo negativo viene evidenziato | ✅ D-072 |
| RB-11 | Annullamento di una proposta approvata: rilascio degli impegni | 💡 D-031 (definizione in M07) |
| RB-12 | Con il metodo "stagionale" un impegno vale per tutta la stagione, qualunque sia il periodo di contratto | ✅ D-062 |

## 9. Validazioni
- Capacità: numeri ≥ 0. Minuti in multipli di 0,5; partnership e posti interi.
- Richiesta minuti nel configuratore: multipla di 0,5, > 0, ≤ residuo.
- Posti hospitality: interi, da 1 a 6 per club, ≤ residuo.
- Forzatura: motivazione obbligatoria (testo libero, minimo 10 caratteri 💡).

## 10. Eccezioni
| Caso | Gestione |
|---|---|
| Due proposte in attesa sugli ultimi minuti | Entrambe passano il configuratore; vale la prima approvata; la seconda in approvazione risulta in conflitto (forzatura o riduzione) |
| Contratto da gennaio con metodo stagionale | ⚠️ Impegna minuti per tutta la stagione (scelta consapevole, D-062); la forzatura copre i casi particolari |
| Capacità ridotta dal club in corso di stagione | Consentita con avviso; residuo negativo evidenziato (RB-10) |
| Residuo negativo dopo una forzatura | Evidenziato nella vista disponibilità; il configuratore mostra 0 all'agente |
| Proposte approvate dallo storico (M11) | ~~La migrazione deve generare i relativi impegni~~ — non applicabile: nessuna migrazione (D-227) |

## 11. Dipendenze
- **Dipende da:**
  - M02 (club, stagioni);
  - M03 (format e bacini consumati, livelli, varianti hospitality).
- **Usato da:**
  - M06 (residui);
  - M07 (verifica, consumo, rilascio);
  - M10 (tracciamento forzature);
  - ~~M11 (impegni dello storico)~~ — non applicabile (D-227).

## 12. Criteri di accettazione
1. Hellas Verona 1ª fila, capacità 30′: dopo l'approvazione di una proposta da 1′30″ il residuo è 28′30″.
2. Una proposta Domination da 1′ scala 1′ sia dalla 1ª che dalla 2ª fila dello stesso club.
3. Per la Lazio con 1ª fila disattivata il configuratore non mostra i format 1ª fila, Jumbo e Domination.
4. Con capacità attiva e valore 0, il format è visibile come esaurito e non selezionabile.
5. Nel configuratore non si possono inserire 1′15″ (non multiplo di 30″) né minuti oltre il residuo.
6. L'approvazione di una proposta Premium Lazio scala di 1 il contatore Premium Lazio; una proposta Libera non scala nulla.
7. Una proposta con 7 posti hospitality per lo stesso club non è salvabile.
8. Un'approvazione oltre la disponibilità è possibile solo all'admin, con forzatura e motivazione; la forzatura risulta nell'audit. Il gestore non vede l'opzione di forzatura.
9. Ogni impegno salvato riporta data di inizio e di fine del contratto.
10. Riducendo una capacità sotto il già impegnato compare un avviso, il salvataggio avviene e il residuo negativo è evidenziato.

## 13. Questioni aperte
- 💡 Passaggio futuro al metodo "per partita": valutazione rinviata, architettura predisposta (D-062).
