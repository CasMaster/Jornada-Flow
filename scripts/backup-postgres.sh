#!/bin/sh
set -eu
umask 077
CONTAINER="${POSTGRES_CONTAINER_NAME:-hibrido-home-office-postgres-prod}"
DATABASE="${DB_DATABASE:-hibrido}"
USERNAME="${DB_USERNAME:-hibrido}"
BACKUP_DIR="${BACKUP_DIR:-/opt/backups/hibrido-home-office}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
podman exec "$CONTAINER" pg_dump -U "$USERNAME" -d "$DATABASE" -Fc > "$BACKUP_DIR/hibrido-$STAMP.dump"
sha256sum "$BACKUP_DIR/hibrido-$STAMP.dump" > "$BACKUP_DIR/hibrido-$STAMP.dump.sha256"
chmod 600 "$BACKUP_DIR/hibrido-$STAMP.dump" "$BACKUP_DIR/hibrido-$STAMP.dump.sha256"
find "$BACKUP_DIR" -type f -name 'hibrido-*.dump*' -mtime "+$RETENTION_DAYS" -delete
echo "Backup criado em $BACKUP_DIR/hibrido-$STAMP.dump"
