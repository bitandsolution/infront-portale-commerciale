#!/usr/bin/env bash
# Builds the release archive for the deploy (docs/dev/ci.md §2; PR-09): production dependencies,
# compiled frontend assets, without tests, documentation and development files.
# Output: release.tar.gz in the working directory.
#
# Browsershot needs Node.js and puppeteer at runtime (R1, D-223): the archive keeps the production
# npm dependencies (node_modules after `npm prune --omit=dev`) but not Chrome for Testing, which the
# server installs once into shared/puppeteer (deploy/deploy.sh, step 1; D-308).
set -euo pipefail

composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts
PUPPETEER_SKIP_DOWNLOAD=true npm ci --no-audit --no-fund
npm run build
npm prune --omit=dev --no-audit --no-fund

tar -czf release.tar.gz \
    --exclude='./.git' --exclude='./.github' --exclude='./.cache' --exclude='./tests' \
    --exclude='./docs' --exclude='./.env' --exclude='./storage' --exclude='./release.tar.gz' \
    --exclude='./public/hot' --exclude='./phpunit.xml' --exclude='./compose.yaml' --exclude='./docker' \
    .

echo "release.tar.gz: $(du -h release.tar.gz | cut -f1)"
