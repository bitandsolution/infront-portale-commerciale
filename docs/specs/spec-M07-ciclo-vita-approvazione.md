# Specifica funzionale: M07 Ciclo di vita e approvazione

| Campo | Valore |
|---|---|
| Codice | M07 |
| Nome | Ciclo di vita e approvazione della proposta |
| Versione | 1.0 (approvata, D-106) |
| Data | 2026-10-10 |
| Decisioni di riferimento | D-011, D-014, D-027, D-041, D-062, D-064, D-068, D-070, D-090, D-095…D-105 |

Legenda: ✅ confermato (con riferimento) · 💡 proposta · ❓ da chiarire · ⚠️ rischio

> **Note di allineamento (repository, 2026-10-21)**
> - D-096 (versioni delle approvate) è APPROVATA (D-183).
> - Modello: versione di lavoro + versioni congelate immutabili (codice + trigger), cronologia in `proposal_events` (D-198, D-199).
> - Approvazione in un'unica transazione con lock, fino a 3 ripetizioni in caso di stallo; blocco di lavorazione di 30 minuti; `lock_version` (D-216).
> - L'e-mail di approvazione all'agente parte quando il documento è pronto; se la generazione fallisce, parte senza link e l'admin viene avvisato (D-221).
> - §11: "prezzi scontati per club" corretto in "prezzi di listino per club e sconto sul totale", coerente con D-103 (D-101 è stata rifiutata).
> - **Nessuna migrazione** (D-227): la voce "M11" tra gli utilizzatori al §11 non si applica. Lo stato "Storico" (D-142) non si usa nella 1.0.

---

## 1. Descrizione
Il modulo governa la proposta dall'invio dell'agente all'esito finale:
- controllo di disponibilità e prezzo;
- sconto;
- approvazione, che consuma disponibilità e congela prezzi e partite;
- rifiuto;
- modifiche successive con versioni;
- annullamento;
- notifiche.

## 2. Obiettivi
- Nessuna proposta approvata senza controllo di disponibilità, salvo forzatura tracciata dell'admin.
- Prezzi definitivi stabili dopo l'approvazione, con versioni tracciate per ogni modifica successiva.
- Esito comunicato tempestivamente all'agente.

## 3. Attori e permessi

| Azione | Agente | Gestore | Admin |
|---|---|---|---|
| Inviare in approvazione | ✅ | — | — |
| Ritirare una proposta in attesa | ✅ D-100 | — | — |
| Correggere e reinviare una rifiutata | ✅ D-099 | — | — |
| Modificare in approvazione (configurazione, quantità) | — | ✅ D-098 | ✅ |
| Applicare lo sconto | — | ✅ D-098 | ✅ D-011 |
| Approvare / Rifiutare | — | ✅ D-068 | ✅ |
| Forzare oltre la disponibilità | — | ❌ D-070 | ✅ D-064 |
| Modificare una proposta approvata | — | ❌ | ✅ D-041 |
| Annullare una proposta approvata | — | ❌ | ✅ D-095 |

## 4. Stati e transizioni (✅ D-095)

| Stato | Impegna disponibilità | Chi può modificare | Documento |
|---|---|---|---|
| Bozza | No | Agente | No |
| In approvazione | No | Gestore, Admin | No |
| Rifiutata | No | Agente (per reinviarla) | No |
| Approvata | **Sì** | Admin (nuova versione) | **Sì** (✅ D-090) |
| Annullata | No (rilasciata) | Nessuno | Consultabile (💡 con dicitura "Annullata") |

