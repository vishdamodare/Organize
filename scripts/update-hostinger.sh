#!/usr/bin/env bash
# Update a Hostinger install to the latest code from GitHub.
# Usage (on the server): bash ~/organize/scripts/update-hostinger.sh [web-folder]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
T="${1:-$HOME/domains/vyapaarsathi.co.in/public_html/crm}"
PHP="${PHP:-/opt/alt/php84/usr/bin/php}"
[ -f "$T/data/config-internal.php" ] || { echo "No install found in $T" >&2; exit 1; }

git -C "$ROOT" pull --ff-only
cp -R "$ROOT/src/custom/Espo/Modules/Jewellery" "$T/custom/Espo/Modules/"
cp -R "$ROOT/src/client/custom/modules/jewellery" "$T/client/custom/modules/"

cd "$T"
run() { "$PHP" -d error_reporting='E_ALL & ~E_DEPRECATED' command.php "$@"; }
run rebuild
run clear-cache
# New ?r= on CSS/JS/templates: every browser downloads the new files, open tabs get a "Refresh" prompt.
run update-app-timestamp
echo "✔ Updated $T"
