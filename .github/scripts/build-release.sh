#!/usr/bin/env bash
# Builds the release archive for the deploy (docs/dev/ci.md §2; PR-09): production dependencies,
# compiled frontend assets, without tests, documentation and development files.
# Output: release.tar.gz in the working directory.
set -euo pipefail

composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts
npm ci --no-audit --no-fund
npm run build

tar -czf release.tar.gz \
    --exclude='./.git' --exclude='./.github' --exclude='./node_modules' --exclude='./tests' \
    --exclude='./docs' --exclude='./.env' --exclude='./storage' --exclude='./release.tar.gz' \
    --exclude='./public/hot' --exclude='./phpunit.xml' --exclude='./compose.yaml' \
    .

echo "release.tar.gz: $(du -h release.tar.gz | cut -f1)"
