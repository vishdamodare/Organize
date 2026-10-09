#!/usr/bin/env bash
# Run EspoCRM scheduled jobs (reminders, notifications, cleanup) for every company.
# Schedule every minute:  * * * * * /path/to/Gold_CRM/scripts/cron.sh >/dev/null 2>&1
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
for dir in "$ROOT"/instances/*/; do
    [ -f "$dir/data/config-internal.php" ] && (cd "$dir" && php -d error_reporting='E_ALL & ~E_DEPRECATED' cron.php)
done