| Da → A | Chi | Effetti |
|---|---|---|
| Bozza → In approvazione | Agente | Riverifica listino e residui (RB-11 di M06); e-mail ad admin e gestore (✅ D-105) |
| In approvazione → Bozza | Agente | Ritiro (✅ D-100) |
| In approvazione → Approvata | Gestore / Admin | Verifica disponibilità; impegni; congelamento; versione 1; notifica all'agente |
| In approvazione → Rifiutata | Gestore / Admin | Motivazione facoltativa (✅ D-099); notifica all'agente |
| Rifiutata → In approvazione | Agente | Dopo correzione (✅ D-099) |
| Approvata → Approvata (v. n+1) | Admin | Ricalcolo degli impegni sulla differenza; notifica all'agente (✅ D-041, 💡 D-096) |
| Approvata → Annullata | Admin | Rilascio di tutti gli impegni; notifica all'agente |

## 5. Funzionalità
| ID | Funzionalità | Stato |
|---|---|---|
| F-01 | Coda "Da approvare" per admin e gestore, ordinata per data di invio | 💡 |
| F-02 | Schermata di approvazione: configurazione per club, prezzi di listino, controllo disponibilità aggiornato, proposte in attesa sulle stesse risorse | ✅ D-064 |
| F-03 | Modifica della configurazione in approvazione (minuti, posti, pacchetto, club, date) | ✅ D-098 |
| F-04 | Sconto sul totale, in % o in importo; il sistema calcola l'altro valore e il totale finale; nessun limite per il gestore | ✅ D-097, D-104 |
| F-05 | Approva / Rifiuta (motivazione facoltativa) / Forza (solo admin, motivazione obbligatoria) | ✅ D-064, D-099 |
| F-06 | Versioni delle approvate: elenco versioni con autore, data, differenze principali | 💡 D-096 |
| F-07 | Annullamento (solo admin) con rilascio disponibilità | ✅ D-095 |
| F-08 | Notifiche via e-mail: all'agente su approvazione, rifiuto, modifica di un'approvata, annullamento; ad admin e gestore su ogni nuova proposta inviata | ✅ D-014, D-041, D-105 |
| F-09 | Blocco di lavorazione: chi apre una proposta in approvazione la blocca, gli altri la vedono in sola lettura | 💡 |
| F-10 | Storico degli eventi della proposta (invio, ritiro, modifiche, esito, versioni) | 💡 → M10 |

## 6. Flussi utente
**FL-01 Approvazione standard**
1. L'agente invia la proposta. Il sistema riverifica listino e residui: se qualcosa è cambiato, l'invio si blocca e l'agente corregge.
2. Il gestore apre la proposta dalla coda e la blocca in lavorazione.
3. Vede disponibilità e prezzi, applica lo sconto (per esempio 15%) e approva.
4. Il sistema salva:
   - lo snapshot di prezzi, partite e sconto;
   - gli impegni di magazzino con il periodo di contratto (✅ D-062);
   - la versione 1.
5. L'agente riceve la notifica e può scaricare il documento.

**FL-02 Conflitto di disponibilità**
1. In approvazione il controllo segnala un residuo insufficiente su un club o una risorsa e indica le proposte coinvolte.
2. Il gestore può ridurre le quantità o rifiutare.
3. L'admin può anche **forzare**, con motivazione obbligatoria e tracciata.

**FL-03 Rifiuto e reinvio**
1. Rifiuto con motivazione facoltativa e notifica all'agente.
2. L'agente corregge e reinvia: la proposta torna In approvazione, mantenendo lo stesso codice e lo storico.

**FL-04 Modifica di un'approvata**
1. L'admin modifica, per esempio da 1′ a 2′ o cambiando lo sconto.
2. Il sistema verifica la disponibilità solo sulla differenza (forzabile).
3. Salva la versione n+1 e notifica l'agente. Il documento si rigenera sull'ultima versione.

**FL-05 Annullamento**
1. L'admin annulla, con motivazione 💡.
2. Il sistema rilascia gli impegni e notifica l'agente.

