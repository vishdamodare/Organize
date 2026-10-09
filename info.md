# MASTER PROMPT — Build a Complete Gem & Jewellery CRM MVP Using an Existing CRM

## 1. Your Role

Act as a senior software architect, experienced full-stack developer, SaaS product designer, database engineer, security engineer, and QA engineer.

Your task is to build a polished, functional **Gem & Jewellery CRM MVP** by leveraging an existing CRM platform rather than rebuilding standard CRM functionality from scratch.

The final product must feel like a complete, commercially usable SaaS application. It should be simple enough for a jewellery shop owner or salesperson with limited technical knowledge to use comfortably.

Do not just provide recommendations, documentation, wireframes, or sample code. Inspect the environment, choose a suitable foundation, implement the required features, run the application, and test the complete workflows.

Work autonomously wherever requirements are clear. Ask questions only when a decision genuinely blocks implementation.

---

## 2. Product Vision

Build a modern CRM for businesses in the gem and jewellery industry.

The CRM should help jewellery businesses manage:

- Customers and business relationships.
- Retailers, wholesalers, buyers, and suppliers.
- Jewellery enquiries and sales leads.
- Sales pipelines and negotiations.
- Calls, meetings, tasks, and follow-ups.
- Basic jewellery product catalogues.
- Quotations and sales orders.
- Sales performance and reports.
- Employees, permissions, and company data.

The central goal is to replace scattered spreadsheets, notebooks, and manual follow-ups with one organised workspace.

### Product principles

1. Reuse existing, reliable CRM functionality wherever possible.
2. Customise the CRM specifically for the jewellery industry.
3. Keep the interface clean, modern, intuitive, and fast.
4. Make every important button and workflow functional.
5. Use real database records rather than hardcoded demonstration metrics.
6. Build a secure architecture that can support multiple jewellery businesses.
7. Avoid unnecessary features and overengineering.
8. Keep the code maintainable and upgrades manageable.
9. Make the MVP feel complete without attempting to build a full jewellery ERP.
10. Never claim a feature is implemented or tested unless that is actually true.

---

## 3. Phase Zero: Inspect Before Building

Do not start coding immediately.

First inspect the available workspace and establish what already exists.

### A. Inspect the environment

Determine:

- Whether an existing repository or application is available.
- Which frameworks, languages, and database systems are already being used.
- Whether the current application is a CRM or a blank project.
- Whether Docker, environment configuration, database credentials, or deployment files already exist.
- Which development tools and dependencies are installed.
- Whether an existing CRM instance is available to configure or customise.

Inspect the repository, README, package manifests, dependency files, database schema, configuration examples, and existing routes where applicable.

Do not overwrite an existing application without understanding it.

### B. Evaluate existing CRM platforms

Evaluate at least these options:

1. Frappe CRM — https://github.com/frappe/crm
2. Twenty CRM — https://github.com/twentyhq/twenty
3. EspoCRM — https://github.com/espocrm/espocrm

Check their current documentation, supported features, extension mechanisms, database requirements, deployment requirements, licensing, and upgrade strategy.

Evaluate:

- Ease of customising customers, leads, deals, and tasks.
- Custom fields and relationships.
- User roles and permissions.
- Organisation-level data isolation.
- APIs and integration capabilities.
- Dashboard customisation.
- Ability to create jewellery-specific workflows.
- Hosting and maintenance complexity.
- Ability to brand and distribute the resulting product commercially.
- Total implementation effort.

Select the most suitable foundation based on evidence, not personal preference.

Prefer the platform that lets us reuse the most core CRM functionality while delivering the required user experience securely.

**Important:** Verify the actual licence and obligations before modifying or commercially distributing any selected platform. Do not assume that every open-source CRM permits unrestricted rebranding or commercial redistribution.

### C. Create an evaluation report

Before making major changes, produce:

`docs/PLATFORM_EVALUATION.md`

Include:

- Platforms evaluated.
- Comparison table.
- Selected platform and reasons.
- Licensing considerations.
- Technical architecture.
- Known limitations.
- Implementation plan.
- Risks and mitigation strategies.

Then proceed with implementation. Do not stop after writing the report.

If the current environment already contains a suitable CRM, assess whether extending it is better than replacing it.

---

## 4. Product Architecture

Design the product so it can eventually support multiple independent jewellery businesses.

Each jewellery business must have its own workspace, employees, customers, enquiries, tasks, products, quotations, orders, and reports.

