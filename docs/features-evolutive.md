# Features evolutive — Portale Commerciale Infront

Raccolta delle funzionalità **fuori MVP**: rinviate, predisposte nell'architettura o proposte come miglioramento. Si aggiorna durante l'analisi (D-133) e si consegna completa al termine.

Ultimo aggiornamento: 2026-10-16

Legenda stato:
- **Predisposta:** l'architettura MVP la prevede, va solo attivata o sviluppata.
- **Rinviata:** discussa e rimandata.
- **Proposta:** idea da valutare con Infront.

| ID | Funzionalità | Modulo | Stato | Origine | Note |
|---|---|---|---|---|---|
| EV-01 | **Landing web della proposta** con link inviato al cliente via e-mail | M09, M05 | Predisposta | D-079, D-123 | Composizione indipendente dal formato; e-mail cliente già obbligatoria; modello di pagine salvato sul documento (D-204) |
| EV-02 | **Export PPTX** dei documenti (almeno lato admin) | M09 | Predisposta | D-122 | Le slide sono immagini: modificabili solo i testi dinamici; base: `page_model` (D-204) |
| EV-03 | **Documenti in inglese** | M08, M09 | Predisposta | D-124 | Attributo lingua sulle slide; testi dei layout traducibili |
| EV-04 | **Territorio Estero** | M03, M06 | Predisposta | D-030 | Campo presente, non attivo |
| EV-05 | **Visibilità Virtual** | M03, M04 | Predisposta | D-021 | Tipo visibilità e bacino previsti, non attivi |
| EV-06 | **Bacini Corner bassi e Sotto tribuna** | M04 | Predisposta | D-046 | Attivabili per club |
| EV-07 | **Disponibilità calcolata per partita** (metodo B) | M04 | Predisposta | D-062 | Gli impegni salvano già il periodo di contratto (D-192); switch da impostazione |
| EV-08 | **Obiettivi e budget** per agente o club, con confronto sul venduto | M10 | Predisposta | D-132 | Tabella definita nel modello, creata con la funzione (D-212) |
| EV-09 | **Export Excel** di report ed elenchi proposte | M10 | Rinviata | D-131 | |
| EV-10 | **Altre discipline** (basket, sport invernali, golf, ciclismo…) | M02, M03 | Predisposta | D-016 | Disciplina presente nel modello; MVP solo calcio |
| EV-11 | **Gestionale accrediti** collegato all'anagrafica sportiva centralizzata | M02 | Predisposta | D-035, D-038 | Da realizzare nella revisione di quel software. ⚠️ **Requisito per il nuovo portale accrediti** (D-217): lettura dell'anagrafica **solo via API** `/api/v1` con token in sola lettura, nessun accesso diretto al database del portale; id storici tramite `external_references` (D-186) |
| EV-12 | Testi benefit strutturati per la landing web | M03 | Predisposta | D-117 | Facoltativi nel catalogo; non usati nei PDF |
| EV-13 | Notifiche anche in piattaforma (oltre all'e-mail) | M07, M01 | Proposta | — | Campanella con elenco notifiche |
| EV-14 | Controllo automatico dell'esclusività merceologica | M04, M05 | Proposta | D-022 | Oggi non esiste come regola; i deck Top/Premium la citano: da allineare con Infront |
| EV-15 | Scadenza o promemoria per proposte approvate non firmate, con proposta di annullamento | M07 | Proposta | D-027 | Evita inventario bloccato a lungo |
| EV-16 | Firma elettronica del contratto e stato "Firmata" | M07 | Proposta | — | Chiuderebbe il ciclo commerciale |
| EV-17 | Integrazione CRM | M05, M07 | Proposta | — | Clienti e pipeline commerciale |
| EV-18 | Aggiornamento automatico dei follower social tramite API | M08 | Proposta | — | Oggi aggiornamento manuale ogni 4-6 mesi |
| EV-19 | Report accessi avanzato (alert su tentativi falliti ripetuti) | M10 | Proposta | D-128 | |
| EV-20 | Download tracciato del documento da parte del cliente (via landing) | M09, M10 | Proposta | EV-01 | Utile per follow-up commerciale |
| EV-21 | Accesso con **SSO aziendale** (Microsoft 365 / Google Workspace) | M01 | Rinviata | D-135 | Eliminerebbe le password da gestire |
| EV-22 | Override manuale del prezzo di una riga (solo admin), oltre allo sconto sul totale | M07 | Proposta | D-153 | Nel vecchio sistema usato in 90 proposte su 185 per finalità promozionali |
| EV-23 | **"Ricorda questo dispositivo"** per la 2FA (es. 30 giorni) | M01 | Rinviata | D-214 | Nell'MVP la 2FA si chiede a ogni login; utile per gli agenti su tablet |
| EV-24 | **Archivio PDF delle proposte del vecchio sistema** consultabile nel portale | M09 | Rinviata | D-228 | I PDF vanno generati in blocco dal vecchio sistema (che non li salva, D-149) e riscritti con qpdf in import; tabella `legacy_documents` |
