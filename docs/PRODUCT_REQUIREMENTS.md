# Product Requirements — Organize MVP

Organize is a CRM for gem & jewellery businesses (retailers, wholesalers, manufacturers), built on EspoCRM.

Status legend: **Done** = implemented and covered by an automated test or a recorded check · **Done (manual)** = implemented and checked in the browser, no automated test · **Native** = provided by EspoCRM, configured not rebuilt · **Partial** · **Not in MVP**.

## In scope

| Module | Requirement | Status |
|---|---|---|
| Overview | KPI tiles from live data: customers, new enquiries, open deals (+pipeline value), follow-ups due today, overdue, won deals (+value), order value | Done |
| | Today / week / month / year / custom range | Done |
| | Tiles link to filtered lists | Done (period not carried into the list – see limitations) |
| | Pipeline by stage, upcoming follow-ups, recent activity (native Stream dashlet), my tasks dashlet | Done |
| | Empty state with "add first customer / import / template / add employee / settings" | Done (manual) |
| Customers | Jewellery business types, contact person, phone, email, address/city/state, source, assigned salesperson, tags, notes, preferred contact, status, GSTIN, created, last interaction, next follow-up | Done |
| | Phone/email/GSTIN validation | Done |
| | Duplicate warning on name / email / phone | Done |
| | Profile: two columns; tabs Timeline / Enquiries & Deals / Quotations & Orders; activities, history, tasks side panels | Done (manual) |
| | Search, filter, sort, paginate | Native |
| | Export (owners) | Native |
| Enquiries | Ref no. ENQ-#####, customer, date, source, category, description, metal, purity, gemstone, quantity, budget, expected purchase date, owner, priority, status, next follow-up, attachments | Done |
| | Convert to deal once, keeping the enquiry (status Converted) | Done |
| Pipeline | Kanban New → Contacted → Qualified → Quotation Sent → Negotiation → Won / Lost | Native + configured |
| | Won/Lost records outcome date; Lost requires reason; Won ≠ order | Done |
| | Stages configurable | Native (Entity Manager → Deal → Stage) |
| Tasks | Types: customer follow-up, phone call, meeting, quotation follow-up, general | Done |
| | Linked customer (derived), linked deal/enquiry/quotation/order, assignee, due, priority, status, notes, completion time | Done |
| | Filters: open, due today, upcoming, overdue, completed, "only my" | Done |
| | Completed tasks never overdue | Done |
| | Reminders | Native (popup/email reminders; email needs SMTP + cron) |
| Products | Name, SKU (unique), category, metal, purity, design no., gemstone details, description, image, indicative price, unit, active | Done |
| Quotations | QT-##### numbering, customer, enquiry/deal link, issue/expiry date, INR, line items with qty, unit price, discount %, tax %, server-side totals (decimal), terms, notes, created by, status | Done |
| | PDF with company letterhead, customer block, items, totals, terms | Done |
| Orders | ORD-#####, created only from an Accepted quotation, items copied, statuses Draft → Delivered/Cancelled, history | Done |
| | No duplicate conversion (row lock + check) | Done |
| Reports | Enquiries by source/status/category, deals by stage, won vs lost, lost reasons, salesperson performance, follow-up completion, overdue, quotations sent/accepted, orders by status, sales over time; date filters; CSV export | Done |
| Team | List, invite (create user + role), deactivate | Native |
| Settings | Company name, address, phone, email, GSTIN, default tax %, default quotation terms | Done |
| | Logo, currency, time zone, date format, numbering | Native (Branding / Settings / Entity Manager) |
| Import | Column mapping, preview, validation, duplicates, error rows, summary, revert; sample CSV | Native + template |

## Explicitly out of scope (MVP)

WhatsApp automation, bulk campaigns, payments, live metal rates, accounting sync, barcode, manufacturing, AI suggestions, stock/inventory, GST-compliant invoicing, multi-currency quoting (INR only by configuration), deal → order without a quotation.

## Limitations

- Dashboard tiles open the matching filtered list but not restricted to the selected period.
- Quotation→order is the only order path; to change an accepted quotation, set it back to Draft before conversion, or create a new quotation after.
- Salespeople read all company customers/enquiries/deals/quotations and edit their own; owners can tighten this to "own" in Roles.