### Tenant structure

Use a clear relationship such as:

Company/Organisation → Users → Customers → Enquiries → Tasks → Quotations → Orders.

Use the selected CRM's native organisation or tenancy mechanism where it is sufficient.

If additional tenant-specific entities are required, extend the existing system using its supported architecture.

Do not introduce a separate authentication system or duplicate existing CRM tables unnecessarily.

### Company-level data isolation

Every business must only access the records it is authorised to access.

Enforce isolation on the server, not merely by hiding information in the frontend.

Requirements:

- Authenticate every protected request.
- Validate the user's company membership and role on the server.
- Scope database reads and writes to the authorised organisation.
- Validate ownership for record updates, deletions, exports, attachments, and related objects.
- Prevent users from accessing another company's data by modifying IDs in URLs or API requests.
- Ensure dashboard counts, searches, filters, exports, and reports are tenant-scoped.
- Prevent cross-company access through related records, such as a quotation linked to another company's customer.
- Enforce appropriate access rules for file uploads and downloads.
- Audit important security-sensitive changes.
- Never trust a company ID supplied by the browser without validating it against the authenticated user.

If the selected CRM cannot safely support the required tenancy model, document the limitation and implement an appropriate supported extension or choose a better foundation.

Do not claim multi-tenant support is complete until isolation tests pass.

---

## 5. User Roles and Permissions

Implement the minimum roles needed for the MVP.

### Role 1: Company Owner / Admin

Can:

- View and manage their company's CRM records.
- Invite, deactivate, and manage employees.
- Assign roles and responsibilities.
- View company-wide sales reports.
- Manage products, quotations, and orders.
- Configure company information and workflow settings.
- Import and export authorised company data.

### Role 2: Salesperson

Can:

- Manage authorised customers and enquiries.
- Create and update deals.
- Record calls, notes, and meetings.
- Schedule and complete follow-ups.
- Create quotations where permitted.
- View assigned work and authorised sales information.

A salesperson must not automatically receive unrestricted administrative access.

Use record ownership and team permissions where supported.

### Role 3: Platform Administrator

This role is for the SaaS operator, not an ordinary jewellery company employee.

It may manage platform-level configuration and account support, but must not have unrestricted access to customer business data by default.

Any exceptional support access should be controlled, authorised, and audited.

Use the selected platform's native permission system where possible. Do not implement role-based security solely through frontend conditions.

---

## 6. Main Application Navigation

Create a simple, consistent sidebar.

Recommended navigation:

1. Overview
2. Customers
3. Enquiries
4. Sales Pipeline
5. Tasks & Follow-ups
6. Products
7. Quotations
8. Orders
9. Reports
10. Team
11. Settings

Only display features the current user is authorised to access.

Avoid unnecessary nested menus. Make the most frequently used actions accessible within one or two clicks.

Use the platform's native routing and navigation framework where practical.

---

## 7. Module Requirements

### Module A: Dashboard

Build a functional overview page using actual authorised database records.

Display:

- Total customers.
- New enquiries.
- Open deals.
- Follow-ups due today.
- Overdue follow-ups.
- Won deals.
- Sales value for a selected period.
- Recent activity.
- Upcoming tasks.
- Sales pipeline by stage.

Add useful date filters, such as:

- Today.
- This week.
- This month.
- Custom date range, if supported.

Clicking a metric should open the relevant filtered records.

Ensure the calculations have clearly defined meanings. For example, distinguish deal value from actual completed sales revenue.

Handle empty states correctly. If a company has no customers, show a helpful message and an action to add its first customer.

Never use hardcoded numbers or fake activity in production dashboards.

### Module B: Customers and Business Profiles

Build a searchable customer directory.

Support these customer types:

- Retail jewellery shop.
- Wholesaler.
- Manufacturer.
- Individual buyer.
- Supplier.
- Other business.

A customer record should support:

- Customer or business name.
- Contact person's name.
- Phone number.
- Email address.
- Business type.
- City and state.
- Business address.
- Customer source.
- Assigned salesperson.
- Tags.
- Notes.
- Preferred contact method.
- Status.
- Creation date and last interaction.
- Optional GSTIN, when relevant.

Use validation for phone numbers, email addresses, and other relevant fields.

Do not make sensitive business information publicly accessible.

### Customer detail page

Create a well-organised customer profile with sections or tabs for:

- Overview.
- Contact details.
- Enquiries and deals.
- Calls and meetings.
- Notes and activity timeline.
- Quotations.
- Orders.

