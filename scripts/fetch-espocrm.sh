#!/usr/bin/env bash
# Download the pinned EspoCRM release into build/ (shared, read-only core for new instances).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
set -a; [ -f "$ROOT/.env" ] && . "$ROOT/.env"; set +a
VER="${ESPO_VERSION:?}"
DEST="$ROOT/build/espocrm-$VER"
[ -d "$DEST" ] && { echo "Already present: $DEST"; exit 0; }
TMP="$(mktemp -d)"
curl -fsSL -o "$TMP/espo.zip" "https://github.com/espocrm/espocrm/releases/download/$VER/EspoCRM-$VER.zip"
echo "sha256: $(shasum -a 256 "$TMP/espo.zip" | cut -d' ' -f1)  (record it in docs/DEPLOYMENT.md when upgrading)"
unzip -q "$TMP/espo.zip" -d "$TMP"
mkdir -p "$ROOT/build"
mv "$TMP/EspoCRM-$VER" "$DEST"
echo "EspoCRM $VER → $DEST"
