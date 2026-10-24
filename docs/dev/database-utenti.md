# Utenti MariaDB del portale (requisito R7)

Stato: PR-05 (D-210, D-211, D-283). Lo script è `database/sql/audit-users.sql`. In produzione lo esegue **Antonio**, come amministratore di MariaDB: l'hosting è fuori dal perimetro dello sviluppo (D-175).

## 1. Perché tre utenti

L'audit (`audit_events`) è **append-only** (D-210, M10-CA3). Lo garantiscono due trigger della migrazione, che rifiutano `UPDATE` e `DELETE` a **qualunque** utente MariaDB, root compreso, con due sole eccezioni, necessarie alla conservazione:

| Operazione ammessa dal trigger | Perché |
|---|---|
| `UPDATE` che svuota **solo** `ip_address` | L'IP scade prima dell'evento (D-211) |
| `DELETE` di eventi più vecchi di **5 anni** | Conservazione di 5 anni (RB-A2, D-128) |

I permessi di MariaDB si possono solo aggiungere: un utente con `UPDATE`/`DELETE` sul database li ha su tutte le tabelle, `audit_events` compresa. Per questo la protezione dell'audit sta nei trigger, e chi usa il portale non deve poterli cancellare. Quindi l'utente applicativo **non ha permessi DDL** e le migrazioni girano con un utente distinto (D-283, che precisa D-210 e R7).

| Utente | Variabili del `.env` | Permessi | Chi lo usa |
|---|---|---|---|
| Migrazioni | `DB_MIGRATION_USERNAME`, `DB_MIGRATION_PASSWORD` | `ALL PRIVILEGES` sul database del portale | Solo il deploy: `php artisan migrate --database=migrator --force` |
| Applicativo | `DB_USERNAME`, `DB_PASSWORD` | `SELECT, INSERT, UPDATE, DELETE` sul database, nessun DDL | Il portale (PHP-FPM, worker, scheduler) |
| Manutenzione | `DB_AUDIT_MAINTENANCE_USERNAME`, `DB_AUDIT_MAINTENANCE_PASSWORD` | Su `audit_events` soltanto: `SELECT`, `DELETE`, `UPDATE (ip_address)` | Il comando notturno `audit:prune` |

Dalla PR-07 `audit:prune` legge i giorni di conservazione dell'IP dalla tabella `settings` con la connessione dell'utente **applicativo**, poi cancella e svuota con l'utente di manutenzione: a quest'ultimo non serve alcun permesso in più e lo script non cambia.

⚠️ Un amministratore di MariaDB (root) può sempre togliere i trigger: la protezione è contro un portale compromesso, non contro chi amministra il server.

## 2. Applicare lo script in produzione

1. Copia `database/sql/audit-users.sql` sul server **fuori dal repository** (contiene le password dopo la sostituzione) e sostituisci i segnaposto:

   | Segnaposto | Valore |
   |---|---|
   | `__DATABASE__` | nome del database del portale |
   | `__HOST__` | host da cui si collega il portale, di solito `localhost` |
   | `__MIGRATION_USER__`, `__APP_USER__`, `__MAINTENANCE_USER__` | nomi dei tre utenti |
   | `__MIGRATION_PASSWORD__`, `__APP_PASSWORD__`, `__MAINTENANCE_PASSWORD__` | tre password lunghe e diverse |

2. **Prima del primo deploy** esegui la parte A (utenti migrazioni e applicativo).
3. Esegui il primo deploy, con le migrazioni lanciate dall'utente migrazioni.
4. **Dopo la prima migrazione** esegui la parte B (utente di manutenzione): concede permessi sulla tabella `audit_events`, che deve già esistere.
5. Scrivi le tre coppie utente/password nel `.env` di produzione (tabella del §1) e cancella la copia dello script con le password.

Lo script si può rieseguire: crea gli utenti solo se mancano e i permessi si sommano.

⚠️ **Log binario attivo** (replica o backup point-in-time): MariaDB permette di creare trigger solo a chi ha il privilegio `SUPER`, oppure con `log_bin_trust_function_creators = 1` nella configurazione del server. Senza una delle due, la migrazione di `audit_events` si ferma con un errore sui permessi. Va deciso da chi amministra il server.

## 3. Controllo dopo l'installazione

Collegato come utente applicativo, ognuno di questi comandi deve essere **rifiutato**:

```sql
UPDATE audit_events SET user_name = 'x';                -- append-only (trigger)
DELETE FROM audit_events;                               -- append-only (trigger)
DROP TRIGGER audit_events_before_delete;                -- TRIGGER command denied
```

Collegato come utente di manutenzione, `SELECT COUNT(*) FROM users;` deve essere rifiutato. `php artisan audit:prune` deve rispondere con il numero di eventi eliminati e di IP svuotati.

## 4. Sviluppo, CI e VM

- **CI**: la connessione è root. I test dei trigger (`tests/Feature/Audit/AuditEventsTableTest.php`) valgono anche per root. I test dello script (`tests/Database/AuditUsersScriptTest.php`) creano i tre utenti con nomi e password di prova, si collegano con ciascuno e poi li cancellano.
- **VM**: Sail usa un utente con tutti i permessi sul solo database del portale, che non può creare utenti. I test dello script risultano saltati (*skipped*), è previsto. Le variabili del §1 restano vuote, quindi migrazioni e `audit:prune` usano `DB_USERNAME`. Il collaudo dei trigger si fa con root di Sail: `sail exec mariadb mariadb -uroot -ppassword infront_portale`.
