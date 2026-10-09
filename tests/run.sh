#!/usr/bin/env bash
# Runs the automated suite against two isolated test companies (created on first run).
#   tests/run.sh            → unit + API tests
# Requires: PHP 8.3+, MariaDB, Node 20+.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export OWNER_PASSWORD="Owner-Test-2026"
export TEST_A_URL="http://127.0.0.1:8091" TEST_B_URL="http://127.0.0.1:8092"

echo "== Unit: money maths"
php "$ROOT/tests/unit/money_test.php"

for pair in "testalpha:$TEST_A_URL:Alpha Gold House" "testbeta:$TEST_B_URL:Beta Diamonds"; do
    IFS=: read -r slug proto host port name <<<"$pair"
    if [ ! -d "$ROOT/instances/$slug" ]; then
        "$ROOT/scripts/create-company.sh" "$slug" "$name" "$proto:$host:$port" owner
    else
        "$ROOT/scripts/deploy-module.sh" "$slug" >/dev/null
        php "$ROOT/scripts/setup-company.php" "$ROOT/instances/$slug" "$name" >/dev/null
    fi
done

"$ROOT/scripts/serve.sh" testalpha 8091 >/dev/null 2>&1 & A=$!
"$ROOT/scripts/serve.sh" testbeta 8092 >/dev/null 2>&1 & B=$!
trap 'kill $A $B 2>/dev/null || true' EXIT
for url in "$TEST_A_URL" "$TEST_B_URL"; do
    for _ in $(seq 1 50); do curl -fs -o /dev/null "$url/" && break; sleep 0.2; done
done

echo "== API tests"
node --test --test-concurrency=1 "$ROOT"/tests/*.test.mjs