Show the next follow-up and recent interaction prominently.

Avoid creating duplicate customers unnecessarily. Provide duplicate warnings based on useful matching fields, such as phone number or email address.

Allow users to search, filter, sort, paginate, and export authorised customer records.

### Module C: Leads and Jewellery Enquiries

Create a structured enquiry workflow.

Each enquiry should contain:

- Enquiry reference number.
- Customer.
- Enquiry date.
- Lead source.
- Jewellery category.
- Product or design description.
- Metal type, if applicable.
- Purity, if applicable.
- Gemstone type, if applicable.
- Quantity.
- Approximate budget.
- Expected purchase date.
- Assigned salesperson.
- Priority.
- Status.
- Next follow-up date.
- Notes and attachments, if supported.

Use optional fields for attributes that do not apply to every enquiry.

For the MVP, support categories such as gold jewellery, diamond jewellery, silver jewellery, gemstones, bridal jewellery, and custom designs.

Allow an enquiry to be converted into a deal without losing its history or creating unnecessary duplicate records.

### Module D: Sales Pipeline

Create a visual Kanban pipeline.

Suggested stages:

New → Contacted → Qualified → Quotation Sent → Negotiation → Won / Lost.

Allow the company to configure stages where the platform supports it.

Each deal should contain:

- Deal title.
- Customer.
- Linked enquiry.
- Expected value.
- Expected closing date.
- Assigned salesperson.
- Current stage.
- Probability, if useful.
- Notes.
- Next activity.

Users should be able to move deals between stages using the platform's supported interaction patterns.

When a deal is marked Won or Lost:

- Record the outcome.
- Record the date.
- Allow an appropriate lost reason.
- Update reports correctly.
- Preserve the deal's history.

Do not treat every Won deal as a completed or paid order. Keep deal status and order/payment status conceptually separate.

### Module E: Tasks and Follow-ups

This is a critical feature for jewellery sales.

Users should be able to create:

- Phone calls.
- Meetings.
- Customer follow-ups.
- General tasks.
- Quotation follow-ups.

Each task should support:

- Title.
- Task type.
- Linked customer.
- Linked deal, if applicable.
- Assigned user.
- Due date and time.
- Priority.
- Status.
- Notes.
- Completion timestamp.

Provide filters for:

- My tasks.
- Due today.
- Upcoming.
- Overdue.
- Completed.

Provide useful reminders through the platform's supported capabilities. Do not promise push notifications or automated messaging unless they are implemented and tested.

Prevent completed tasks from remaining incorrectly listed as overdue.

### Module F: Products and Jewellery Catalogue

Create a basic product catalogue for reference and quotations.

Each product can include:

- Product name.
- SKU or reference code.
- Jewellery category.
- Metal type.
- Purity.
- Design or model number.
- Gemstone details.
- Product description.
- Image.
- Indicative selling price.
- Unit of measurement.
- Active or inactive status.

Support multiple images only if the platform's existing attachment system supports them safely.

For the MVP, this is a catalogue, not a complete stock-management system.

Do not claim that the application tracks real-time stock, metal rates, manufacturing costs, or inventory valuation.

Clearly distinguish indicative prices from final customer quotations.

### Module G: Quotations

Implement basic quotation creation.

A quotation should contain:

- Unique quotation number.
- Customer.
- Linked enquiry or deal.
- Issue date.
- Expiry date.
- Currency.
- Line items.
- Quantity.
- Unit price.
- Applicable discount.
- Tax fields where configured and legally appropriate.
- Subtotal.
- Total.
- Notes and terms.
- Created by.
- Quotation status.

Suggested statuses:

Draft, Sent, Accepted, Rejected, Expired.

Calculate totals on the server or through the platform's trusted business logic. Never rely exclusively on client-submitted totals.

Use appropriate decimal handling for monetary values.

Provide a printable or downloadable quotation document using a supported PDF or document-generation mechanism.

Make the quotation visually professional, with company branding, customer details, itemised pricing, and terms.

Do not represent this basic quotation module as a complete GST-compliant accounting system.

### Module H: Orders

Allow a quotation or accepted deal to be converted into an order through a controlled workflow.

An order should include:

- Unique order number.
- Customer.
- Linked quotation or deal.
- Order date.
- Expected delivery date.
- Line items.
- Quantities.
- Order value.
- Status.
- Assigned salesperson.
- Notes.
- Creation and modification history.

