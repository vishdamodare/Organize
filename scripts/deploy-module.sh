#!/usr/bin/env bash
# Copy the Jewellery module from src/ into one (or every) company instance and rebuild.
# Usage: scripts/deploy-module.sh <slug>|--all
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="${1:?slug or --all required}"

if [ "$TARGET" = "--all" ]; then
    for dir in "$ROOT"/instances/*/; do "$0" "$(basename "$dir")"; done
    exit 0
fi

INST="$ROOT/instances/$TARGET"
[ -f "$INST/data/config-internal.php" ] || { echo "No installed instance: $TARGET" >&2; exit 1; }

echo "→ Deploying module to $TARGET"
mkdir -p "$INST/custom/Espo/Modules/Jewellery" "$INST/client/custom/modules/jewellery"
rsync -a --delete "$ROOT/src/custom/Espo/Modules/Jewellery/" "$INST/custom/Espo/Modules/Jewellery/"
rsync -a --delete "$ROOT/src/client/custom/modules/jewellery/" "$INST/client/custom/modules/jewellery/"

cd "$INST"
php -d error_reporting='E_ALL & ~E_DEPRECATED' command.php rebuild
php -d error_reporting='E_ALL & ~E_DEPRECATED' command.php clear-cache
# New ?r= on CSS/JS/templates so browsers fetch the new files; open tabs get a "Refresh" prompt.
php -d error_reporting='E_ALL & ~E_DEPRECATED' command.php update-app-timestamp
