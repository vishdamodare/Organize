# Testing — what ran and the actual results

Run everything: `tests/run.sh` (creates two test companies on first run: `testalpha` :8091, `testbeta` :8092).

## Results of the last run (2026-10-09)

| Suite | Result |
|---|---|
| `tests/unit/money_test.php` — line/total maths, rounding, input rejection | **10/10 passed** |
| `tests/workflow.test.mjs` — functional API tests | **13/13 passed** |
| `tests/security.test.mjs` — isolation & permission tests | **10/10 passed** |
| `tests/ui/` — headless Chrome interaction checks (demo company) | **7/7 passed** |

### Functional (API)
Owner logs into the correct workspace · customer create + email/GSTIN validation + duplicate warning (phone) · customer with 2 enquiries · enquiry → deal once (second attempt 409), enquiry kept as Converted · deal through stages, Lost requires reason, Won/Lost set outcome date, Won creates no order · follow-up create/complete, completed task leaves "overdue", upcoming filter, customer next-follow-up/last-interaction updated · quotation totals (25,001.00 − 1,000.05 + 720.03 = 24,720.98) with tampered totals ignored and negative price rejected · draft can't convert; accepted converts once (also two parallel requests → both 409); lines locked; converted quotation can't change status or be deleted · PDF generated (`%PDF`) · reports change when an order is confirmed (+24,720.98), won/lost, quotations, salespeople, custom range, bad range 400 · search & filters · CSV import: 2 valid rows imported, 1 invalid row reported, values preserved · deactivated user gets 401 immediately.

### Security (API, two companies)
Unauthenticated 401 · cross-company credentials · B cannot read/update/delete A's customer, enquiry, deal, quotation, order; cannot convert A's quotation or list its lines · B cannot link to A's customer or add a line to A's quotation · attachment download blocked across companies · B's search, global search, export and reports contain nothing from A · salesperson blocked from users, settings, roles, reports, import, export, delete, products, order conversion · salesperson edits only own records; tasks private; dashboard respects ACL; can't add lines to others' quotations · restricted mode blocks extension upload and upgrade · error responses leak no paths/traces/SQL/passwords.

### UI (headless Chrome, `tests/ui/run.sh`)
Convert-to-Deal action present → confirmation modal → navigates to new deal · empty customer form shows required-field errors · Cancel leaves the form · quick-create modal opens and closes. Screenshots at 1366 px and 390 px reviewed manually: overview, customers, customer profile, pipeline Kanban, enquiries, tasks, quotation, reports; salesperson view hides Reports/Team/Settings. No JavaScript errors apart from EspoCRM's own "invalid" promise rejection when a save is blocked by validation.

## Not covered / incomplete

- No automated test for: Kanban drag-and-drop, PDF visual layout (checked by eye once), dashboard tile links, email reminders (SMTP not configured), CSV *preview UI* (native, checked only via API), keyboard-only navigation, colour contrast measurement.
- Performance/load testing was not done.
- Tests ran on macOS with PHP 8.5 / MariaDB 12; EspoCRM officially lists PHP 8.3+. Validate on the production stack.
