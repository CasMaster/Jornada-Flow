#!/bin/sh
set -eu
umask 077
CONTAINER="${POSTGRES_CONTAINER_NAME:-hibrido-home-office-postgres-prod}"
DATABASE="${DB_DATABASE:-hibrido}"
USERNAME="${DB_USERNAME:-hibrido}"
BACKUP_DIR="${BACKUP_DIR:-/opt/backups/hibrido-home-office}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_REMOTE="${BACKUP_REMOTE:-${ONEDRIVE_REMOTE:-}}"
mkdir -p "$BACKUP_DIR"
DUMP="$BACKUP_DIR/hibrido-$STAMP.dump"
TEMP="$DUMP.tmp"
trap 'rm -f "$TEMP"' EXIT HUP INT TERM
podman exec "$CONTAINER" pg_dump -U "$USERNAME" -d "$DATABASE" -Fc > "$TEMP"
test -s "$TEMP"
mv "$TEMP" "$DUMP"
(cd "$BACKUP_DIR" && sha256sum "$(basename "$DUMP")" > "$(basename "$DUMP").sha256")
chmod 600 "$DUMP" "$DUMP.sha256"
if [ -n "$BACKUP_REMOTE" ]; then
    command -v rclone >/dev/null 2>&1 || { echo 'rclone não está instalado.' >&2; exit 1; }
    rclone copyto "$DUMP" "$BACKUP_REMOTE/$(basename "$DUMP")"
    rclone copyto "$DUMP.sha256" "$BACKUP_REMOTE/$(basename "$DUMP.sha256")"
    rclone check "$BACKUP_DIR" "$BACKUP_REMOTE" --include "$(basename "$DUMP")" --one-way
fi
find "$BACKUP_DIR" -type f -name 'hibrido-*.dump*' -mtime "+$RETENTION_DAYS" -delete
echo "Backup criado em $DUMP${BACKUP_REMOTE:+ e copiado para o destino externo}"
