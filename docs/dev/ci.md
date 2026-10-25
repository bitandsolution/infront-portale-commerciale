# Integrazione continua e deploy

Stato: 💡 descrizione del blocco 6.3. I file di workflow si scrivono con la PR `PR-00` della fetta F0 (6.4).
Riferimenti: D-170, D-176, D-177, D-178, progettazione-tecnica §5.2.6.

## 1. Pipeline sulle pull request (GitHub Actions)

| Job | Contenuto | Blocca il merge |
|---|---|---|
| `php` | Composer install con cache; **Pint** in modalità test; **Larastan** | Sì |
| `tests` | MariaDB 11.8 e Redis come servizi; `php artisan test --parallel`; report di copertura come artefatto | Sì |
| `frontend` | `npm ci`; `vue-tsc`; ESLint; `vite build` | Sì |
| `audit` | `composer audit` e `npm audit --audit-level=high` | Sì, sulle vulnerabilità alte e critiche |
| `pdf` (dalla fetta S1) | Chromium + qpdf nel runner; generazione dei casi di prova dei layout e confronto con le immagini di riferimento | Sì |

- Regole di protezione su `main`: PR obbligatoria, CI verde, approvazione di Antonio, nessun push diretto né force push.
- Dependabot settimanale per Composer, npm e GitHub Actions.
- Segreti: solo in GitHub Environments (`staging`, `production`); nessun segreto nei workflow.

## 2. Deploy

Workflow `.github/workflows/deploy.yml` (PR-09): **pronto ma non attivo** finché lo staging su server non è disponibile (D-261). Ogni job parte solo se la sua variabile del repository vale `true` (*Settings → Secrets and variables → Actions → Variables*); altrimenti risulta saltato.

| Evento | Destinazione | Condizioni |
|---|---|---|
| CI verde su `main` (dopo il merge) | **staging**, automatico | `DEPLOY_STAGING_ENABLED=true` |
| Tag `vX.Y.Z` | **produzione** | `DEPLOY_PRODUCTION_ENABLED=true`; il tag è su un commit di `main` con la CI verde; ambiente `production` protetto con l'approvazione di Antonio; backup del database prima del deploy (D-177) |

