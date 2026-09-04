#!/bin/sh
set -eu
URL="${HEALTH_URL:-http://127.0.0.1:8082/health/ready}"
MAX_SECONDS="${MAX_RESPONSE_SECONDS:-2}"
WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT HUP INT TERM
result="$(curl --fail --silent --show-error --output "$WORK_DIR/health.json" --write-out '%{http_code} %{time_total}' "$URL")"
status="${result%% *}"
elapsed="${result#* }"
[ "$status" = "200" ]
awk -v elapsed="$elapsed" -v maximum="$MAX_SECONDS" 'BEGIN { exit !(elapsed <= maximum) }' || {
    echo "Tempo de resposta acima do limite: ${elapsed}s (máximo ${MAX_SECONDS}s)" >&2
    exit 1
}
cat "$WORK_DIR/health.json"
echo " health_response_seconds=$elapsed"
podman ps --filter name=hibrido-home-office --format '{{.Names}} {{.Status}}'
