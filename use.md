# How to Use Organize — Simple Guide

Organize is like a digital diary for your jewellery shop. It remembers your customers, what they want, when to call them back, the prices you quoted, and the orders you took. No more notebooks or Excel sheets.

---

## 1. Starting the app

Your computer runs the app. Each time you restart your Mac, start it again:

1. Open the **Terminal** app.
2. Type this and press Enter:
   ```
   cd ~/Desktop/Gold_CRM && scripts/serve.sh demo 8080
   ```
3. Leave that Terminal window open (closing it stops the app).
4. Open Chrome or Safari and go to: **http://localhost:8080**

## 2. Signing in

| Who | Username | Password |
|---|---|---|
| Owner (you) | `owner` | `Demo-Owner-2026` |
| Salesperson | `priya` or `arjun` | `Demo-Sales-2026` |

- Type the **username in the top box** and the **password in the bottom box**.
- Change your password: click your name/icon at the top-right → **Preferences** → change password.

## 3. The left menu — what each item means

| Menu | What it is (in simple words) |
|---|---|
| **Overview** | Your home page. Numbers for today/this month: customers, enquiries, follow-ups, sales. |
| **Customers** | Your list of people and shops who buy from you (or sell to you). |
| **Enquiries** | "Someone asked for something" — e.g. *"Mrs. Desai wants a 22K bridal necklace, budget ₹3 lakh."* |
| **Sales Pipeline** | Enquiries that became serious. Shown as cards in columns, from *New* to *Won/Lost*. |
| **Tasks & Follow-ups** | Your to-do list: calls to make, meetings, reminders. |
| **Products** | Your catalogue: designs, SKU codes, purity, approximate price. |
| **Quotations** | Price offers you give to a customer (can print as PDF). |
| **Orders** | Confirmed orders, made from an accepted quotation. |
| **Reports** | Tables: where enquiries come from, who sold most, how much sales. |
| **Team** | Your staff. Add or remove employees. (Owner only) |
| **Settings** | Your shop name, address, GSTIN, tax %, quotation terms. (Owner only) |

---

## 4. First-time setup (do once)

**Step 1 — Fill your shop details**
Click **Settings** → **Edit** → enter shop name, address, phone, email, GSTIN, default tax % (e.g. 3) and your quotation terms → **Save**.
These details print on every quotation.

**Step 2 — Add your staff**
Click **Team** → **Create User** → fill name, username, email, password → in **Roles** pick **Salesperson** → **Save**.
Tell them their username and password.
If someone leaves: open their name → **Edit** → untick **Is Active** → **Save**. They can't log in any more.

**Step 3 — Add your customers**
- *One by one:* **Customers** → **New Customer** → fill the form → **Save**.
- *Many at once from Excel:* On **Overview**, click **Download CSV template** (this button shows while you have no customers; you can also find the file at `src/client/custom/modules/jewellery/res/customer-import-template.csv`). Fill it in Excel, then **File → Save As → CSV**. In Organize open the import page (on the Overview page, or **⋯ (top-right menu) → Import**), choose the file, match the columns, check the preview, and click **Import**. At the end it shows how many rows were added and which rows had mistakes.

**Step 4 — Add your products** (optional)
**Products** → **New Product** → name, SKU code, category, metal, purity, price → **Save**.

---

## 5. Everyday work — the full journey

Example: a customer walks in asking for a bridal necklace.

### ① Save the customer
**Customers** → **New Customer** → name, phone, city → **Save**.
If the phone number already exists, Organize warns you, so you don't create the same customer twice.

### ② Write down what they want (Enquiry)
**Enquiries** → **New Enquiry** (or open the customer → **Enquiries & Deals** tab → **+**).
Fill in: what they want, customer, category (Bridal), metal (Gold), purity (22K), budget, and the **next follow-up date** → **Save**.
You can attach photos or sketches.

