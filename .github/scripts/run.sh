#!/usr/bin/env bash
# Runs a command; on failure, reports the tail of its output as a GitHub annotation,
# so the error is readable from the checks API without downloading the job log.
# TEMPORARY (PR-04): one annotation per failed test, to be reverted before merge.
set -o pipefail
title="$1"; shift
log=$(mktemp)
"$@" 2>&1 | tee "$log"
status=${PIPESTATUS[0]}
if [ "$status" -ne 0 ]; then
    clean=$(mktemp)
    sed -e 's/\x1b\[[0-9;]*m//g' -e 's/%/%25/g' -e 's/\r//g' "$log" > "$clean"
    n=0
    for line in $(grep -n "FAILED " "$clean" | cut -d: -f1 | head -n 9); do
        msg=$(sed -n "${line},$((line + 30))p" "$clean" | awk 'BEGIN{ORS="%0A"} {print}')
        echo "::error title=${title} ${n}::${msg}"
        n=$((n + 1))
    done
    msg=$(tail -n 60 "$clean" | awk 'BEGIN{ORS="%0A"} {print}')
    echo "::error title=${title}::${msg}"
fi
exit "$status"
