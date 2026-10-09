#!/usr/bin/env bash
# Back up one company: database dump + instance data (config, uploads, custom files).
# Usage: scripts/backup.sh <slug>      → backups/<slug>-<timestamp>.{sql.gz,files.tar.gz}
# Backups contain customer data and DB credentials: store them encrypted, off-server.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
set -a; [ -f "$ROOT/.env" ] && . "$ROOT/.env"; set +a
SLUG="${1:?slug required}"; INST="$ROOT/instances/$SLUG"
[ -d "$INST" ] || { echo "No instance $SLUG" >&2; exit 1; }
TS="$(date +%Y%m%d-%H%M%S)"; OUT="$ROOT/backups"; mkdir -p "$OUT"; chmod 700 "$OUT"
umask 077
MYSQL_PWD="${DB_ROOT_PASSWORD:-}" mysqldump -h "$DB_HOST" -u "$DB_ROOT_USER" --single-transaction --routines \
    "goldcrm_$SLUG" | gzip > "$OUT/$SLUG-$TS.sql.gz"
tar -C "$INST" -czf "$OUT/$SLUG-$TS.files.tar.gz" data custom client/custom
echo "Backup written: $OUT/$SLUG-$TS.sql.gz and $OUT/$SLUG-$TS.files.tar.gz"