### ③ Set a reminder to call back (Follow-up)
**Tasks & Follow-ups** → **New Follow-up** → e.g. *"Call Mrs. Desai about design"*, type = Phone Call, due date & time, *Related To* = the customer → **Save**.
When it's done, open it and change **Status** to **Completed**.
Overdue items show up in red on the Overview page so nothing is forgotten.

### ④ When the customer is serious → make it a Deal
Open the enquiry → click **⋯** (three dots, next to Edit) → **Convert to Deal** → **Yes**.
The deal now appears in **Sales Pipeline**.

### ⑤ Move the deal forward
In **Sales Pipeline**, **drag the card** from column to column:
New → Contacted → Qualified → Quotation Sent → Negotiation → **Won**.
If the customer says no: open the deal → **Edit** → Stage = **Lost** → choose a reason (e.g. Price too high) → **Save**.

### ⑥ Give a price offer (Quotation)
**Quotations** → **New Quotation** → choose the customer (and the deal) → **Save**.
Then at the bottom, under **Line Items**, click **+**:
- pick a product (name and price fill in by themselves) **or** type a description,
- enter quantity (grams allowed, like 12.5), price, discount %, tax %,
- **Save**. Add more lines the same way.

Totals are calculated automatically, so you can't make a maths mistake.
**Print / PDF:** click **⋯** → **Print to PDF**. Send it to the customer.
Change **Status** to *Sent* when you send it.

### ⑦ Customer agrees → make an Order (owner)
Open the quotation → **Edit** → Status = **Accepted** → **Save**.
Then **⋯** → **Convert to Order** → **Yes**.
The order is created with the same items. (Once accepted, the quotation's items can't be changed, to protect what the customer agreed to.)

### ⑧ Track the order
Open **Orders** → the order → **Edit** → Status:
Confirmed → In Progress → Ready for Delivery → **Delivered**. (Or Cancelled.)

---

## 6. Checking how business is going

**Overview** (home page) — click **Today / This week / This month / This year / Custom** at the top.
Click any box to open that list.

**What the numbers mean:**
- **Order value** = total of confirmed orders (not Draft or Cancelled). It is **not** cash received.
- **Won deals** = deals you marked as Won.
- **Open deals / pipeline** = deals still in progress.
- **Overdue follow-ups** = reminders whose time has passed and aren't done. Clear these first!

**Reports** (owner) — tables of enquiries by source, deals by stage, won vs lost, reasons for losing, salesperson performance and sales over time. Click **Export CSV** to open it in Excel.

---

## 7. Handy tips

- **Search anything:** use the **Search** box at the top (customer name, phone, quotation number…).
- **Quick add:** the **+** at the top-right lets you add a customer, enquiry, follow-up or quotation from any page.
- **Filters:** on any list, click the **All** dropdown to pick *Open*, *Due Today*, *Overdue*, etc.
- **History:** every customer page has a **Timeline** showing who did what and when.
- **Salespeople** can see all customers but edit only their own. They see only their own tasks, and can't delete, export or open Reports.

## 8. Something went wrong?

| Problem | What to do |
|---|---|
| Page won't open | The Terminal window from Step 1 was closed. Start it again. |
| "Wrong username or password" | Make sure the username is in the top box. After 10 wrong tries, wait 1 minute. |
| Can't change quotation items | The quotation is Accepted. Change its status back to Draft (only possible if not yet converted to an order). |
| "Convert to Order" not shown | The quotation must be **Accepted** first, and only the owner can convert. |
| Old logo/name still showing | Press **Cmd + Shift + R** to refresh. |

## 9. How it works behind the scenes (simple explanation)

Think of Organize as three parts:

| Part | Like… | What it does |
|---|---|---|
| **The screen** (browser) | The shop counter | What you see and click |
| **The brain** (server program) | The shop manager | Checks who you are, what you're allowed to do, calculates totals, follows the rules |
| **The memory** (database) | The locked register book | Stores every customer, enquiry, quotation and order |

