# Collaudo della milestone A — Dati di base (F0, S1, F1, F2)

Stato: 💡 proposta (D-353, 4/11/2026). Riferimenti: pianificazione §6.1.4, `docs/dev/pr-f0.md`, `pr-f1.md`, `pr-f2.md`, `docs/dev/ambiente-locale.md`.

Ogni PR delle fette F0, S1, F1 e F2 è già stata collaudata da sola. La milestone A prova invece il **percorso completo**, nell'ordine in cui l'admin caricherà i dati della stagione 2027-28 in produzione con il rilascio interno **R0** (D-235). La stessa checklist, senza i passi segnati "solo VM", è la traccia del caricamento di R0.

La milestone si svolge in due tempi (D-353):

| Tempo | Dove | Dati | Quando |
|---|---|---|---|
| **A1** | VM di collaudo | Fittizi (club del vecchio sistema, calendario e prezzi inventati) | Subito dopo la chiusura di F2 |
| **A2** | VM di collaudo, con l'eccezione di D-355 | Calendario reale di Infront, listino e capacità reali di 2–3 club | All'arrivo del calendario (entro il 30/11/2026) |

Come si compila: per ogni passo segna ✅ (esito atteso), ❌ (diverso: annota cosa vedi) o ⏭ (saltato, con il motivo). Gli esiti con ❌ diventano una PR `fix/…` prima di R0.

Tutti i comandi si eseguono **nel terminale della VM** (collegamento da PowerShell: `ssh -p 2222 antonio@127.0.0.1`, poi `cd ~/infront-portale-commerciale` e `sail up -d`).

---

## 1. Preparazione (A1)

| # | Passo | Esito atteso |
|---|---|---|
| 1.1 | Da VirtualBox, istantanea **"prima della milestone A"** | Si potrà tornare ai dati dei collaudi precedenti |
| 1.2 | `git checkout main && git pull`, `sail composer install`, `sail npm ci && sail npm run build` | Nessun errore |
| 1.3 | `sail artisan migrate:fresh --seed` (⚠️ **cancella tutti i dati della VM**) | Tabelle ricreate; dati di sistema (impostazioni, calcio con Serie A, B, C gironi A/B/C, catalogo) e utenti di prova |
| 1.4 | `sail artisan users:create-admin`, poi accesso su http://localhost:8080 e configurazione della 2FA dell'admin | Dashboard dell'admin |
| 1.5 | Seconda finestra: `sail artisan horizon`; poi `sail artisan schedule:run` e http://localhost:8080/health | `{"status":"ok"}` |
| 1.6 | `sail artisan test` | Tutto verde, 3 *skipped* (`AuditUsersScriptTest`) |

## 2. F0 — Accesso, permessi, audit

| # | Passo | Criterio | Esito atteso |
|---|---|---|---|
| 2.1 | Accesso come `agente2` (senza 2FA) | M01-CA1 | Configurazione della 2FA obbligatoria prima di qualsiasi pagina |
| 2.2 | 5 password errate per `agente1` | M01-CA2, M10-CA2 | Blocco di 15′, e-mail di blocco in Mailpit (utente e admin); `sail artisan audit:list --area=access` mostra i tentativi con IP e il blocco. Poi `sail artisan cache:clear` |
| 2.3 | Come `gestore1`: menu laterale e URL diretti `/catalogo/valori`, `/magazzino/capacita`, `/anagrafiche/stagioni`, `/anagrafiche/club`, `/utenti`, `/impostazioni`, `/audit` | M01-CA3 | Voci assenti dal menu; gli URL rispondono con la pagina "Accesso negato" (403) |
| 2.4 | Come admin, **Utenti**: disattivare sé stesso | M01-CA5 | Operazione rifiutata con un messaggio |
| 2.5 | Client MariaDB: `UPDATE audit_events SET user_name = 'x' LIMIT 1;` | M10-CA3 | `ERROR 1644 … immutable` |

## 3. S1 — Pipeline PDF

| # | Passo | Criterio | Esito atteso |
|---|---|---|---|
| 3.1 | `grep DOCUMENT_FONTS_PATH .env` e `sail artisan documents:try` | M09-CA4 (anticipato) | 40 pagine, ≤ 15 MB, font Interstate incorporati, nessun avviso di font di ripiego |

