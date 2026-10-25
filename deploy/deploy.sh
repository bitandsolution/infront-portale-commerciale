#!/usr/bin/env bash
# Deploy of one release on the server (docs/dev/ci.md §2; D-178; PR-09).
#
# Run by the deploy workflow (.github/workflows/deploy.yml) over SSH, as the deploy user without
# sudo (R6), from inside the release already unpacked in $DEPLOY_PATH/releases/<release>:
#
#   DEPLOY_PATH=/srv/portale DEPLOY_ENV=staging HEALTH_URL=https://…/health \
#     bash deploy/deploy.sh <release>
#
# Layout on the server:
#   $DEPLOY_PATH/releases/<release>   one folder per release, the last 5 are kept
#   $DEPLOY_PATH/shared/.env          configuration of the environment (never in the repository)
#   $DEPLOY_PATH/shared/storage       files, logs, cache of the views (private disk: D-287)
#   $DEPLOY_PATH/current              link to the running release (document root: current/public)
#
# Variables:
#   DEPLOY_PATH          base folder (required)
#   DEPLOY_ENV           staging | production (required)
#   HEALTH_URL           address of /health checked after the switch (required)
#   PHP_BIN              PHP 8.4 binary (default: php)
#   FPM_RELOAD_COMMAND   reload of PHP-FPM (default: sudo -n systemctl reload php8.4-fpm, allowed to
#                        the deploy user by one sudoers rule, R6 as precised by PR-09)
#   BACKUP_COMMAND       backup of the database before the deploy, required in production (D-177)
#   KEEP_RELEASES        releases kept (default: 5)
#
# If /health does not answer "ok" within a minute the link goes back to the previous release.
# Migrations are backward compatible with the previous version (D-178), so the previous code runs on
# the migrated database.

set -euo pipefail

release="${1:?usage: deploy.sh <release>}"
: "${DEPLOY_PATH:?DEPLOY_PATH is required}"
: "${DEPLOY_ENV:?DEPLOY_ENV is required}"
: "${HEALTH_URL:?HEALTH_URL is required}"
php="${PHP_BIN:-php}"
fpm_reload="${FPM_RELOAD_COMMAND:-sudo -n systemctl reload php8.4-fpm}"
keep="${KEEP_RELEASES:-5}"

releases="$DEPLOY_PATH/releases"
shared="$DEPLOY_PATH/shared"
current="$DEPLOY_PATH/current"
target="$releases/$release"

log() { printf '[deploy %s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }

[ -d "$target" ] || { log "release folder $target not found"; exit 1; }
[ -f "$shared/.env" ] || { log "$shared/.env not found"; exit 1; }
[ -d "$shared/storage" ] || { log "$shared/storage not found"; exit 1; }

cd "$target"

# 1. Shared configuration and storage.
ln -sfn "$shared/.env" "$target/.env"
rm -rf "$target/storage"
ln -sfn "$shared/storage" "$target/storage"

# 2. Backup of the database before a production release (D-177).
if [ "$DEPLOY_ENV" = "production" ]; then
    : "${BACKUP_COMMAND:?BACKUP_COMMAND is required in production (D-177)}"
    log "backup of the database"
    bash -c "$BACKUP_COMMAND"
fi

# 3. Migrations with the MariaDB user of the migrations (R7, D-283), then the system data (PR-07).
log "migrations"
"$php" artisan migrate --database=migrator --force --no-interaction
"$php" artisan db:seed --class=SystemDataSeeder --force --no-interaction

# 4. Caches of configuration, routes, views and events.
"$php" artisan config:cache
"$php" artisan route:cache
"$php" artisan view:cache
"$php" artisan event:cache

# 5. Atomic switch of the link.
previous="$(readlink "$current" 2>/dev/null || true)"
ln -sfn "$target" "$current.next"
mv -Tf "$current.next" "$current"
log "current -> $target"

restart() {
    log "reload of PHP-FPM and restart of the queue workers"
    bash -c "$fpm_reload"
    (cd "$current" && "$php" artisan horizon:terminate)
}

healthy() {
    for _ in $(seq 1 12); do
        if [ "$(curl -fsS --max-time 10 "$HEALTH_URL" 2>/dev/null || true)" = '{"status":"ok"}' ]; then
            return 0
        fi
        sleep 5
    done
    return 1
}

restart

# 6. Health check; back to the previous release if it fails.
if ! healthy; then
    log "/health does not answer ok"
    if [ -n "$previous" ] && [ -d "$previous" ]; then
        ln -sfn "$previous" "$current.next"
        mv -Tf "$current.next" "$current"
        log "rolled back: current -> $previous"
        restart
    fi
    exit 1
fi
log "/health ok"

# 7. Keep the last releases, never the running one.
find "$releases" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
    | sort -rn | tail -n "+$((keep + 1))" | cut -d' ' -f2- \
    | while read -r old; do
        [ "$old" = "$(readlink "$current")" ] || rm -rf "$old"
    done

log "done"
