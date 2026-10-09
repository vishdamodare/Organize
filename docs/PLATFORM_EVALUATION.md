# Platform Evaluation

## Environment found (2026-10-09)

| Item | Found |
|---|---|
| Repository | Blank folder containing only `info.md` (no existing app, no git) |
| Runtimes | PHP 8.5 (all needed extensions incl. pdo_mysql, gd, intl, bcmath, zip), Node 26, Python **3.9** |
| Databases | MariaDB 12.3 running locally (root via socket); **no PostgreSQL, no Redis** |
| Containers | **No Docker** |

## Options evaluated

| Criterion | Frappe CRM | Twenty CRM | **EspoCRM 10.0.9** |
|---|---|---|---|
| Stack | Python/Frappe + Vue, bench | Node/NestJS + React | PHP + JS (Backbone/ES classes) |
| Needs | Python ≥3.10, MariaDB, Redis, Node, bench | PostgreSQL, Redis, usually Docker | PHP ≥8.3, MySQL/MariaDB |
| Runs here without new infrastructure | No (Python 3.9, no Redis) | No (no Postgres/Redis/Docker) | **Yes** – verified by installing and running it |
| Custom entities/fields/relations | Yes (DocTypes) | Yes (data model UI, objects) | Yes (metadata + Entity Manager, module system) |
| Roles & record permissions | Yes | Basic roles; record-level limited | **Yes**: roles with own/team/all per action, field-level ACL |
| Tenancy | Site per tenant (bench multi-site) | Native workspaces in one DB | No in-instance multi-tenancy → **instance + DB per company** |
| SaaS hardening for tenant admins | — | — | **`restrictedMode` + super-admin** built for hosted use (verified in source) |
| Quotes/PDF | Via ERPNext | Not built-in | Native PDF templates (dompdf); quotes are a paid add-on → built in our module |
| Import/export | Yes | Yes | Native CSV import with mapping, preview, error list, revert |
| Kanban pipeline | Yes | Yes | Yes (native) |
| Extension without core patching | Apps | Limited for deep logic | **Modules** (metadata, hooks, API actions, client views) |
| Licence | AGPL-3.0 | AGPL-3.0 core; some files under a commercial licence | AGPL-3.0 **+ §7(b): UI must keep displaying the word "EspoCRM"** |

## Decision: EspoCRM 10.0.9

1. It runs on what is already installed; the other two need new infrastructure (Redis/Postgres/Docker or a newer Python).
2. Its role/record ACL, stream/audit, import, PDF, Kanban, numbering and duplicate checks cover most of the brief natively.
3. `restrictedMode` + `isSuperAdmin` exist specifically for hosting many customers' instances: company owners administer their workspace but cannot install code, upgrade, or change system paths.
4. The module system lets all jewellery logic live in `src/` without editing core files, so EspoCRM upgrades remain manageable.

## Licensing considerations (verified for EspoCRM; re-verify all before commercial launch)

- Verified in the downloaded release: `LICENSE.txt` is AGPL-3.0; `about.md` adds a §7(b) term — *"Appropriate Legal Notices must retain the display of the 'EspoCRM' word."* We keep the EspoCRM logo/footer; our product name "Organize" is used alongside it, not instead of it.
- AGPL: users interacting with the hosted service are entitled to the source of the modified program, **including our `src/` module**. Plan to publish it (e.g. a public repo link in the About page) — this is a business decision to confirm with legal counsel.
- Frappe/Twenty licence statements above come from their public repositories, not from a review done in this session; re-check if reconsidering them.
- Paid EspoCRM extensions (e.g. Sales Pack) are not used; our entity names (`Quotation`, `CustomerOrder`, `JewelleryProduct`) avoid clashing with Sales Pack's `Quote`/`SalesOrder`/`Product`.

## Technical architecture (summary — see ARCHITECTURE.md)

One shared, unmodified EspoCRM release in `build/`; each company gets a copy in `instances/<slug>/` with **its own database and its own DB user**; the `Jewellery` module from `src/` is deployed into every instance.

## Known limitations of this choice

- Isolation is per instance: each company costs one PHP code copy (~170 MB) and one database. Fine for tens of companies; for hundreds, move to hard-linked/shared code (see DEPLOYMENT.md).
- No cross-company "platform dashboard" in-app; the operator works from the server.
- Company owners are EspoCRM admins of their own instance (restricted mode). They can change roles, layouts and fields of their own workspace.

## Implementation plan (executed)

1. Pin and install EspoCRM via CLI; add `php -S` router for local use.
2. Build module: entities, layouts, hooks, conversions, reports API, dashlet, reports page, brand CSS, PDF templates.
3. Provisioning scripts (create company, deploy module, backup/restore, cron).
4. Automated unit + API tests across two isolated companies; browser checks; demo data.
5. Documentation.

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| Owner (admin) misconfigures roles | Salesperson role is (re)applied by `setup-company.php`; documented in USER_ROLES.md |
| Core upgrade breaks module | Module uses only metadata + public extension points; upgrade one instance, run `tests/run.sh`, then roll out |
| Many instances to operate | Scripts loop over `instances/*`; backups per company |
| AGPL obligations | Publish module source; keep EspoCRM attribution |
