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

| Evento | Destinazione | Note |
|---|---|---|
| Merge su `main` | **staging**, automatico | Dopo lo stesso controllo della CI |
| Tag `vX.Y.Z` | **produzione** | Ambiente protetto: approvazione di Antonio; backup del database prima del deploy (D-177) |

Passi del deploy, con utente SSH dedicato senza sudo (R6):
1. Caricamento del rilascio in `releases/<data-ora>`.
2. `composer install --no-dev`, build del frontend nella pipeline (gli asset compilati arrivano già pronti).
3. Collegamento di `.env` e `storage/` condivisi.
4. `php artisan migrate --force` (migrazioni compatibili all'indietro).
5. Cache di configurazione, rotte e viste.
6. Cambio atomico del link `current`; ricaricamento di PHP-FPM; `horizon:terminate` per riavviare i worker.
7. Controllo `/health`; se fallisce, **ritorno automatico** al rilascio precedente.
8. Conservazione degli ultimi 5 rilasci.

## 3. Prerequisiti di Antonio
- Repository: `github.com/bitandsolution/infront-portale-commerciale` (D-248).
- Collaudo durante lo sviluppo su VM locale (`docs/dev/ambiente-locale.md`, D-261). Staging su server conforme a R1–R7 quando disponibile, al più tardi entro il 26/2/2027 (milestone B); produzione entro il 15/1/2027 (D-243).
- Utente di deploy e chiave SSH dedicata, da salvare nel GitHub Environment.
- `DOCUMENT_FONTS_PATH` sul server con i file Interstate autorizzati.

## 4. Note di esercizio (PR-00, 2026-10-21)
- Gli errori di ogni passo sono riportati come **annotazioni** del check (`.github/scripts/run.sh`), leggibili anche dove i log completi dei job non sono raggiungibili.
- Se `composer.lock` manca, la CI lo risolve e lo **committa sul branch della PR** (unico caso in cui il job ha permesso di scrittura). Una volta presente, la CI usa sempre `composer install` dal lock.
- ⚠️ Nelle sessioni cloud di Claude **packagist.org non è raggiungibile** e PHP è alla 8.3: la parte PHP si verifica solo in CI; la parte frontend anche in locale. Per lavorare più velocemente serve abilitare packagist.org nella rete delle sessioni, oppure sviluppare in locale con Sail.