**What happens when you click Save on a quotation line:**
1. The screen sends your numbers to the brain.
2. The brain checks: *Is this person logged in? Can they edit this quotation? Is the quotation still open (not Accepted)?*
3. The brain calculates the line total itself. **It ignores totals typed in the screen**, so no one can cheat the price.
4. It adds up all lines into Subtotal, Discount, Tax and Grand Total, and saves them in the memory.
5. The screen shows the new totals.

**Every company is fully separate.** Each shop gets its own copy of the app and its own register book (database). Shop A's staff can never see Shop B's customers, not even by guessing a web address. We tested this.

**Rules the brain always enforces (even if someone tries tricks):**
- A salesperson can edit only their own customers and deals, can't delete, and can't see Reports.
- An enquiry becomes a deal only **once**.
- A quotation becomes an order only **once**, only when **Accepted**, and only if it has items.
- An accepted quotation's items are **locked**.
- A Lost deal must have a reason.
- A completed task never shows as overdue.
- A deactivated employee is locked out immediately.

**Automatic things it does for you:**
- Gives numbers: `ENQ-00001`, `QT-00001`, `ORD-00001`.
- Fills today's date on new enquiries, quotations and orders.
- Makes you the owner of anything you create.
- Fills a product's name and price when you pick it on a quotation.
- Updates the customer's **Next Follow-up** and **Last Interaction** when tasks change.
- Records Won/Lost date when a deal closes.
- Keeps a history (Timeline) of every change on important records.

---

## 10. Everything it can do today ✅

**Customers**
- Save shops, wholesalers, manufacturers, individual buyers, suppliers.
- Phone, email, address, city/state, GSTIN, tags (VIP, Bridal…), source, preferred contact.
- Duplicate warning (same name, phone or email).
- Search, filter, sort; owner can export to Excel (CSV).
- Import hundreds of customers from Excel with error checking and undo.

**Enquiries**
- Category, metal, purity, gemstone, quantity, budget, dates, priority, photos.
- "Follow-up Due" list so you know whom to call.
- One-click Convert to Deal.

**Sales Pipeline**
- Drag-and-drop board: New → Contacted → Qualified → Quotation Sent → Negotiation → Won/Lost.
- Expected value and closing date per deal; lost reasons.
- Owner can rename or add stages (Administration → Entity Manager → Deal → Stage).

**Tasks & Follow-ups**
- Calls, meetings, follow-ups, quotation follow-ups, general tasks.
- Due today / upcoming / overdue / completed lists.
- Calendar view, and pop-up reminders inside the app.

**Products**
- Catalogue with SKU, category, metal, purity, design number, gemstone details, photo, approximate price, unit (piece/pair/set/gram/carat).

**Quotations**
- Line items with quantity (grams allowed), price, discount %, tax %.
- Exact automatic totals.
- Professional PDF with your shop name, address, GSTIN, logo and terms.
- Status: Draft, Sent, Accepted, Rejected, Expired.

**Orders**
- Created from accepted quotations, items copied.
- Status: Draft → Confirmed → In Progress → Ready for Delivery → Delivered / Cancelled.
- Printable order confirmation PDF.

**Overview & Reports**
- Live numbers by Today / Week / Month / Year / Custom dates.
- Enquiries by source/status/category, deals by stage, won vs lost, lost reasons, quotations sent/accepted, orders by status, sales over time, salesperson performance, follow-up completion.
- Export reports to Excel (CSV).

**Team & Security**
- Owner and Salesperson roles; deactivate staff instantly.
- Password rules; temporary lock after 10 wrong login tries.
- Each shop's data completely separate.
- Login history and change history kept for 6 months.

**Built-in API (already available)**
Every screen also has a "machine door" (REST API) at `http://<your-address>/api/v1/…`. Other software can use it, with a username and password or an API key, to read or add customers, enquiries, quotations and so on, with the same permission rules as people.
Create an API user: Administration → API Users.

