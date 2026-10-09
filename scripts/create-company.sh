#!/usr/bin/env bash
# Provision an isolated workspace for one jewellery company:
# its own EspoCRM instance, its own database and its own DB user.
#
# Usage: scripts/create-company.sh <slug> "<Company Name>" <site-url> [owner-username]
#   e.g. scripts/create-company.sh shreeganesh "Shree Ganesh Jewellers" http://localhost:8081 owner
# The owner password is taken from $OWNER_PASSWORD or generated and printed once.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
set -a; [ -f "$ROOT/.env" ] && . "$ROOT/.env"; set +a

SLUG="${1:?slug required}"; NAME="${2:?company name required}"; SITE_URL="${3:?site url required}"
OWNER="${4:-owner}"
[[ "$SLUG" =~ ^[a-z0-9]{2,30}$ ]] || { echo "slug must be 2-30 chars of a-z0-9" >&2; exit 1; }
[[ "$OWNER" =~ ^[a-z0-9._@-]{2,50}$ ]] || { echo "invalid owner username" >&2; exit 1; }

INST="$ROOT/instances/$SLUG"
CORE="$ROOT/build/espocrm-${ESPO_VERSION:?}"
[ -e "$INST" ] && { echo "Instance $SLUG already exists" >&2; exit 1; }
[ -d "$CORE" ] || "$ROOT/scripts/fetch-espocrm.sh"

DB="goldcrm_$SLUG"
DB_USER="gc_$SLUG"
DB_PASS="$(openssl rand -hex 20)"
OWNER_PASS="${OWNER_PASSWORD:-$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | cut -c1-14)Aa7}"

mysql_root() { MYSQL_PWD="${DB_ROOT_PASSWORD:-}" mysql -h "$DB_HOST" -u "$DB_ROOT_USER" "$@"; }

echo "→ Creating database $DB and user $DB_USER"
mysql_root -e "CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER '$DB_USER'@'$DB_USER_HOST' IDENTIFIED BY '$DB_PASS';
  GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$DB_USER'@'$DB_USER_HOST';"

echo "→ Copying EspoCRM $ESPO_VERSION"
cp -R "$CORE" "$INST"

echo "→ Installing"
cd "$INST"
install() { php -d error_reporting='E_ALL & ~E_DEPRECATED' install/cli.php -a "$1" ${2:+-d "$2"} >/dev/null; }
install step1 "user-lang=en_US"
install setupConfirmation "host-name=$DB_HOST&db-name=$DB&db-user-name=$DB_USER&db-user-password=$DB_PASS"
install saveSettings "site-url=$SITE_URL&default-permissions-user=&default-permissions-group="
install buildDatabase
install createUser "user-name=$OWNER&user-pass=$OWNER_PASS"
install savePreferences "dateFormat=DD/MM/YYYY&timeFormat=hh:mm A&timeZone=Asia/Kolkata&weekStart=1&defaultCurrency=INR&thousandSeparator=,&decimalMark=.&language=en_US"
install finish
grep -q "'isInstalled' => true" data/config-internal.php || { echo "Install failed" >&2; exit 1; }

"$ROOT/scripts/deploy-module.sh" "$SLUG"
php "$ROOT/scripts/setup-company.php" "$INST" "$NAME"

cat <<MSG

✔ Company "$NAME" is ready.
  URL:       $SITE_URL
  Username:  $OWNER
  Password:  $OWNER_PASS   (shown once — ask the owner to change it after first login)
MSG
