#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="$SCRIPT_DIR/.tunnel-logs"
PID_FILE="$LOG_DIR/pids"

if [[ ! -f "$PID_FILE" ]]; then
    echo "No tunnel PID file found."
    exit 0
fi

while read -r pid; do
    [[ -n "$pid" ]] || continue
    if kill -0 "$pid" >/dev/null 2>&1; then
        echo "Stopping $pid"
        kill "$pid" >/dev/null 2>&1 || true
    fi
done < "$PID_FILE"

: > "$PID_FILE"
echo "Stopped."
