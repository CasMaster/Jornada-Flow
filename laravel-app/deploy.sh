#!/bin/sh
set -eu

OIDC_ENV_FILE="${OIDC_ENV_FILE:-/home/admin/.config/mixhome-web/oidc.env}"
if [ -f "$OIDC_ENV_FILE" ]; then
    [ ! -L "$OIDC_ENV_FILE" ] || { echo "OIDC_ENV_FILE não pode ser link simbólico." >&2; exit 1; }
    mode="$(stat -c '%a' "$OIDC_ENV_FILE")"
    [ "$mode" = "600" ] || { echo "OIDC_ENV_FILE deve ter permissão 0600." >&2; exit 1; }
    set -a
    # Arquivo administrado fora do repositório e legível somente pelo usuário de deploy.
    . "$OIDC_ENV_FILE"
    set +a
fi

PROJECT_NAME="${COMPOSE_PROJECT_NAME:-$(basename "$PWD")}";
APP_CONTAINER="${APP_CONTAINER_NAME:-}"
if [ -z "$APP_CONTAINER" ]; then
    APP_CONTAINER="$(podman ps -aq --filter "label=io.podman.compose.project=$PROJECT_NAME" --filter "label=com.docker.compose.service=hibrido_laravel" | head -n 1)"
fi
[ -n "$APP_CONTAINER" ] || APP_CONTAINER="hibrido-home-office-laravel"
BACKUP_DIR="${BACKUP_DIR:-$PWD/backups}"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_FILE="$BACKUP_DIR/pre-deploy-$STAMP.dump"
BACKUP_TEMP="$BACKUP_FILE.tmp"

mkdir -p "$BACKUP_DIR"
umask 077
trap 'rm -f "$BACKUP_TEMP"' EXIT HUP INT TERM

podman-compose up -d postgres
attempt=0
until podman-compose exec -T postgres sh -c 'pg_isready -U "$POSTGRES_USER" -d "$POSTGRES_DB"' >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 12 ]; then
        echo "PostgreSQL não ficou pronto para o backup pré-deploy." >&2
        exit 1
    fi
    sleep 5
done

podman-compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$BACKUP_TEMP"
mv "$BACKUP_TEMP" "$BACKUP_FILE"
sha256sum "$BACKUP_FILE" > "$BACKUP_FILE.sha256"
chmod 600 "$BACKUP_FILE" "$BACKUP_FILE.sha256"

PREVIOUS_IMAGE_ID="$(podman inspect --format '{{.Image}}' "$APP_CONTAINER" 2>/dev/null || true)"
PREVIOUS_IMAGE_NAME="$(podman inspect --format '{{.ImageName}}' "$APP_CONTAINER" 2>/dev/null || true)"

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
APP_CONTAINER="$(podman ps -aq --filter "label=io.podman.compose.project=$PROJECT_NAME" --filter "label=com.docker.compose.service=hibrido_laravel" | head -n 1)"

attempt=0
until podman exec "$APP_CONTAINER" php /var/www/html/docker-healthcheck.php >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 12 ]; then
        echo "Healthcheck falhou após o deploy; restaurando a imagem anterior." >&2
        if [ -n "$PREVIOUS_IMAGE_ID" ] && [ -n "$PREVIOUS_IMAGE_NAME" ]; then
            podman tag "$PREVIOUS_IMAGE_ID" "$PREVIOUS_IMAGE_NAME"
            for service in hibrido_laravel queue_worker scheduler; do
                ids="$(podman ps -aq --filter "label=io.podman.compose.project=$PROJECT_NAME" --filter "label=com.docker.compose.service=$service")"
                [ -z "$ids" ] || podman rm -f $ids
            done
            podman-compose up -d
        fi
        exit 1
    fi
    sleep 5
done

podman exec "$APP_CONTAINER" php artisan hibrido:sync-vacation-entitlements

echo "Deploy validado. Backup prévio: $BACKUP_FILE"
