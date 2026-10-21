# Fetta F0 — Fondamenta: elenco delle PR

Stato: 💡 proposta del blocco 6.3. Lo sviluppo parte solo con l'autorizzazione esplicita al blocco 6.4.
Periodo previsto: 2/11 – 18/11/2026 (D-243). Riferimenti: pianificazione §6.1.3, spec-M01, progettazione-tecnica §5.2–§5.5.

| PR | Contenuto | Criteri e decisioni | Dipende da |
|---|---|---|---|
| **PR-00** Scheletro (PR #2) | Laravel 13 su PHP 8.4, Sail (MariaDB 11.8, Redis, Mailpit), Pest, Larastan, Pint, Inertia + Vue 3 TS, ESLint, `vue-tsc`; `.env.example`; workflow CI di `docs/dev/ci.md` §1; comandi del `CLAUDE.md` §12 | D-174, D-178 | — |
| **PR-01** Interfaccia di base | Layout applicativo: menu laterale per ruolo (compresso su tablet, a scomparsa su smartphone), barra superiore con selettore stagione (segnaposto fino a F1), componenti degli stati vuoto, caricamento ed errore, toast con codice di supporto; font IBM Plex Sans/Mono, accento #C8102E | ux-architettura §4.1, §4.5; D-157, D-161, D-165 | PR-00 |
| **PR-02** Utenti e ruoli | Tabella `users` (D-209), enum `Role`, comando Artisan per creare il primo admin, Policy e Gate di base, **test di matrice** rotta × ruolo | M01 RB-01, RB-02; D-208, D-215 | PR-00 |
| **PR-03** Accesso sicuro | Fortify con pagine Inertia; password ≥ 12 caratteri con controllo sulle password violate; 2FA TOTP obbligatoria a ogni login, 8 codici di recupero; blocco di 15 minuti dopo 5 tentativi; sessioni su Redis con scadenza per ruolo (8 h agente, 2 h admin e gestore); il cambio password chiude le altre sessioni | M01-CA1, M01-CA2; D-214 | PR-02 |
| **PR-04** Gestione utenti | Pagine admin: crea (link di attivazione a 48 h), modifica ruolo, disattiva, reset della 2FA, reset password; controllo "almeno un admin attivo" in transazione; profilo personale | M01-CA3 (parziale), M01-CA5; D-139, D-140 | PR-03 |
| **PR-05** Audit | `audit_events` con trigger contro UPDATE e DELETE; script SQL documentato per i due utenti MariaDB (R7); servizio di scrittura usato dalle Action; eventi di accesso (login riuscito, fallito, logout, blocco); job notturno di conservazione (5 anni) e svuotamento degli IP (D-211) | M10-CA2, M10-CA3; D-127, D-128, D-210, D-211 | PR-02 |
| **PR-06** File | `stored_files`, disco privato, verifica del tipo dal contenuto, ricodifica delle immagini, rotta di download con Policy | D-218, C10 | PR-02 |
| **PR-07** Impostazioni | Tabella `settings` con i valori di D-191 e un seeder dei dati di sistema. Le tabelle di catalogo e anagrafica arrivano con le fette F1 e F2, ciascuna con il proprio seeder | D-191; progettazione-tecnica §5.6.1 A1 | PR-00 |
| **PR-08** Code ed e-mail | Code `documents`, `mail` e `default` con Horizon (visibile solo all'admin); `notification_logs`; template e-mail in italiano con logo Infront (segnaposto fino alla ricezione del logo ufficiale); invio delle e-mail di attivazione e reset password | D-209, D-220; M01 §7 | PR-04 |
| **PR-09** Esercizio | Endpoint `/health` (database, Redis, worker, disco); Sentry con regione UE e senza dati personali; intestazioni di sicurezza (HSTS, CSP, X-Frame-Options); log giornalieri a rotazione; deploy su staging (`docs/dev/ci.md` §2) | D-176, D-179 | PR-00; staging pronto (13/11) |

Note:
- PR-02, PR-05, PR-06 e PR-07 possono procedere in parallelo dopo PR-00.
- ⚠️ PR-09 dipende dallo staging conforme a R1–R7 (scadenza 13/11). Se lo staging non è pronto, la fetta si chiude senza il deploy, che slitta a S1.
- Alla fine di F0 parte **S1 Prova della pipeline PDF** (D-239), che usa `docs/layout-dinamici.md`.