Suggested statuses:

Draft, Confirmed, In Progress, Ready for Delivery, Delivered, Cancelled.

Allow statuses to be adjusted to suit the chosen workflow, if practical.

Prevent accidental duplicate conversion into orders. Define how edits to accepted quotations affect orders.

Do not introduce inventory deductions, manufacturing workflows, payment reconciliation, or delivery integrations in this MVP.

### Module I: Reports

Build practical business reports using actual database records.

Include:

- Enquiries by source.
- Enquiries by status.
- Deals by stage.
- Won versus lost deals.
- Salesperson performance.
- Follow-up completion.
- Overdue tasks.
- Quotations sent and accepted.
- Orders by status.
- Sales value over time.

Provide date filters and export options where supported.

Clearly define report calculations. For example, use the appropriate order or deal records for each metric instead of treating all pipeline value as revenue.

Ensure every report respects company and user permissions.

### Module J: Team Management

Provide a team page showing:

- Employee name.
- Email.
- Role.
- Account status.
- Assigned tasks or workload, where supported.

Allow authorised company admins to invite employees, assign roles, and deactivate access.

Use secure invitation and password-reset mechanisms supported by the existing platform.

Do not create insecure custom password storage or authentication flows.

Deactivated users must no longer be able to authenticate or perform protected actions.

### Module K: Company Settings

Provide settings for:

- Company name.
- Company logo.
- Business address.
- Contact information.
- Currency.
- Time zone.
- Date format.
- Quotation numbering.
- Default quotation terms.
- Supported workflow settings.

Use company-level settings rather than hardcoding a single jewellery business's details into the application.

For India-focused deployments, INR can be the default currency, but money handling should remain consistent and extensible.

---

## 8. UI/UX and Branding

The application must look like a finished B2B SaaS product, not a developer dashboard.

### Design direction

Use a modern, clean, understated visual identity.

Suggested palette:

- Deep navy: `#132244`
- Warm gold: `#C6963B`
- White: `#FFFFFF`
- Soft neutral backgrounds.
- Dark readable typography.
- Subtle borders and shadows.

Use gold as an accent, not as the dominant background colour.

Avoid excessive gradients, oversized decorative graphics, excessive animations, and unnecessary visual clutter.

### Interface requirements

- Consistent typography and spacing.
- Clean sidebar and header.
- Clear page titles and primary actions.
- Reusable cards, forms, tables, badges, and modals.
- Readable tables with appropriate column widths.
- Search and filters positioned consistently.
- Helpful empty states.
- Loading indicators.
- Clear validation errors.
- Success and failure feedback.
- Confirmation for destructive actions.
- Responsive layouts for desktop, tablet, and mobile.
- Accessible colour contrast, keyboard navigation, and form labels.

### Dashboard layout

Prioritise information rather than decoration.

Recommended structure:

1. Welcome message and primary actions.
2. Key business metrics.
3. Sales pipeline overview.
4. Follow-ups due today.
5. Upcoming activities.
6. Recent customer interactions.

Use charts only where they provide useful information.

### Customer profile layout

Use a clear two-column layout on larger screens where appropriate:

- Main area: customer details, enquiries, notes, and activity timeline.
- Secondary area: contact information, owner, customer status, and next follow-up.

Adapt gracefully to mobile screens.

### Important rule

Use the existing CRM's supported theme, layout, and extension systems first. Avoid patching core source files unnecessarily.

Do not replace working platform components simply to achieve minor visual differences.

---

## 9. Database and Data Integrity

Before creating any new database entities, map the existing CRM's data model.

Reuse native entities wherever practical.

Create custom entities or fields only when the jewellery workflow requires them.

Document:

- Entity relationships.
- Required fields.
- Unique identifiers.
- Foreign-key relationships.
- Organisation ownership.
- Indexes.
- Validation rules.
- Data-retention requirements.
- Migration and rollback procedures.

Ensure:

- No orphaned records from ordinary operations.
- No accidental duplicate orders.
- No inconsistent quotation totals.
- No cross-company relationships.
- No silent loss of customer history.
- No destructive schema changes without a migration.
- No hardcoded production credentials.

Use transactions for operations that must succeed or fail together.

Use the platform's supported migration mechanism. Do not manually alter production database schemas without a reviewed migration and backup.

---

## 10. Security Requirements

Treat security as a core requirement.

Implement and test the protections appropriate to the selected CRM architecture.

