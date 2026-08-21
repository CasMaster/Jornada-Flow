#!/bin/sh
set -eu

PROJECT_NAME="${COMPOSE_PROJECT_NAME:-$(basename "$PWD")}"

podman-compose build

for service in hibrido_laravel queue_worker scheduler; do
    ids="$(podman ps -aq \
        --filter "label=io.podman.compose.project=$PROJECT_NAME" \
        --filter "label=com.docker.compose.service=$service")"
    if [ -n "$ids" ]; then
        podman rm -f $ids
    fi
done

podman-compose up -d
