#!/usr/bin/env bash
# Local development server for one company instance (PHP built-in server).
# Usage: scripts/serve.sh <slug> [port]   — production uses nginx/apache, see docs/DEPLOYMENT.md
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="${1:?slug required}"; PORT="${2:-8081}"
cd "$ROOT/instances/$SLUG"
echo "Organize [$SLUG] → http://localhost:$PORT"
exec php -d error_reporting='E_ALL & ~E_DEPRECATED' -d display_errors=0 -S "127.0.0.1:$PORT" "$ROOT/scripts/router.php"