## 4. F1 e F2 — Dati della stagione 2027-28 (percorso di R0)

### 4.1 Anagrafiche (F1)

| # | Passo | Criterio | Esito atteso |
|---|---|---|---|
| 4.1.1 | Copia del CSV dei club (`club-idoc.csv`) con `scp -P 2222` in `storage/app/private/`, poi `sail artisan sports:import-legacy-clubs storage/app/private/club-idoc.csv --merge=8,20 --merge=11,39 --merge=21,35 --merge=25,36` (prima con `--dry-run`) | D-318, D-329 | 81 club, alias e riferimenti `idoc`; flag top match su Lazio, Inter, Milan, Juventus, Roma, Napoli |
| 4.1.2 | **Anagrafiche › Stagioni**: nuova stagione `2027-28` con le date reali della stagione; selettore stagione in alto | D-317 | La 2027-28 è selezionabile e predefinita se è l'unica |
| 4.1.3 | Aggiunta di Serie A e Serie B alla stagione, con il numero standard di partite in casa (19) | D-185 | Edizioni create |
| 4.1.4 | Stadi e iscrizione di 20 club in Serie A e di alcuni club in Serie B; flag "club Infront" su 3 club di Serie A e 1 di Serie B | D-188 | Iscrizioni visibili nell'edizione |
| 4.1.5 | **Import calendario** Serie A con il file fittizio da 380 partite: anteprima, associazione di un nome sconosciuto, conferma | M02-CA1, CA2 | Nessun errore; partite consultabili per club; alias salvato |
| 4.1.6 | Re-import dello stesso file con la data di una partita cambiata e il nome associato al passo precedente | M02-CA2, CA3 | Nome riconosciuto da solo; partita aggiornata, nessuna nuova |
| 4.1.7 | Come `gestore1`: import di un calendario e tentativo di aprire `/anagrafiche/stagioni` | M02-CA8 | Import riuscito; stagioni negate |
| 4.1.8 | `sail artisan sports:matches 2027-28 <CLUB_SERIE_A> --from=01/10/2027` | M02-CA4, CA6 | Partite in casa da quella data; top match valorizzati |
| 4.1.9 | `sail artisan sports:matches 2027-28 <CLUB_SERIE_B>` (edizione senza calendario), poi con un periodo di metà stagione | M02-CA5, CA6 | Numero standard (19), poi la metà arrotondata, con avviso; top match "non applicabile" |
| 4.1.10 | Club con iscrizioni: nessun pulsante di cancellazione, solo disattivazione | M02-CA7 (parte F1) | Come atteso |

### 4.2 Catalogo, listino e magazzino (F2)

| # | Passo | Criterio | Esito atteso |
|---|---|---|---|
| 4.2.1 | **Catalogo › Valori**: controllo di format, livelli, varianti e categorie | D-331, D-339 | Valori di D-332; codici non modificabili |
| 4.2.2 | **Catalogo › Listini LED**: nuovo listino 2027-28 (bozza); prezzi LED dei club Infront (es. Lazio 1ª fila 3.157,89) | D-333 | Riga del prezzo stagionale sotto la griglia: 59.999,91 € per 19 partite (D-340, D-351) |
| 4.2.3 | **Partnership** e **Hospitality**: prezzi (es. Lazio Premium 135.000, posto Premium); un benefit Lazio Top | M03-CA7 | Il benefit non compare per altri club o livelli |
| 4.2.4 | Lasciare un club Infront senza prezzi LED e partnership; **Pubblica** | D-333, D-348 | Avviso non bloccante sul club senza offerte; listino pubblicato, non più riportabile in bozza |
| 4.2.5 | `sail artisan catalog:price 2027-28 LAZIO --format=FILA_1 --minutes=1` e `--minutes=1,5` | M03-CA1 | 59.999,91 € e 89.999,87 € |
| 4.2.6 | Stesso comando con `--from=` a stagione in corso (10 partite rimaste nel calendario fittizio) | M03-CA2 | Costo × minuti × 10 |
| 4.2.7 | `sail artisan catalog:price 2027-28 LAZIO --tier=PREMIUM` con due date di inizio diverse | M03-CA3 | 135.000 € in entrambi i casi |
| 4.2.8 | `sail artisan catalog:price 2027-28 LAZIO --seats=PREMIUM:2`, con e senza `--tier` | M03-CA9 | 2 × prezzo a posto in entrambi i casi |
| 4.2.9 | **Magazzino › Capacità**: capacità dei club Infront; 1ª fila Lazio **non gestita**; un bacino di un altro club a 0 | D-347 | Salvataggio con audit |
| 4.2.10 | `sail artisan inventory:availability 2027-28 LAZIO` e pagina **Magazzino › Disponibilità** | M04-CA3, M03-CA4 | Nessuna opzione 1ª fila, Jumbo, Domination per la Lazio; un format senza prezzo non compare |
| 4.2.11 | `sail artisan inventory:availability` sul club con il bacino a 0 | M04-CA4 | Format "esaurito", visibile ma non selezionabile |
| 4.2.12 | *Solo VM*: un impegno creato come nel collaudo della #63, poi capacità ridotta sotto l'impegnato | M04-CA10 | Conferma richiesta, salvataggio, residuo negativo evidenziato ("sotto l'impegnato", D-349) |
| 4.2.13 | Come `gestore1`: Listini e Disponibilità in sola lettura; Capacità e Valori negati | D-337 | Come atteso |

