#!/bin/sh
set -eu
URL="${HEALTH_URL:-http://127.0.0.1:8082/health/ready}"
curl --fail --silent --show-error "$URL"
podman ps --filter name=hibrido-home-office --format '{{.Names}} {{.Status}}'
