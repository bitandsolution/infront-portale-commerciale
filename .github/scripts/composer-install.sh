#!/usr/bin/env bash
# Installs the Composer dependencies (docs/dev/ci.md §4). A composer.lock without some packages of
# composer.json (a package just added by a PR) is completed by resolving only those packages; without
# composer.lock everything is resolved. Committing the completed lock is a separate step of the
# backend job, on pull requests only.
set -e
cd "$(dirname "$0")/../.."
if [ -f composer.lock ]; then
    missing=$(php .github/scripts/missing-locked-packages.php)
    if [ -n "$missing" ]; then
        echo "::warning::composer.lock without $missing: resolving only these packages"
        .github/scripts/run.sh "composer update" composer update $missing --with-dependencies --no-interaction --no-progress --prefer-dist
    else
        .github/scripts/run.sh "composer install" composer install --no-interaction --no-progress --prefer-dist
    fi
else
    echo "::warning::composer.lock missing: resolving dependencies with composer update"
    .github/scripts/run.sh "composer update" composer update --no-interaction --no-progress --prefer-dist
fi
