#!/usr/bin/env bash
# Restore one company from backup.sh output. Overwrites that company's database and data.
# Usage: scripts/restore.sh <slug> <backup.sql.gz> <backup.files.tar.gz>
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
set -a; [ -f "$ROOT/.env" ] && . "$ROOT/.env"; set +a
SLUG="${1:?slug}"; SQL="${2:?sql.gz}"; FILES="${3:?files.tar.gz}"; INST="$ROOT/instances/$SLUG"
[ -d "$INST" ] || { echo "Instance $SLUG must exist (create it or copy the core first)" >&2; exit 1; }
read -r -p "Overwrite database goldcrm_$SLUG and data of '$SLUG'? Type the slug to confirm: " ok
[ "$ok" = "$SLUG" ] || { echo "Aborted."; exit 1; }
gunzip -c "$SQL" | MYSQL_PWD="${DB_ROOT_PASSWORD:-}" mysql -h "$DB_HOST" -u "$DB_ROOT_USER" "goldcrm_$SLUG"
tar -C "$INST" -xzf "$FILES"
(cd "$INST" && php -d error_reporting='E_ALL & ~E_DEPRECATED' command.php clear-cache)
echo "Restored $SLUG."
