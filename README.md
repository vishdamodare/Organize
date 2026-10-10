# Vyapaarsathi — CRM (MVP)

A CRM for jewellery businesses built on **EspoCRM 10.0.9** (AGPL-3.0). Each company gets its own isolated workspace (instance + database). All jewellery features live in a module under `src/` — EspoCRM core is not modified.

**Features:** customers · jewellery enquiries · Kanban sales pipeline · follow-ups · product catalogue · quotations with server-side totals and PDF · orders from accepted quotations · live overview dashboard · reports with CSV export · team & roles · CSV import.

## Quick start (local)

Needs PHP 8.3+, MariaDB/MySQL, Node 20+ (tests only).

```bash
cp .env.example .env                       # DB provisioning account
scripts/create-company.sh demo "Shree Lakshmi Jewellers" http://localhost:8080 owner
scripts/serve.sh demo 8080                 # open http://localhost:8080
php scripts/seed-demo.php instances/demo 'Demo-Sales-2026'   # optional sample data (demo only)
tests/run.sh                               # unit + API tests (two isolated test companies)
tests/ui/run.sh                            # browser checks against the demo company
```

`create-company.sh` prints the owner's password once (or set `OWNER_PASSWORD`).

## Repository layout

| Path | What |
|---|---|
| `src/` | The Jewellery module (backend + client) and PDF template |
| `scripts/` | Provision, deploy, serve, cron, backup/restore, demo seed |
| `tests/` | `unit/`, API tests (`*.test.mjs`), `ui/` |
| `docs/` | Evaluation, requirements, architecture, roles, security, testing, deployment, user guide |
| `build/`, `instances/`, `backups/` | Generated, git-ignored |

## Licence

EspoCRM is AGPL-3.0 with a §7(b) term requiring the "EspoCRM" name to remain displayed. This module is distributed under the same terms; see `docs/PLATFORM_EVALUATION.md`.
