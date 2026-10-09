# Security Model

## Controls in place

| Requirement | How |
|---|---|
| Authentication | EspoCRM native (hashed passwords, auth tokens, optional 2FA). No custom auth was written. |
| Company isolation | Separate instance + database + DB user per company. Tested: B's owner gets 403/404 for A's customers, enquiries, deals, quotations, orders, line items, attachments; searches/exports/reports in B contain nothing from A. |
| Authorisation | EspoCRM roles (own/all per action), field ACL; custom endpoints call `Acl` before acting; item ACL inherits from the parent quotation/order (`Classes/Acl/ItemAccessChecker`). |
| IDOR | Every record access goes through ACL; custom endpoints return 404 for unreadable IDs (no existence leak). |
| Related-record tampering | Native `LinkCheck` + same-customer checks in hooks. |
| Totals tampering | Totals fields are read-only and recomputed server-side. |
| Duplicate orders | Transaction + row lock (`FOR UPDATE`) + existence check; tested with parallel requests. |
| SQL injection | Only EspoCRM ORM/query builder; no hand-written SQL strings with input. |
| XSS / output encoding | Handlebars escaping in templates; PDF text fields escaped with `htmlspecialchars` before line breaks are added. |
| CSV injection | Report CSV export prefixes cells starting with `= + - @` with `'`. |
| CSRF | EspoCRM design: API calls authenticate with a request header set by the app (Espo-Authorization), which a cross-site form cannot add. Not separately tested here. |
| Brute force | `authMaxFailedAttemptNumber=10` per `60 seconds` (native lockout). |
| Password policy | ≥10 chars, letters and digits. |
| Tenant admins can't run code | `restrictedMode`, `adminExtensionUpload=false`, `adminUpgradeDisabled=true` (tested). |
| Secrets | `.env` and instance `data/config-internal.php` are git-ignored; DB passwords are random per company; owner password generated or provided via env and printed once. |
| Error leakage | Tested: 4xx responses contain no paths, traces, SQL or passwords. |
| Audit | Stream + audited fields (status, totals, customer, stage), Action History, Auth Log (6-month retention). |
| File access | EspoCRM attachment ACL follows the parent record; uploads stored under the company's own `data/upload`. |

## Known risks / limitations

1. **Dev server**: `scripts/serve.sh` uses PHP's built-in server — local use only. Production must use nginx/Apache + PHP-FPM with HTTPS (DEPLOYMENT.md).
2. **Installer password on command line**: `create-company.sh` passes the owner password to EspoCRM's CLI installer as an argument (briefly visible in the process list). Run provisioning on a host without untrusted local users, and have owners change the password at first login.
3. **Operator access**: anyone with server/DB access can read company data. This is a procedural control (access logging, least privilege), not a technical one.
4. **Malformed JSON** bodies get a generic HTTP 500 from EspoCRM core (no details leaked).
5. **Email**: invitations and password-reset emails need SMTP configured per company (Administration → Outbound Emails). Until then, owners set initial passwords themselves.
6. **Salesperson read scope**: salespeople can read all company customers/deals by default (common in small shops). Tighten to "own"/"team" in Roles if needed.
7. **Backups** contain customer data and DB credentials; store them encrypted and off-server.
8. **Dependency review**: EspoCRM 10.0.9 ships its own vendor libraries; track EspoCRM security releases and upgrade per DEPLOYMENT.md. No third-party packages were added by this project.
9. **HTTPS** is not configured locally; it is a hard requirement for any non-local deployment.
