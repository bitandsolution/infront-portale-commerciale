# UX — Architettura informativa e linee guida (Fase 4)

Ultimo aggiornamento: 2026-10-10 · Decisioni: D-156…D-166 (Fase 4 approvata, D-166)

> **Nota (repository, 2026-10-21)**: font dell'interfaccia IBM Plex Sans e IBM Plex Mono, colore d'accento #C8102E (mockup approvati). Mockup: canvas "Portale Infront – Configuratore", 13 tavole → https://claude.ai/artifact/WnoZA4zUgQMJBHLJvF4B3c (privato). Nella 1.0 la voce "Proposte › Storico" non si usa, perché non c'è migrazione (D-227); la "copia con +%" del listino arriva con il rilascio 1.1 (D-236).

## Principi
- **Tablet-first** per l'agente (D-157): target di riferimento iPad 1024×768 / 1180×820, orizzontale e verticale; desktop pienamente supportato; smartphone per consultazione (proposte, stato, documento).
- **Grafica pulita e neutra** con logo Infront (D-158); colore di accento sobrio; dati e numeri in primo piano.
- **Continuità** con l'interfaccia attuale (D-159): menu laterale, sezioni per club a fisarmonica, "Copia su altri club".
- **Touch**: aree toccabili ≥ 44 px, niente interazioni solo-hover, stepper +/− per minuti (passi di 30″) e posti hospitality.
- **Totale sempre visibile** nel configuratore (barra fissa in basso su tablet, colonna a destra su desktop).

## Navigazione per ruolo
**Agente:** Dashboard · Nuova proposta · Le mie proposte · I miei clienti · Profilo.

**Gestore / Admin** (*corsivo = solo admin*): Dashboard · Proposte (Da approvare, Tutte, Storico) · Clienti (*assegnazione, unione*) · Catalogo (*Listini LED, Partnership, Hospitality*; gestore sola lettura) · Magazzino (disponibilità; *capacità*) · Contenuti (Libreria slide, Simulatore, KPI e digital) · Anagrafiche sportive (Calendari, Import, Partite, Stadi; *Stagioni, Competizioni, Club*) · Report (R-01…R-05; *Accessi*) · *Audit* · *Utenti* · *Impostazioni*.

- Le voci non permesse non sono visibili.
- Selettore di stagione nella barra superiore (default: stagione in corso).
- Dashboard per ruolo: agente → proprie proposte; gestore/admin → coda "Da approvare" e avvisi (residui negativi, KPI non aggiornati, slide mancanti).
- Su tablet il menu laterale si comprime in icone; su smartphone diventa menu a scomparsa.

## Blocchi successivi
- 4.2 Configuratore → mockup approvati (D-162): Periodo e club, Configurazione per club, Riepilogo
- 4.3 Approvazione → mockup approvati (D-163): coda Da approvare, approvazione con conflitto e forzatura, dettaglio con versioni e documento
- 4.4 Backoffice → mockup desktop approvati (D-164): Listino LED (bozza/pubblicato, copia con +%), Magazzino (capacità, impegnato, in attesa, residuo, negativi), Libreria slide con condizioni e simulatore, Import calendario con anteprima e alias
- 4.5 Stati, errori, responsive, wireframe consolidati (D-165)

## 4.5 Stati, errori e responsive

### Breakpoint
| Dispositivo | Larghezza | Comportamento |
|---|---|---|
| Desktop | ≥ 1280 px | Menu laterale esteso; approvazione con pannello laterale |
| Tablet orizzontale | 1024–1279 px | Menu a icone; configuratore come nei mockup; totale in barra fissa |
| Tablet verticale | 768–1023 px | Menu a icone; griglie a 2 colonne (pacchetti, format, club); barra totale fissa |
| Smartphone | < 768 px | Menu a scomparsa; **consultazione**: elenco e dettaglio proposte, download documento, stato; configuratore consentito ma non ottimizzato (💡) |

### Stati
| Stato | Trattamento |
|---|---|
| Vuoto (nessuna proposta, nessun cliente) | Messaggio breve + azione primaria ("Crea la tua prima proposta") |
| Caricamento | Scheletri grigi al posto di tabelle e card; mai schermo bianco |
| Salvataggio bozza | 💡 Salvataggio automatico a ogni modifica, con indicazione "Bozza salvata alle hh:mm" (protegge da chiusure accidentali su tablet) |
| Connessione assente (tablet in trasferta) | Banner "Sei offline: le modifiche verranno salvate al ritorno della connessione"; invio in approvazione disabilitato |
| Sessione scaduta | Ritorno al login con 2FA; al rientro si riapre la bozza dove era |
| Risorsa esaurita | Opzione visibile ma non selezionabile, con motivo ("Esaurito", "Bacino non gestito", "Non disponibile") |

### Errori e messaggi
| Situazione | Messaggio (esempio) | Dove |
|---|---|---|
| Campo obbligatorio | "Inserisci la ragione sociale" | Sotto il campo, al blur e all'invio |
| Minuti non validi | "I minuti vanno a passi di 30″ e non possono superare il residuo (7′30″)" | Sotto lo stepper |
| Hospitality oltre limite | "Massimo 6 posti per club" | Sotto lo stepper |
| Cliente duplicato | "Cliente già presente in anagrafica: contatta l'amministratore" | Dialog bloccante |
| Residuo cambiato all'invio | "La disponibilità di Udinese · 1ª fila è cambiata: residuo 1′00″. Correggi per inviare." | Banner in riepilogo + club evidenziato |
| Listino cambiato | "Il listino di Genoa è stato aggiornato: totale ricalcolato da € X a € Y" | Banner informativo alla riapertura della bozza |
| Conflitto in approvazione | Pannello "Controllo disponibilità" con esito per risorsa | Schermata di approvazione |
| Errore generazione PDF | "Documento non generato. Nuovo tentativo in corso; l'approvazione è valida." | Dettaglio proposta + e-mail admin |
| Errore di sistema | "Qualcosa è andato storto. Riprova; se il problema persiste contatta l'amministratore (codice: ABC123)" | Toast + codice per il supporto |

### Conferme
Le azioni irreversibili o con effetti sul magazzino chiedono conferma esplicita con riepilogo dell'effetto: **Forza e approva**, **Annulla proposta**, **Pubblica listino**, **Riduci capacità sotto l'impegnato**, **Elimina bozza**, **Disattiva utente**.

### Accessibilità
Contrasto testo ≥ 4.5:1; aree toccabili ≥ 44 px; navigazione da tastiera su desktop; stati non affidati al solo colore (sempre testo: "Esaurito", "Conflitto", "Forzato").
