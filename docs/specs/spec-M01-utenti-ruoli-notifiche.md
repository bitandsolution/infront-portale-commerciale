# Specifica funzionale: M01 Utenti, ruoli e notifiche

| Campo | Valore |
|---|---|
| Codice | M01 |
| Nome | Utenti, ruoli e notifiche |
| Versione | 1.0 (approvata, D-140) |
| Data | 2026-10-10 |
| Decisioni di riferimento | D-040, D-068…D-070, D-076, D-078, D-082, D-098, D-104, D-105, D-109, D-128, D-129, D-134…D-139 |

Legenda: ✅ confermato (con riferimento) · 💡 proposta · ❓ da chiarire · ⚠️ rischio

> **Note di allineamento (repository, 2026-10-21)**
> - Autenticazione e 2FA come da D-214: password ≥ 12 caratteri con controllo sulle password violate, 2FA TOTP a ogni login, 8 codici di recupero, blocco di 15 minuti dopo 5 tentativi, sessioni con scadenza per ruolo. "Ricorda dispositivo" rinviato (EV-23).
> - Ruoli come colonna dell'utente e permessi nelle Policy, con test di matrice (D-208, D-215). Attivazione con token di 48 ore e `notification_logs` conservato 12 mesi (D-209).
> - Dimensionamento: fino a 40 agenti (D-148, che sostituisce D-138 citata al §3).
> - Le e-mail di approvazione partono quando il documento è pronto (D-221).
> - Il servizio e-mail resta da decidere prima del rilascio (D-180).

---

## 1. Descrizione
Il modulo copre:
- gestione degli utenti;
- autenticazione e sicurezza degli accessi;
- ruoli e permessi, consolidando quanto deciso nei moduli M02–M10;
- notifiche e-mail.

## 2. Obiettivi
- Accesso sicuro alla piattaforma, con particolare attenzione dopo la compromissione del server attuale (vedi contesto).
- Permessi chiari e coerenti per ogni ruolo, applicati in modo uniforme in tutta l'applicazione.
- Notifiche affidabili sugli eventi del ciclo di vita delle proposte.

## 3. Ruoli (✅ D-040, D-068)
| Ruolo | Descrizione | Numero indicativo |
|---|---|---|
| Admin | Controllo completo della piattaforma | 1 (✅ D-138; il sistema ne ammette più di uno) |
| Gestore | Operativo: approva, gestisce contenuti e KPI, consulta report | 2 (✅ D-138) |
| Agente | Configura e invia proposte per i propri clienti | Fino a 40 (✅ D-148) |

## 4. Matrice dei permessi (consolidata)

| Area / Azione | Agente | Gestore | Admin | Rif. |
|---|---|---|---|---|
| **Anagrafiche sportive (M02)** — consultazione | — (solo via configuratore) | ✅ | ✅ | D-038 |
| Anagrafiche — stagioni, competizioni, club | — | ❌ | ✅ | D-137 |
| Anagrafiche — calendari, import, partite, stadi | — | ✅ | ✅ | D-137 |
| **Catalogo e listini (M03)** — gestione | — | — | ✅ | D-020 |
| Catalogo e listini — consultazione | — | ✅ | ✅ | D-137 |
| **Magazzino (M04)** — capacità e flag | — | ❌ | ✅ | D-137 |
| Magazzino — consultazione | Residui nel configuratore | ✅ | ✅ | D-065, D-071 |
| **Clienti (M05)** — crea, modifica i propri | ✅ | — | ✅ | D-076, D-078 |
| Clienti — vede tutti, assegna, unisce | — | Vede tutti | ✅ | D-082 |
| Settori merceologici | — | — | ✅ | D-075 |
| **Proposte (M06)** — crea, duplica, invia, ritira le proprie | ✅ | — | — | D-069, D-100 |
| Proposte — consultazione di tutte | — | ✅ | ✅ | |
| **Approvazione (M07)** — modifica in approvazione, sconto (senza limiti), approva, rifiuta | — | ✅ | ✅ | D-068, D-098, D-104 |
| Approvazione — forzatura oltre disponibilità | — | ❌ | ✅ | D-070 |
| Approvazione — modifica di approvate, annullamento | — | ❌ | ✅ | D-041, D-095 |
| **Contenuti, slide e KPI (M08)** | — | ✅ | ✅ | D-109 |
| **Documenti (M09)** — download | Proprie approvate | Tutti | Tutti | D-090 |
| Documenti — rigenerazione | — | — | ✅ | D-125 |
| **Audit (M10)** e report accessi | — | ❌ | ✅ | D-129 |
| Report R-01…R-05 | — | ✅ | ✅ | D-134 |
| Cruscotto personale R-06 | ✅ | — | — | D-130 |
| **Utenti (M01)** — gestione | — | — | ✅ | D-139 |