At minimum:

- Server-side authentication and authorisation.
- Organisation-level data isolation.
- Role and record permissions.
- Input validation and output encoding.
- SQL injection protection through supported query mechanisms.
- CSRF protection where applicable.
- Secure session and cookie configuration.
- Rate limiting for sensitive authentication endpoints where supported.
- Secure password-reset and invitation workflows.
- Safe file upload and download permissions.
- Protection against insecure direct object references.
- Secure secret management.
- Appropriate error handling without exposing credentials or internal stack traces.
- Audit logging for important administrative changes.
- Dependency and configuration review.
- Production HTTPS.

Do not store plaintext passwords, API secrets, or unnecessary sensitive payment information.

Do not log passwords, authentication tokens, or secrets.

Do not introduce custom authentication if the chosen CRM already provides a secure solution.

Create automated tests that attempt to access another company's customers, deals, quotations, orders, files, and reports using manipulated record IDs.

These requests must be denied without leaking protected information.

---

## 11. Integrations: MVP Boundaries

Keep external integrations limited initially.

### Include

- Existing CRM email or notification capabilities, if straightforward.
- CSV import and export.
- Printable quotation or PDF generation.
- Existing supported APIs.

### Defer

- WhatsApp Business API automation.
- Bulk marketing campaigns.
- Payment gateway integration.
- Live gold and silver prices.
- Accounting software synchronisation.
- Barcode scanners.
- Manufacturing systems.
- AI-based sales recommendations.

Do not build integrations with fake API responses presented as real functionality.

Document integration points so they can be added later without restructuring the entire product.

---

## 12. Data Import and Onboarding

A CRM is not useful if a jewellery business cannot move its existing customers into it.

Implement a simple onboarding flow.

The company should be able to:

1. Create its company profile.
2. Add its first employee.
3. Import a customer CSV.
4. Review and map CSV columns.
5. Validate the data.
6. Preview records before importing.
7. Identify invalid or duplicate records.
8. Import valid records.
9. Review an import summary.

Do not silently discard invalid rows.

Provide a downloadable sample CSV template and clear error messages.

Respect company-level permissions during import and export.

If the platform already provides a reliable import workflow, customise or document it instead of building a duplicate system.

---

## 13. Testing Requirements

Do not consider the project complete just because the pages load.

Use the selected platform's existing test framework and add automated tests for custom behaviour.

### Functional tests

Verify that:

- A user can log in and access the correct company workspace.
- A company admin can add a customer.
- A customer can have multiple enquiries.
- An enquiry can be moved through the pipeline.
- A deal can be marked Won or Lost.
- A follow-up can be scheduled and completed.
- A quotation can be created and its totals calculated correctly.
- An accepted quotation can be converted into an order.
- Reports reflect database changes correctly.
- CSV imports validate and preserve data.
- Search and filters return the correct records.
- Deactivated users lose access.

### Security tests

Verify that:

- Users cannot access another company's records.
- Changing a URL or record ID does not bypass permissions.
- API requests enforce the same permissions as the UI.
- Exports and attachments cannot bypass company isolation.
- Salespeople cannot perform restricted administrative actions.
- Invalid or unauthorised changes are rejected.
- Sensitive errors do not reveal internal secrets.

### UI tests

Verify that:

- All navigation links work.
- Forms display validation errors.
- Buttons perform the actions they advertise.
- Loading and empty states work.
- Tables are usable on mobile.
- Modals can be closed correctly.
- Destructive actions require appropriate confirmation.

Use browser automation or end-to-end tests if available.

Clearly report which tests actually ran, which passed, and which remain incomplete.

---

## 14. Development and Deployment

Use a clean development workflow.

### Environment setup

- Keep local, staging, and production configurations separate.
- Use environment variables for secrets.
- Provide an example environment file without real credentials.
- Document database setup.
- Document migrations.
- Document the required dependencies.
- Provide clear local startup instructions.
- Provide health checks where supported.

### Deployment

Prepare the project for a controlled deployment to an appropriate hosting environment.

Document:

- Required server resources and dependencies.
- Database configuration.
- Domain and HTTPS requirements.
- Migration procedure.
- Backup and restoration procedure.
- Scheduled jobs, if any.
- Logs and monitoring.
- Upgrade procedure.
- Rollback procedure.

Do not make unrequested changes to an existing production system.

Never expose production credentials or copy production customer data into development without appropriate authorisation and safeguards.