### What it does NOT do today ❌
- No WhatsApp or SMS sending.
- No email sending until you set up your email account (Administration → Outbound Emails). After that, invitations, password reset and email reminders work.
- No payments, invoices or GST e-invoices (quotations are not tax invoices).
- No live gold/silver rates.
- No stock/inventory counting.
- No connection to Tally, Zoho Books or other accounting software.
- No mobile app (the website works in a phone browser once it's hosted online).
- Not on the internet yet — it runs only on this Mac (see `docs/DEPLOYMENT.md` to host it).

---

## 11. What it could do in future by adding APIs/integrations 🚀

These are **not built yet**. Each one plugs into the app's built-in API or into a small added program, without rebuilding the rest. Rough effort is shown so you can plan.

| Idea | What you'd get | How it would connect | Rough effort |
|---|---|---|---|
| **WhatsApp Business API** (Meta, via Gupshup, Interakt, WATI…) | Send quotation PDFs and follow-up reminders on WhatsApp; WhatsApp chats become enquiries automatically | Message provider's API + a webhook into Organize | Medium (needs a Meta-approved business number) |
| **Website / Instagram enquiry form** | People filling your website form appear as new Enquiries instantly | Form → Organize API (EspoCRM's built-in Lead Capture creates Leads, not Enquiries, so a small adapter is needed) | Small |
| **SMS** (MSG91, Twilio) | Order-ready and follow-up SMS | SMS provider API | Small |
| **Live gold & silver rates** (e.g. MetalpriceAPI, GoldAPI, IBJA feed) | Today's rate on the dashboard; product prices auto-calculated by weight × rate + making charge | Rate API checked every few minutes | Medium |
| **Payments** (Razorpay, PayU, UPI links) | Send a payment link for advance/booking amount; order marked "advance paid" | Payment gateway API + webhook | Medium |
| **GST invoices / e-invoicing** | Proper tax invoices with IRN/QR from orders | GST e-invoice API (via a GSP) or accounting software | Large (needs accountant review) |
| **Tally / Zoho Books** | Orders and customers flow into accounts; no double typing | Tally XML/ODBC or Zoho Books API | Medium |
| **Stock & barcodes** | Track pieces, scan tags at the counter, reduce stock on delivery | New stock module + barcode scanner (works like a keyboard) | Large |
| **Google Calendar / Outlook** | Meetings and follow-ups appear in your phone calendar | Paid EspoCRM "Google Integration" extension, or a small sync program using the API | Small (or buy the extension) |
| **Email marketing** (Mailchimp, Brevo) | Festival offers (Akshaya Tritiya, Diwali) to selected customers | Export lists or sync via API | Small |
| **Shiprocket / courier** | Create shipments from orders and track delivery | Courier API | Medium |
| **Mobile app** | App on staff phones with notifications | Uses the same API | Large |
| **AI assistant** | Suggests who to follow up today, drafts WhatsApp replies, summarises a customer's history | AI API (e.g. Claude) reading CRM data through the API | Medium |
| **Online hosting for many shops** | Each jewellery business gets its own web address and login (sell it as SaaS) | Server + domain + HTTPS (scripts already exist) | Small–Medium |

**Suggested order if you want to grow it:** host it online → website enquiry form → WhatsApp → live gold rates → payments → Tally/GST.

**Before adding any integration:** keep API keys secret (never in screenshots or chats), use a separate API user with only the permissions it needs, and test on a copy (demo company) first.

---

## 12. Videos to learn more

Organize is built on **EspoCRM**. Search YouTube for **"EspoCRM tutorial"**. In the videos:
*Accounts* = Customers · *Opportunities* = Sales Pipeline · *Home* = Overview.
Enquiries, Products, Quotations, Orders and Reports are custom to Organize and are explained in this guide.
