#!/usr/bin/env bash
# Install one Vyapaarsathi company on Hostinger shared hosting (or any cPanel-style host
# where databases are created in the control panel, not by script).
#
# Run over SSH from the cloned repo:
#   bash scripts/install-hostinger.sh
# It asks for: web folder, site URL, company name, and the database created in hPanel.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export ESPO_VERSION="${ESPO_VERSION:-10.0.9}"

# EspoCRM 10 needs PHP >= 8.3; Hostinger's default CLI php may be older.
PHP="${PHP:-}"
for p in "$PHP" /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php php; do
    [ -n "$p" ] && command -v "$p" >/dev/null && "$p" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' && { PHP="$p"; break; }
    PHP=""
done
[ -n "$PHP" ] || { echo "PHP 8.3+ CLI not found. Set PHP=/path/to/php8.3 and re-run." >&2; exit 1; }
echo "Using $($PHP -r 'echo PHP_BINARY, " (", PHP_VERSION, ")";')"

ask() { local v; read -r -p "$1: " v; [ -n "$v" ] || { echo "required" >&2; exit 1; }; printf '%s' "$v"; }
TARGET="${TARGET:-$(ask 'Web folder of the subdomain (e.g. /home/u123/domains/site.com/public_html/crm)')}"
SITE_URL="${SITE_URL:-$(ask 'Site URL (e.g. https://crm.site.com)')}"
COMPANY="${COMPANY:-$(ask 'Company name')}"
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-$(ask 'Database name (from hPanel)')}"
DB_USER="${DB_USER:-$(ask 'Database user (from hPanel)')}"
[ -n "${DB_PASS:-}" ] || { read -r -s -p "Database password: " DB_PASS; echo; }
OWNER="${OWNER:-owner}"
OWNER_PASS="${OWNER_PASSWORD:-$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | cut -c1-14)Aa7}"

# Hostinger puts a placeholder default.php/index.html in new folders; anything else means "not empty".
mkdir -p "$TARGET"
rm -f "$TARGET/default.php" "$TARGET/index.html"
[ -z "$(ls -A "$TARGET")" ] || { echo "$TARGET is not empty — refusing to overwrite." >&2; exit 1; }

CORE="$ROOT/build/espocrm-$ESPO_VERSION"
if [ ! -d "$CORE" ]; then
    echo "→ Downloading EspoCRM $ESPO_VERSION"
    TMP="$(mktemp -d)"
    curl -fsSL -o "$TMP/espo.zip" "https://github.com/espocrm/espocrm/releases/download/$ESPO_VERSION/EspoCRM-$ESPO_VERSION.zip"
    unzip -q "$TMP/espo.zip" -d "$TMP"
    mkdir -p "$ROOT/build" && mv "$TMP/EspoCRM-$ESPO_VERSION" "$CORE" && rm -rf "$TMP"
fi

echo "→ Copying files"
cp -R "$CORE/." "$TARGET/"
mkdir -p "$TARGET/custom/Espo/Modules" "$TARGET/client/custom/modules"
cp -R "$ROOT/src/custom/Espo/Modules/Jewellery" "$TARGET/custom/Espo/Modules/"
cp -R "$ROOT/src/client/custom/modules/jewellery" "$TARGET/client/custom/modules/"

echo "→ Installing (takes a minute)"
cd "$TARGET"
install() { "$PHP" -d error_reporting='E_ALL & ~E_DEPRECATED' install/cli.php -a "$1" ${2:+-d "$2"} >/dev/null; }
install step1 "user-lang=en_US"
install setupConfirmation "host-name=$DB_HOST&db-name=$DB_NAME&db-user-name=$DB_USER&db-user-password=$DB_PASS"
install saveSettings "site-url=$SITE_URL&default-permissions-user=&default-permissions-group="
install buildDatabase
install createUser "user-name=$OWNER&user-pass=$OWNER_PASS"
install savePreferences "dateFormat=DD/MM/YYYY&timeFormat=hh:mm A&timeZone=Asia/Kolkata&weekStart=1&defaultCurrency=INR&thousandSeparator=,&decimalMark=.&language=en_US"
install finish
grep -q "'isInstalled' => true" data/config-internal.php || { echo "Install failed — check the database details." >&2; exit 1; }

"$PHP" -d error_reporting='E_ALL & ~E_DEPRECATED' command.php rebuild
"$PHP" "$ROOT/scripts/setup-company.php" "$TARGET" "$COMPANY"

cat <<MSG

✔ Vyapaarsathi is live for "$COMPANY".
  URL:       $SITE_URL
  Username:  $OWNER
  Password:  $OWNER_PASS   (shown once — change it after first login)

  Last step — hPanel → Advanced → Cron Jobs → Custom, every minute:
    $PHP -d error_reporting='E_ALL & ~E_DEPRECATED' $TARGET/cron.php
MSG