## 5. Gestione utenti (💡)
| Funzione | Note |
|---|---|
| Creazione utente | Solo admin (✅ D-139); dati: nome, cognome, e-mail (username), ruolo, telefono facoltativo |
| Attivazione | E-mail con link per impostare la password (scadenza 💡 48 ore) |
| Modifica ruolo | Admin; tracciata nell'audit |
| Disattivazione | L'utente non accede più; i dati restano. Per un agente: l'admin riassegna i clienti (D-082); le proposte restano all'agente originale (D-083) |
| Reset password | Self-service via e-mail (con verifica 2FA), oppure avviato dall'admin |
| Reset 2FA | Solo admin, per esempio in caso di smartphone perso; tracciato in audit |
| Profilo personale | Ogni utente modifica nome, telefono, password |

## 6. Autenticazione e sicurezza (💡)
| Aspetto | Proposta |
|---|---|
| Login | E-mail (username) + password (✅ D-135) |
| Password | Minimo 12 caratteri; nessuna scadenza forzata; controllo contro password note/compromesse |
| Secondo fattore (2FA) | **Obbligatorio per tutti** (✅ D-136); 💡 app di autenticazione (TOTP: Google/Microsoft Authenticator), configurata al primo accesso; codici di recupero; reset del 2FA solo dall'admin |
| Tentativi falliti | Blocco temporaneo dopo 5 tentativi (15 minuti); tracciato in audit (D-128) |
| Sessione | Scadenza dopo inattività (💡 8 ore per l'agente, 2 ore per admin e gestore) |
| Trasporto | Solo HTTPS |
| Accessi | Tracciati in audit, conservazione 5 anni (D-128) |

## 7. Notifiche e-mail (✅ D-105, D-102)
| Evento | Destinatari |
|---|---|
| Nuova proposta inviata in approvazione | Admin, Gestore |
| Proposta approvata (con link al documento) | Agente proprietario |
| Proposta rifiutata (con eventuale motivazione) | Agente proprietario |
| Proposta approvata modificata (nuova versione) | Agente proprietario |
| Proposta annullata | Agente proprietario |
| Attivazione account, reset password | Utente interessato |
| 💡 Blocco account per tentativi falliti | Utente interessato, Admin |

Regole: mittente unico (💡 es. noreply@dominio Infront), template in italiano con logo Infront, link diretti alla piattaforma (mai allegati con dati sensibili; il documento si scarica dopo il login).

## 8. Regole di business
| ID | Regola | Stato |
|---|---|---|
| RB-01 | Un utente ha un solo ruolo | 💡 |
| RB-02 | I permessi si applicano lato server, non solo nascondendo pulsanti nell'interfaccia | 💡 |
| RB-03 | L'agente vede solo i propri clienti, proposte e documenti | ✅ D-076 |
| RB-04 | Deve esistere sempre almeno un admin attivo | 💡 |
| RB-05 | Gli utenti non si cancellano, si disattivano (tracciabilità) | 💡 |

## 9. Validazioni
- E-mail univoca e valida.
- Password conforme alla policy (§6).
- Impossibile disattivare l'ultimo admin attivo.

## 10. Eccezioni
| Caso | Gestione |
|---|---|
| Agente che lascia l'azienda | Disattivazione; riassegnazione dei clienti; le proposte restano a suo nome |
| Gestore e admin assenti | 💡 Le proposte restano in coda; nessuna approvazione automatica |
| E-mail di notifica non recapitata | 💡 Registro degli invii consultabile dall'admin |

## 11. Dipendenze
- **Usato da:** tutti i moduli.
- **Dipende da:** servizio di invio e-mail (Fase 5).

## 12. Criteri di accettazione
1. Un agente non può accedere senza aver configurato il secondo fattore.
2. Dopo 5 login falliti l'account si blocca per 15 minuti e l'evento è in audit.
3. Un gestore non vede le funzioni di gestione listini, capacità di magazzino, stagioni, competizioni, club, utenti e audit; una chiamata diretta a queste funzioni viene rifiutata dal server.
4. Disattivando un agente, i suoi clienti risultano riassegnabili e le sue proposte restano consultabili a suo nome.
5. Non è possibile disattivare l'unico admin attivo.
6. All'invio di una proposta admin e gestori ricevono un'e-mail con link diretto; l'e-mail non contiene allegati.

## 13. Questioni aperte
- ⚠️ 2FA obbligatoria anche per i 20 agenti: serve una breve guida di attivazione e una procedura di reset rapida (admin), altrimenti un telefono perso blocca un commerciale.
- 💡 SSO aziendale rinviato (features-evolutive EV-21).
