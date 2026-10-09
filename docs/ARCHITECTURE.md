# Architecture

```
Gold_CRM/
├── src/                              ← everything we wrote (deployed into each instance)
│   ├── custom/Espo/Modules/Jewellery/
│   │   ├── Resources/metadata/       entityDefs, scopes, clientDefs, recordDefs, selectDefs, aclDefs, pdfDefs, dashlets, app
│   │   ├── Resources/layouts/        detail/list/filter layouts
│   │   ├── Resources/i18n/en_US/     labels (Account→Customer, Opportunity→Deal…)
│   │   ├── Resources/routes.json     custom API routes
│   │   ├── Api/                      convert + report endpoints
│   │   ├── Tools/                    Money (bcmath), Totals, Conversion, Reports, AccountActivity
│   │   ├── Hooks/                    totals, locks, deal outcome, follow-up tracking
│   │   ├── Classes/                  item ACL, defaults populator, primary filters, PDF data
│   │   └── Controllers/              standard record controllers
│   ├── client/custom/modules/jewellery/   dashlet, reports page, convert actions, brand.css, CSV template
│   └── templates/document.html       quotation / order PDF body
├── scripts/                          create-company, deploy-module, setup-company, serve, cron, backup, restore, seed-demo
├── tests/                            unit/ (PHP), *.test.mjs (API), ui/ (headless Chrome)
├── build/espocrm-10.0.9/             pinned pristine EspoCRM (git-ignored)
└── instances/<company>/              one EspoCRM per company (git-ignored)
```

## Tenancy model

`Company → own instance dir → own database goldcrm_<slug> → own DB user gc_<slug>` (privileges only on that DB).
Users, customers, enquiries, tasks, products, quotations, orders, files and reports live inside that database / instance `data/` folder. There is no shared table and no company ID that a browser could tamper with: a record ID from company A simply does not exist in company B (tested).

Inside a company, EspoCRM ACL (roles: own/team/all) applies to every API call, list, search, export, report query and related-record link.

## Entities

| Entity (API name) | UI name | Origin | Key fields |
|---|---|---|---|
| Account | Customer | native + fields | type, contactPerson, customerSource, tags, preferredContactMethod, customerStatus, gstin, lastInteractionAt*, nextFollowUpAt* |
| Enquiry | Enquiry | new | number ENQ-, account (req), enquiryDate, source, category, metal, purity, gemstone, quantity, budget, expected date, priority, status, nextFollowUpDate, attachments |
| Opportunity | Deal | native + fields | stage (jewellery stages), amount, closeDate, enquiry, lostReason, outcomeDate* |
| Task / Call / Meeting | Follow-ups | native + taskType | parent may be Customer, Deal, Enquiry, Quotation, Order; account derived from parent |
| JewelleryProduct | Product | new | sku (unique per non-deleted), category, metal, purity, designNumber, gemstoneDetails, image, price (indicative), unit, status |
| Quotation | Quotation | new | number QT-, account (req), enquiry, opportunity, dates, status, subtotal*/discountAmount*/taxAmount*/grandTotal*, terms |
| QuotationItem | Quotation line | new | quotation (req), product, name, quantity DECIMAL(13,3), unitPrice, discount %, tax %, lineTotal* |
| CustomerOrder | Order | new | number ORD-, account, quotation*, opportunity, orderDate, expectedDeliveryDate, status, totals* |
| CustomerOrderItem | Order line | new | same as quotation line, parent customerOrder |

`*` = read-only, computed on the server. Money is `DECIMAL(13,4)`; arithmetic uses bcmath with half-up rounding to 2 dp per component.

## Relationships

```
Account 1─* Enquiry 1─* Opportunity (enquiryId)
Account 1─* Opportunity 1─* Quotation 1─* QuotationItem *─1 JewelleryProduct
Account 1─* Quotation 1─* CustomerOrder (quotationId) 1─* CustomerOrderItem
Enquiry 1─* Quotation          Task/Call/Meeting ─parent→ any of the above
```

Foreign keys are EspoCRM link columns (`<link>_id`, indexed). EspoCRM does not create DB-level FK constraints; integrity is enforced in hooks:

| Rule | Where |
|---|---|
| Linked enquiry/deal must belong to the same customer | `Hooks/Quotation/Guard` (also orders) |
| Can't link records the user can't read | EspoCRM `LinkCheck` (native) |
| Line items only editable while quotation Draft/Sent, order Draft | `Tools/Totals::assertParentEditable` |
| Totals recomputed on every line change; client totals ignored | `Hooks/*Item/Calculate`, read-only fields |
| One order per quotation, only from Accepted, at least one line | `Tools/Conversion` (transaction + `SELECT … FOR UPDATE`) |
| Converted quotation: customer/status locked, can't be deleted | `Hooks/Quotation/Guard` |
| Deleting a quotation/order deletes its lines | Guard `afterRemove` |
| Lost needs a reason; outcome date on Won/Lost | `Hooks/Opportunity/Outcome` |
| Creator becomes owner; document dates default to today | `Classes/Record/DefaultsPopulator` |

## Indexes added

enquiry(status), enquiry(next_follow_up_date), enquiry(created_at), quotation(status), quotation(date_issued), customer_order(status), customer_order(order_date), account(customer_status), task(task_type), jewellery_product(sku, delete_id) unique — all with `deleted`.

## Server-side logic & APIs

| Endpoint | Purpose |
|---|---|
| `POST /api/v1/Enquiry/{id}/convertToDeal` | Enquiry → Deal |
| `POST /api/v1/Quotation/{id}/convertToOrder` | Accepted quotation → Order |
| `GET /api/v1/JewelleryReport/overview?period=today|week|month|year|custom&from&to` | Dashboard |
| `GET /api/v1/JewelleryReport/summary?…` | Reports page (needs Reports permission) |

All other CRUD is EspoCRM's REST API (`/api/v1/<Entity>`), same ACL as the UI.

## Migrations and rollback

- Schema changes come from metadata. `scripts/deploy-module.sh` runs `php command.php rebuild`, which **adds** tables/columns/indexes; it does not drop data columns.
- Removing or renaming a field: write it as an explicit SQL migration, review it, take a backup (`scripts/backup.sh`) first.
- Rollback: redeploy the previous `src/` version + `rebuild`; for data, `scripts/restore.sh` from the pre-change backup.

## Data retention

Records are soft-deleted (`deleted = 1`) and stay in the DB until purged by an admin. Action history and auth logs are kept 6 months (`cleanupActionHistoryPeriod`, `cleanupAuthLogPeriod`). Backups are the operator's responsibility (DEPLOYMENT.md).

## Integration points for later

REST API with API users (native), webhooks (native, admin), formula/workflow hooks in `Hooks/`, and the `Tools/` services for future WhatsApp, payments, metal rates or accounting connectors.