## 7. Regole di business
| ID | Regola | Stato |
|---|---|---|
| RB-01 | La disponibilità si impegna solo all'approvazione e si rilascia all'annullamento | ✅ D-027, D-095 |
| RB-02 | All'approvazione prezzi, partite e sconto si congelano; listino e calendario successivi non li modificano | ✅ D-041 |
| RB-03 | Sconto sul totale, in % o in importo; il totale finale non può essere negativo | ✅ D-097 |
| RB-04 | Lo sconto si applica solo sul totale: le righe per club restano a prezzo di listino; il documento mostra totale di listino, sconto e totale finale | ✅ D-103 |
| RB-05 | In approvazione si usano i prezzi di listino correnti; se differiscono da quelli all'invio, il sistema lo segnala | 💡 |
| RB-06 | Il gestore non può forzare; in caso di conflitto può solo ridurre, modificare o rifiutare | ✅ D-070 |
| RB-07 | Le modifiche di un'approvata spettano solo all'admin e generano una nuova versione con notifica all'agente | ✅ D-041; 💡 D-096 |
| RB-08 | Le proposte non si cancellano; solo le bozze sono eliminabili dall'agente | 💡 |
| RB-09 | Codice proposta progressivo e univoco, invariato tra versioni e reinvii | 💡 |

## 8. Validazioni
- Sconto %: tra 0 e 100. Sconto in importo: tra 0 e il totale di listino.
- Forzatura: motivazione obbligatoria.
- Approvazione: senza forzatura, tutti i controlli di disponibilità devono risultare positivi.
- Una modifica in approvazione rispetta le stesse validazioni del configuratore (M06 §8).

## 9. Eccezioni
| Caso | Gestione |
|---|---|
| Due proposte in attesa sugli ultimi minuti | Vince la prima approvata; sull'altra il conflitto risulta in approvazione (FL-02) |
| Listino cambiato mentre la proposta è in attesa | Prezzi correnti con evidenza della differenza (RB-05) |
| Gestore e admin sulla stessa proposta | Blocco di lavorazione (F-09) |
| Agente che ritira mentre il gestore sta lavorando la proposta | 💡 Ritiro non consentito se la proposta è bloccata in lavorazione |
| Annullamento di una proposta con documento già inviato al cliente | Il documento resta nello storico con dicitura "Annullata" |

## 10. Dati (concettuali)
- **Proposta:** stato, versione corrente, sconto (tipo, valore), totale di listino, totale finale, chi ha approvato o rifiutato, motivazione, data esito, eventuale forzatura con motivazione.
- **Versione:** numero, snapshot completo (righe club, prezzi, partite, sconto), autore, data.
- **Evento:** proposta, tipo (invio, ritiro, approvazione, rifiuto, modifica, annullamento), utente, data, note.

## 11. Dipendenze
- **Dipende da:**
  - M04 (verifica, impegni, rilascio);
  - M06 (configurazione);
  - M01 (ruoli e notifiche).
- **Usato da:**
  - M09 (documento dell'ultima versione approvata; prezzi di listino per club e sconto sul totale, D-103);
  - M10 (audit, report).

## 12. Criteri di accettazione
1. Una proposta in Bozza o In approvazione non impegna disponibilità; all'approvazione i residui calano delle quantità della proposta.
2. Il gestore applica uno sconto del 15% su 155.680 €: totale finale 132.328 €; le righe per club restano a prezzo di listino.
3. Uno sconto in importo di 10.000 € mostra anche la % equivalente.
4. In caso di conflitto il gestore non vede "Forza"; l'admin sì, e senza motivazione non può procedere.
5. L'agente riceve un'e-mail a ogni approvazione, rifiuto, modifica di un'approvata e annullamento; admin e gestore ricevono un'e-mail a ogni nuova proposta inviata.
6. Una proposta rifiutata, corretta e reinviata mantiene lo stesso codice e lo storico degli eventi.
7. L'admin modifica un'approvata: si crea la versione 2, la versione 1 resta consultabile, il magazzino si aggiorna sulla differenza.
8. Un'approvata annullata rilascia tutti gli impegni; la proposta resta consultabile.

## 13. Questioni aperte
Nessuna.
