#!/usr/bin/env bash
# Uploads release.tar.gz to the server and runs deploy/deploy.sh there (docs/dev/ci.md §2; PR-09).
# Reads the variables set by .github/workflows/deploy.yml; the SSH key never touches the disk of the
# release, only ~/.ssh of the runner.
set -euo pipefail

: "${DEPLOY_SSH_KEY:?}" "${DEPLOY_KNOWN_HOSTS:?}" "${DEPLOY_HOST:?}" "${DEPLOY_USER:?}"
: "${DEPLOY_PATH:?}" "${DEPLOY_ENV:?}" "${HEALTH_URL:?}"

release="$(date -u +%Y%m%d%H%M%S)-${GITHUB_SHA:0:7}"

mkdir -p ~/.ssh && chmod 700 ~/.ssh
printf '%s\n' "$DEPLOY_KNOWN_HOSTS" > ~/.ssh/known_hosts
printf '%s\n' "$DEPLOY_SSH_KEY" > ~/.ssh/deploy_key
chmod 600 ~/.ssh/deploy_key ~/.ssh/known_hosts
ssh_opts=(-i ~/.ssh/deploy_key -o BatchMode=yes -o StrictHostKeyChecking=yes)
target="${DEPLOY_USER}@${DEPLOY_HOST}"

scp "${ssh_opts[@]}" release.tar.gz "$target:$DEPLOY_PATH/releases/$release.tar.gz"

# Values are quoted for the remote shell with printf %q.
remote_env=$(printf 'DEPLOY_PATH=%q DEPLOY_ENV=%q HEALTH_URL=%q PHP_BIN=%q FPM_RELOAD_COMMAND=%q BACKUP_COMMAND=%q' \
    "$DEPLOY_PATH" "$DEPLOY_ENV" "$HEALTH_URL" "${PHP_BIN:-php}" \
    "${FPM_RELOAD_COMMAND:-sudo -n systemctl reload php8.4-fpm}" "${BACKUP_COMMAND:-}")

ssh "${ssh_opts[@]}" "$target" bash -s -- "$release" <<REMOTE
set -euo pipefail
cd $(printf '%q' "$DEPLOY_PATH")/releases
mkdir "\$1"
tar -xzf "\$1.tar.gz" -C "\$1"
rm "\$1.tar.gz"
cd "\$1"
$remote_env bash deploy/deploy.sh "\$1"
REMOTE

rm -f ~/.ssh/deploy_key