If the environment does not permit installation or execution, state the exact limitation and provide the next actionable step rather than pretending deployment succeeded.

---

## 15. Deliverables

Produce the following documentation and implementation artifacts as applicable to the selected platform:

1. `docs/PLATFORM_EVALUATION.md` — platform comparison and decision.
2. `docs/PRODUCT_REQUIREMENTS.md` — agreed MVP features and exclusions.
3. `docs/ARCHITECTURE.md` — architecture and entity relationships.
4. `docs/USER_ROLES.md` — roles and permission matrix.
5. `docs/SECURITY.md` — security model and known risks.
6. `docs/TESTING.md` — tests and actual results.
7. `docs/DEPLOYMENT.md` — setup and deployment instructions.
8. `docs/USER_GUIDE.md` — simple instructions for jewellery business owners and salespeople.
9. Database migrations and configuration changes.
10. The working, customised CRM application.
11. Automated tests for critical workflows and permissions.
12. A list of known limitations and deferred features.

Do not create documentation that claims functionality exists when it has not been implemented.

---

## 16. Definition of Done

The MVP is ready for pilot use only when all applicable conditions below are satisfied.

### Product

- The selected CRM foundation is documented and running.
- Branding and navigation are consistent.
- Customer management works.
- Jewellery enquiries work.
- The sales pipeline works.
- Tasks and follow-ups work.
- Basic product management works.
- Quotations can be created and totals are correct.
- Orders can be created through the intended workflow.
- Dashboard and reports use real data.
- Team management and permissions work.
- CSV import and export work.
- Empty states and validation are handled properly.

### Security

- Organisation-level data isolation has been tested.
- Restricted actions are enforced by the backend.
- API and file access permissions have been checked.
- Secrets are not committed to source control.
- Known security limitations are documented.

### Quality

- Critical workflows pass automated or documented manual tests.
- No known critical defects remain unresolved.
- The application has been tested with realistic jewellery-business data.
- The UI works on desktop and mobile.
- Setup, backup, migration, and deployment instructions are available.

### Operational readiness

- A jewellery business can be onboarded.
- Its users can perform their normal daily work.
- Its records remain isolated from other businesses.
- The team can back up and restore the application and database.
- Known limitations are disclosed.

---

## 17. How You Should Execute This Task

Follow this order.

**Phase 1 — Inspect and evaluate**
- Inspect the environment.
- Evaluate existing CRM foundations.
- Choose the best fit.
- Document the decision.

**Phase 2 — Establish the application**
- Set up the selected CRM.
- Confirm that it runs.
- Understand its data model and extension mechanisms.
- Establish development and testing environments.

**Phase 3 — Implement the MVP**
- Configure company and user access.
- Customise customer records and jewellery enquiries.
- Implement the sales pipeline and follow-ups.
- Add products, quotations, and orders.
- Implement the dashboard, reports, and import workflow.
- Apply consistent branding and UI improvements.

**Phase 4 — Secure and test**
- Test company-level isolation.
- Test role permissions.
- Test all critical workflows.
- Fix defects and rerun tests.

**Phase 5 — Prepare for pilot**
- Finalise documentation.
- Provide setup and deployment instructions.
- List completed features, deferred features, and known risks.
- Prepare a realistic demo dataset only for development or demonstration environments.

At the end of each phase, summarise the actual work completed and continue to the next phase unless a real blocker requires my input.

Do not stop after producing a plan.

Do not build every component from scratch when the chosen CRM already has suitable functionality.

Do not add unrelated features merely to make the project appear larger.

Do not sacrifice data isolation, correctness, or usability to meet a superficial deadline.

If a requested feature is too expensive or risky for the MVP, explain the trade-off and implement the simplest safe alternative.

---

## 18. Final Response Required From You

When implementation is complete, provide a concise but specific report containing:

1. Which CRM was selected and why.
2. The existing features reused.
3. The custom jewellery features implemented.
4. The important files and migrations changed.
5. How to run the application locally.
6. How to access the application.
7. How to create the first company and admin user.
8. How to test the main customer-to-order workflow.
9. The automated tests executed and their actual results.
10. Security checks completed and any remaining risks.
11. How to deploy the product.
12. What has deliberately been deferred to later releases.

Clearly distinguish implemented, tested, partially implemented, and not implemented features.

**Start now by inspecting the existing environment and evaluating the CRM foundations. Then implement the best suitable solution. Build a usable product, not just a demonstration.**