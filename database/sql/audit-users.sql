-- =====================================================================================
-- MariaDB users of the portal (requirement R7; D-210, D-283).
-- Instructions in Italian: docs/dev/database-utenti.md.
--
-- Three users on the same database:
--   1. migrations  (__MIGRATION_USER__)   DDL privileges; used only by the deploy for
--                                          "php artisan migrate --database=migrator".
--   2. application (__APP_USER__)         runtime of the portal: data only (SELECT, INSERT,
--                                          UPDATE, DELETE), no DDL, so it cannot drop or change
--                                          the triggers that keep audit_events append-only.
--   3. maintenance (__MAINTENANCE_USER__) retention of the audit (audit:prune): on audit_events
--                                          only SELECT, DELETE and UPDATE of ip_address. The
--                                          triggers let it delete only events older than 5 years
--                                          and change nothing but clearing the IP.
--
-- Placeholders to replace before running it as a MariaDB administrator:
--   __DATABASE__  __HOST__
--   __MIGRATION_USER__ __MIGRATION_PASSWORD__
--   __APP_USER__ __APP_PASSWORD__
--   __MAINTENANCE_USER__ __MAINTENANCE_PASSWORD__
--
-- Order: part A before the first deploy; part B after the first "migrate", because it grants
-- privileges on the table audit_events, which must exist. The script can be run again: users are
-- created only if missing and grants are additive.
-- =====================================================================================

-- ---------- Part A: before the first deploy ----------

CREATE USER IF NOT EXISTS '__MIGRATION_USER__'@'__HOST__' IDENTIFIED BY '__MIGRATION_PASSWORD__';
GRANT ALL PRIVILEGES ON `__DATABASE__`.* TO '__MIGRATION_USER__'@'__HOST__';

CREATE USER IF NOT EXISTS '__APP_USER__'@'__HOST__' IDENTIFIED BY '__APP_PASSWORD__';
GRANT SELECT, INSERT, UPDATE, DELETE ON `__DATABASE__`.* TO '__APP_USER__'@'__HOST__';

-- ---------- Part B: after the first "php artisan migrate --database=migrator" ----------

CREATE USER IF NOT EXISTS '__MAINTENANCE_USER__'@'__HOST__' IDENTIFIED BY '__MAINTENANCE_PASSWORD__';
GRANT SELECT, DELETE ON `__DATABASE__`.`audit_events` TO '__MAINTENANCE_USER__'@'__HOST__';
GRANT UPDATE (ip_address) ON `__DATABASE__`.`audit_events` TO '__MAINTENANCE_USER__'@'__HOST__';
