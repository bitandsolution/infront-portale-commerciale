#!/usr/bin/env bash
# Runs a command; on failure, reports the tail of its output as a GitHub annotation,
# so the error is readable from the checks API without downloading the job log.
set -o pipefail
title="$1"; shift
log=$(mktemp)
"$@" 2>&1 | tee "$log"
status=${PIPESTATUS[0]}
if [ "$status" -ne 0 ]; then
    # TEMPORARY (PR #64): show the diff of Pint; to be restored before the merge.
    if [ "$title" = "Pint" ]; then vendor/bin/pint --test -v app/Support/Inventory/AvailabilityPresenter.php > "$log" 2>&1; fi
    msg=$(tail -n 60 "$log" | sed -e 's/\x1b\[[0-9;]*m//g' -e 's/%/%25/g' -e 's/\r//g' | awk 'BEGIN{ORS="%0A"} {print}')
    echo "::error title=${title}::${msg}"
fi
exit "$status"
