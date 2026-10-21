#!/usr/bin/env bash
# Runs a command; on failure, reports the tail of its output as a GitHub annotation,
# so the error is readable from the checks API without downloading the job log.
set -o pipefail
title="$1"; shift
log=$(mktemp)
if "$@" 2>&1 | tee "$log"; then
    exit 0
fi
status=$?
msg=$(tail -n 60 "$log" | sed -e 's/%/%25/g' -e 's/\r//g' | awk 'BEGIN{ORS="%0A"} {print}')
echo "::error title=${title}::${msg}"
exit $status