Configurazione di ogni GitHub Environment (`staging`, `production`):
- segreti: `DEPLOY_SSH_KEY` (chiave privata dell'utente di deploy, R6), `DEPLOY_KNOWN_HOSTS` (riga del server per `known_hosts`), `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`;
- variabili: `DEPLOY_HEALTH_URL` (indirizzo di `/health`), facoltative `DEPLOY_PHP_BIN` e `DEPLOY_FPM_RELOAD_COMMAND`; solo in produzione `DEPLOY_BACKUP_COMMAND` (comando del backup del database: senza, il deploy di produzione si ferma).

La pipeline costruisce il rilascio (`.github/scripts/build-release.sh`: dipendenze senza quelle di sviluppo, asset compilati, senza test né documentazione), lo carica sul server e lancia `deploy/deploy.sh` (`.github/scripts/deploy-release.sh`). Struttura sul server: `releases/<data-ora>-<commit>`, `shared/.env`, `shared/storage`, link `current` (radice web: `current/public`).

Passi di `deploy/deploy.sh`, con utente SSH dedicato senza sudo (R6):
1. Collegamento di `.env` e `storage/` condivisi al rilascio già scompattato in `releases/<rilascio>`.
2. Solo produzione: backup del database con `BACKUP_COMMAND` (D-177).
3. `php artisan migrate --database=migrator --force` (migrazioni compatibili all'indietro), con l'utente MariaDB delle migrazioni: l'utente applicativo non ha permessi DDL (R7, D-283; `docs/dev/database-utenti.md`).
4. `php artisan db:seed --class=SystemDataSeeder --force` (dati di sistema, PR-07): inserisce solo ciò che manca e non cambia mai una riga, quindi i valori modificati dall'admin restano; basta l'utente applicativo (solo `INSERT`). Al primo deploy crea le impostazioni con i valori iniziali.
5. Cache di configurazione, rotte, viste ed eventi.
6. Cambio atomico del link `current`; ricaricamento di PHP-FPM (`FPM_RELOAD_COMMAND`, predefinito `sudo -n systemctl reload php8.4-fpm`: l'unico comando ammesso all'utente di deploy da una regola sudoers, precisazione di R6 della PR-09); `horizon:terminate` per riavviare i worker.
7. Controllo di `/health` per un minuto (`{"status":"ok"}`); se fallisce, **ritorno automatico** al rilascio precedente, con un nuovo ricaricamento di PHP-FPM e dei worker.
8. Conservazione degli ultimi 5 rilasci.

### 2.1 Monitoraggio (PR-09, D-179)
- **`/health`**: pubblico, fuori dal gruppo `web` (niente sessione né cookie); risponde 200 `{"status":"ok"}` o 503 `{"status":"fail"}` senza dettagli. Controlla database, Redis, Horizon attivo (non in pausa né fermo), lavoro più vecchio in coda sotto i 10 minuti, disco di `storage/` sotto l'80%, segnale dello scheduler (scritto ogni minuto) sotto i 10 minuti. I dettagli si leggono sul server con `php artisan health:check`; uno stato di errore va nel log al massimo ogni 10 minuti. Soglie in `config/health.php`.
- **Servizio di uptime esterno** su `/health` ogni minuto, con avviso ad Antonio; controlla anche la scadenza del certificato (dominio ancora da decidere, D-180). Rileva anche Horizon fermo, che non genera eventi.
- **Sentry** (regione dati UE) con `SENTRY_LARAVEL_DSN`; vuoto = spento. Solo errori del backend, senza tracciamento delle prestazioni; nessun dato personale (`config/sentry.php`, `App\Domain\Operations\Services\SentryScrubber`): restano id e ruolo dell'utente, codice di supporto, pagina senza query string, messaggi senza indirizzi e-mail o IP né SQL.
- **Avvisi delle code** agli admin attivi, via e-mail inviata subito (non dalla coda) e registrata in `notification_logs`: lavoro non riuscito dopo l'ultimo tentativo e coda con attesa stimata oltre 10 minuti (`LongWaitDetected` di Horizon), al massimo uno all'ora per tipo di lavoro o per coda.
- **Log**: file giornalieri in `storage/logs`, conservati 30 giorni; in produzione `LOG_LEVEL=info`.
- Ultimo backup: controllo rinviato a quando esistono i backup del portale (D-177).

## 3. Prerequisiti di Antonio
- Repository: `github.com/bitandsolution/infront-portale-commerciale` (D-248).
- Collaudo durante lo sviluppo su VM locale (`docs/dev/ambiente-locale.md`, D-261). Staging su server conforme a R1–R7 quando disponibile, al più tardi entro il 26/2/2027 (milestone B); produzione entro il 15/1/2027 (D-243).
- Utente di deploy e chiave SSH dedicata, da salvare nel GitHub Environment, con una sola regola sudoers per ricaricare PHP-FPM (es. `deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm`; precisazione di R6, PR-09).
- Cartelle `releases/` e `shared/` (con `shared/.env` e `shared/storage`) sotto `DEPLOY_PATH`; nginx con radice `current/public`.
- Cron ogni minuto per `php artisan schedule:run` (R2): `/health` fallisce se lo scheduler tace da oltre 10 minuti.
- Progetto Sentry in un'organizzazione con dati nella regione UE (DSN in `SENTRY_LARAVEL_DSN` del `.env` del server) e servizio di uptime esterno su `/health`.
- Sul server `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `LOG_LEVEL=info` e `expose_php = Off` nel `php.ini`.
- `DOCUMENT_FONTS_PATH` sul server con i file Interstate autorizzati.
- PHP da riga di comando con le estensioni `pcntl` e `posix`, richieste da Horizon (PR-08), e un processo sempre attivo per `php artisan horizon` (supervisor o servizio di sistema), riavviato dal deploy con `horizon:terminate` (passo 7).
- I tre utenti MariaDB di `database/sql/audit-users.sql` (migrazioni, applicativo, manutenzione dell'audit), da creare come in `docs/dev/database-utenti.md` (R7, D-283).

## 4. Note di esercizio (PR-00, 2026-10-21)
- Gli errori di ogni passo sono riportati come **annotazioni** del check (`.github/scripts/run.sh`), leggibili anche dove i log completi dei job non sono raggiungibili.
- Se `composer.lock` manca, la CI lo risolve e lo **committa sul branch della PR** (unico caso in cui il job ha permesso di scrittura). Una volta presente, la CI usa sempre `composer install` dal lock.
- Pacchetto nuovo (PR-03): la PR lo aggiunge al `composer.json` e la CI risolve **solo quel pacchetto** e le sue dipendenze (`composer update <pacchetti> --with-dependencies`, elenco da `.github/scripts/missing-locked-packages.php`), poi committa il `composer.lock` aggiornato sul branch. ⚠️ Il commit del bot non fa ripartire la CI (limite del `GITHUB_TOKEN`): serve un altro push sul branch, che conferma il lock con `composer install`.
- ⚠️ Nelle sessioni cloud di Claude **packagist.org non è raggiungibile** e PHP è alla 8.3: la parte PHP si verifica solo in CI; la parte frontend anche in locale. Per lavorare più velocemente serve abilitare packagist.org nella rete delle sessioni, oppure sviluppare in locale con Sail.
- Suite di test (PR-05): `Unit`, `Feature` (in transazione), `Concurrency` e `Database` (con troncamento delle tabelle). La suite `Database` crea utenti MariaDB e richiede un amministratore: in CI la connessione è root, sulla VM i suoi test risultano saltati.
