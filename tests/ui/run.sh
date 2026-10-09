#!/usr/bin/env bash
# Browser checks against the demo company (http://127.0.0.1:8080, see README "Demo").
# Starts a throwaway headless Chrome on :9333, then:
#   flows.mjs        → convert-to-deal button + confirm modal, form validation, cancel, quick-create modal
#   screenshots.mjs  → screenshots of key pages (desktop and phone width) into tests/ui/out/
#   overflow.mjs     → no page scrolls sideways at 360/390/414/768 px
set -euo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"; OUT="$DIR/out"; mkdir -p "$OUT"
CHROME="${CHROME:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"
"$CHROME" --headless=new --remote-debugging-port=9333 --user-data-dir="$(mktemp -d)" --no-first-run about:blank >/dev/null 2>&1 &
PID=$!; trap 'kill $PID 2>/dev/null || true' EXIT; sleep 3
EQ=$(curl -s -u owner:${UI_PASS:-Demo-Owner-2026} "http://127.0.0.1:8080/api/v1/Enquiry?primaryFilter=open&maxSize=1" \
    | php -r 'echo json_decode(stream_get_contents(STDIN))->list[0]->id ?? "";')
node "$DIR/screenshots.mjs" "$OUT" '#Home=overview' '#Account=customers' '#Opportunity=pipeline' '#JewelleryReport=reports'
[ -n "$EQ" ] && node "$DIR/flows.mjs" "$OUT" "$EQ"
VIEWPORT=390x844 node "$DIR/screenshots.mjs" "$OUT" '#Home=mobile-overview' '#Account=mobile-customers'
# No page may scroll sideways at phone/tablet widths
node "$DIR/overflow.mjs" '#Home' '#Account' '#Enquiry' '#Opportunity' '#Task' '#JewelleryProduct' '#Quotation' '#CustomerOrder' '#JewelleryReport' '#Account/create'