### 4.3 Audit di tutto il percorso

| # | Passo | Criterio | Esito atteso |
|---|---|---|---|
| 4.3.1 | `sail artisan audit:list --area=sports`, `--area=catalog`, `--area=inventory` | M10-CA1 (parte F1–F2) | Un evento per ogni scrittura fatta sopra, con autore e valori prima/dopo; un solo evento per ogni conferma di import |

## 5. A2 — Dati reali

Si svolge all'arrivo del calendario di Infront, **sulla VM** con l'eccezione di D-355: solo dati commerciali non personali reali (calendario, listino, capacità di 2–3 club), mai nel repository; partire da un'istantanea e chiudere con `sail artisan migrate:fresh --seed`.

| # | Passo | Criterio | Esito atteso |
|---|---|---|---|
| 5.1 | Import del calendario reale di Serie A (e di B, se disponibile), con le colonne riconosciute o scelte a mano | M02-CA1 sui dati reali | Nessun errore; nomi associati salvati come alias |
| 5.2 | Re-import dello stesso file | M02-CA2 | Nessun nome da associare |
| 5.3 | `sports:matches` per i club Infront, a stagione intera e da una data | M02-CA4 | Partite coerenti con il calendario ufficiale |
| 5.4 | Listino e capacità reali di 2–3 club; `catalog:price` per contratti già venduti nel 2026-27 con gli stessi dati | M03-CA1…CA3 | Prezzi uguali a quelli dei documenti del vecchio sistema, a meno dell'arrotondamento (≤ 1 €) |

## 6. Chiusura e prerequisiti di R0

| # | Passo | Esito atteso |
|---|---|---|
| 6.1 | Elenco degli esiti ❌ e PR `fix/…` corrispondenti | Tutti risolti prima di R0 |
| 6.2 | Istantanea della VM **"milestone A1"** | — |
| 6.3 | **Produzione** su VM dedicata con root (D-354) conforme a R1–R7: pacchetti di `docs/dev/ambiente-locale.md`, tre utenti MariaDB (`docs/dev/database-utenti.md`), estensioni PHP `gd`, `fileinfo`, `zip`, `pcntl`, `posix`, processo permanente di Horizon, cron dello scheduler, struttura `releases/`/`shared/`/`current` e regola sudoers (`docs/dev/ci.md` §3), Chrome for Testing, qpdf, poppler-utils, font Interstate in `DOCUMENT_FONTS_PATH`, backup fuori dall'host (D-177) | Pronta entro il **15/1/2027** |
| 6.4 | Primo deploy in produzione, `/health` = ok, `users:create-admin`, caricamento dei dati come al §4 (senza i passi "solo VM") | R0 entro il **18/1/2027** |
| 6.5 | Dopo l'A2: `sail artisan migrate:fresh --seed` sulla VM (D-355) | Nessun dato reale resta sulla VM |
