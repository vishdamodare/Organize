// Functional tests: the customer → enquiry → deal → quotation → order workflow,
// follow-ups, reports, import, search and user deactivation. Company A only.
import {test, before} from 'node:test';
import assert from 'node:assert/strict';
import {A_URL, OWNER_PASSWORD, client, ensureSalesperson, unique, today, utcDateTime} from './helpers.mjs';

const owner = client(A_URL, 'owner', OWNER_PASSWORD);
const ctx = {};

before(async () => {
    const me = await owner.get('App/user');
    assert.equal(me.status, 200, 'owner can log in');
});

test('owner logs into own company workspace', async () => {
    const me = await owner.get('App/user');
    assert.equal(me.data.user.userName, 'owner');
    assert.equal(me.data.user.type, 'admin');
    const settings = await owner.get('Settings');
    assert.equal(settings.data.jewelleryCompanyName, 'Alpha Gold House');
    assert.equal(settings.data.defaultCurrency, 'INR');
});

test('customer: create, validation, duplicate warning', async () => {
    const phone = '+9198' + String(Date.now()).slice(-8);
    const name = unique('Mehta Jewels');

    const bad = await owner.post('Account', {name: unique('Bad'), type: 'Wholesaler', emailAddress: 'not-an-email'});
    assert.equal(bad.status, 400, 'invalid email rejected');

    const badGst = await owner.post('Account', {name: unique('Bad GST'), type: 'Wholesaler', gstin: '12345'});
    assert.equal(badGst.status, 400, 'invalid GSTIN rejected');

    const res = await owner.post('Account', {
        name, type: 'Retail Jewellery Shop', contactPerson: 'Ramesh Mehta', phoneNumber: phone,
        emailAddress: `${Date.now()}@mehta.example`, billingAddressCity: 'Surat', billingAddressState: 'Gujarat',
        customerSource: 'Exhibition', tags: ['VIP'], preferredContactMethod: 'WhatsApp', gstin: '24AAPFU0939F1ZV',
    });
    assert.equal(res.status, 200, res.text);
    assert.equal(res.data.customerStatus, 'Active');
    ctx.accountId = res.data.id;
    ctx.accountName = name;

    const dup = await owner.post('Account', {name: unique('Another'), type: 'Wholesaler', phoneNumber: phone});
    assert.equal(dup.status, 409, 'same phone triggers a duplicate warning');
});

test('a customer can have multiple enquiries', async () => {
    for (const [title, category] of [['Bridal set 22K', 'Bridal Jewellery'], ['Solitaire ring', 'Diamond Jewellery']]) {
        const r = await owner.post('Enquiry', {name: title, accountId: ctx.accountId, category, budget: '250000.00',
            metalType: 'Gold', purity: '22K', source: 'Walk-in', nextFollowUpDate: today(-1)});
        assert.equal(r.status, 200, r.text);
        assert.match(r.data.number, /^ENQ-\d{5}$/);
        (ctx.enquiryIds ??= []).push(r.data.id);
    }

    const list = await owner.get(`Account/${ctx.accountId}/enquiries`);
    assert.equal(list.data.total, 2);

    const due = await owner.get('Enquiry?primaryFilter=followUpDue&maxSize=200');
    assert.ok(due.data.list.some(e => e.id === ctx.enquiryIds[0]), 'follow-up-due filter finds it');
});

test('enquiry converts to a deal exactly once, keeping history', async () => {
    const id = ctx.enquiryIds[0];
    const r = await owner.post(`Enquiry/${id}/convertToDeal`);
    assert.equal(r.status, 200, r.text);
    ctx.dealId = r.data.id;

    const again = await owner.post(`Enquiry/${id}/convertToDeal`);
    assert.equal(again.status, 409, 'second conversion refused');

    const enquiry = await owner.get(`Enquiry/${id}`);
    assert.equal(enquiry.data.status, 'Converted');

    const deal = await owner.get(`Opportunity/${ctx.dealId}`);
    assert.equal(deal.data.enquiryId, id);
    assert.equal(deal.data.accountId, ctx.accountId);
    assert.equal(deal.data.stage, 'Qualified');
    assert.equal(Number(deal.data.amount), 250000);
});

test('deal moves through the pipeline; Won/Lost recorded with date and reason', async () => {
    for (const stage of ['Quotation Sent', 'Negotiation']) {
        const r = await owner.put(`Opportunity/${ctx.dealId}`, {stage});
        assert.equal(r.status, 200, r.text);
        assert.equal(r.data.stage, stage);
        assert.equal(r.data.outcomeDate, null);
    }

    const conv = await owner.post(`Enquiry/${ctx.enquiryIds[1]}/convertToDeal`);
    ctx.lostDealId = conv.data.id;

    const noReason = await owner.put(`Opportunity/${ctx.lostDealId}`, {stage: 'Closed Lost'});
    assert.equal(noReason.status, 400, 'lost reason is required');

    const lost = await owner.put(`Opportunity/${ctx.lostDealId}`, {stage: 'Closed Lost', lostReason: 'Price too high'});
    assert.equal(lost.status, 200, lost.text);
    assert.ok(lost.data.outcomeDate, 'outcome date recorded');

    const won = await owner.put(`Opportunity/${ctx.dealId}`, {stage: 'Closed Won'});
    assert.equal(won.status, 200);
    assert.ok(won.data.outcomeDate);
    assert.equal(won.data.lostReason, '');

    const orders = await owner.get(`Opportunity/${ctx.dealId}/customerOrders`);
    assert.equal(orders.data.total, 0, 'Won deal does not create an order by itself');
});

test('follow-up: schedule, complete, and overdue logic', async () => {
    const future = await owner.post('Task', {name: 'Call about bridal design', taskType: 'Customer Follow-up',
        parentType: 'Account', parentId: ctx.accountId, dateEnd: utcDateTime(24), status: 'Not Started'});
    assert.equal(future.status, 200, future.text);
    assert.equal(future.data.accountId, ctx.accountId, 'customer derived from parent');

    let account = await owner.get(`Account/${ctx.accountId}`);
    assert.ok(account.data.nextFollowUpAt, 'next follow-up shown on customer');

    const past = await owner.post('Task', {name: 'Send catalogue', taskType: 'Phone Call',
        parentType: 'Opportunity', parentId: ctx.dealId, dateEnd: utcDateTime(-48), status: 'Not Started'});
    assert.equal(past.status, 200, past.text);

    let overdue = await owner.get('Task?primaryFilter=overdue&maxSize=200');
    assert.ok(overdue.data.list.some(t => t.id === past.data.id), 'past open task is overdue');

    const done = await owner.put(`Task/${past.data.id}`, {status: 'Completed'});
    assert.equal(done.status, 200);
    assert.ok(done.data.dateCompleted, 'completion timestamp set');

    overdue = await owner.get('Task?primaryFilter=overdue&maxSize=200');
    assert.ok(!overdue.data.list.some(t => t.id === past.data.id), 'completed task is no longer overdue');

    const upcoming = await owner.get('Task?primaryFilter=upcoming&maxSize=200');
    assert.ok(upcoming.data.list.some(t => t.id === future.data.id));

    account = await owner.get(`Account/${ctx.accountId}`);
    assert.ok(account.data.lastInteractionAt, 'last interaction updated');
});

test('quotation totals are calculated on the server', async () => {
    const q = await owner.post('Quotation', {accountId: ctx.accountId, opportunityId: ctx.dealId, grandTotal: 1});
    assert.equal(q.status, 200, q.text);
    assert.match(q.data.number, /^QT-\d{5}$/);
    assert.ok(q.data.terms, 'default terms copied from settings');
    ctx.quotationId = q.data.id;

    const product = await owner.post('JewelleryProduct', {name: unique('Kundan Necklace'), sku: 'KN-' + Date.now(),
        category: 'Bridal Jewellery', metalType: 'Gold', purity: '22K', price: '10000.50', unit: 'Piece'});
    assert.equal(product.status, 200, product.text);

    // Product pre-fills name and price; server computes line totals.
    const l1 = await owner.post('QuotationItem', {quotationId: ctx.quotationId, productId: product.data.id,
        quantity: '2', discountPercent: '5', taxPercent: '3', lineTotal: '1'});
    assert.equal(l1.status, 200, l1.text);
    assert.equal(l1.data.name, product.data.name);
    assert.equal(Number(l1.data.lineTotal), 19570.98);

    const l2 = await owner.post('QuotationItem', {quotationId: ctx.quotationId, name: 'Gold chain (by weight, grams)',
        quantity: '1.5', unitPrice: '3333.33', discountPercent: '0', taxPercent: '3'});
    assert.equal(l2.status, 200, l2.text);
    assert.equal(Number(l2.data.lineTotal), 5150);

    const tampered = await owner.put(`Quotation/${ctx.quotationId}`, {grandTotal: '5', subtotal: '5'});
    assert.equal(tampered.status, 200);

    const quote = await owner.get(`Quotation/${ctx.quotationId}`);
    assert.equal(Number(quote.data.subtotal), 25001);
    assert.equal(Number(quote.data.discountAmount), 1000.05);
    assert.equal(Number(quote.data.taxAmount), 720.03);
    assert.equal(Number(quote.data.grandTotal), 24720.98, 'client-submitted totals ignored');

    const neg = await owner.post('QuotationItem', {quotationId: ctx.quotationId, name: 'x', quantity: '1', unitPrice: '-5'});
    assert.equal(neg.status, 400, 'negative price rejected');
});

test('draft quotation cannot be converted; accepted converts once into an order', async () => {
    const early = await owner.post(`Quotation/${ctx.quotationId}/convertToOrder`);
    assert.equal(early.status, 409);

    assert.equal((await owner.put(`Quotation/${ctx.quotationId}`, {status: 'Accepted'})).status, 200);

    const lockedAdd = await owner.post('QuotationItem', {quotationId: ctx.quotationId, name: 'Extra', quantity: '1', unitPrice: '1'});
    assert.equal(lockedAdd.status, 409, 'accepted quotation lines are locked');

    const conv = await owner.post(`Quotation/${ctx.quotationId}/convertToOrder`);
    assert.equal(conv.status, 200, conv.text);
    assert.match(conv.data.number, /^ORD-\d{5}$/);
    ctx.orderId = conv.data.id;

    const [twice, parallel] = await Promise.all([
        owner.post(`Quotation/${ctx.quotationId}/convertToOrder`),
        owner.post(`Quotation/${ctx.quotationId}/convertToOrder`),
    ]);
    assert.equal(twice.status, 409);
    assert.equal(parallel.status, 409);

    const order = await owner.get(`CustomerOrder/${ctx.orderId}`);
    assert.equal(order.data.status, 'Draft');
    assert.equal(order.data.quotationId, ctx.quotationId);
    assert.equal(Number(order.data.grandTotal), 24720.98);
    const items = await owner.get(`CustomerOrder/${ctx.orderId}/items`);
    assert.equal(items.data.total, 2);

    assert.equal((await owner.put(`Quotation/${ctx.quotationId}`, {status: 'Draft'})).status, 409, 'converted quotation status locked');
    assert.equal((await owner.del(`Quotation/${ctx.quotationId}`)).status, 409, 'converted quotation cannot be deleted');

    const orders = await owner.get(`Quotation/${ctx.quotationId}/orders`);
    assert.equal(orders.data.total, 1, 'still exactly one order');
});

test('quotation PDF can be generated', async () => {
    const templates = await owner.get('Template?where[0][type]=equals&where[0][attribute]=entityType&where[0][value]=Quotation');
    const templateId = templates.data.list[0].id;
    const res = await owner.raw(`?entryPoint=pdf&entityType=Quotation&entityId=${ctx.quotationId}&templateId=${templateId}`);
    assert.equal(res.status, 200);
    assert.match(res.headers.get('content-type'), /pdf/);
    const buf = Buffer.from(await res.arrayBuffer());
    assert.equal(buf.subarray(0, 4).toString(), '%PDF');
});

test('reports reflect database changes', async () => {
    const before = await owner.get('JewelleryReport/overview?period=month');
    assert.equal(before.status, 200, before.text);

    assert.equal((await owner.put(`CustomerOrder/${ctx.orderId}`, {status: 'Confirmed'})).status, 200);

    const after = await owner.get('JewelleryReport/overview?period=month');
    const delta = Math.round((Number(after.data.orderValue) - Number(before.data.orderValue)) * 100) / 100;
    assert.equal(delta, 24720.98, 'confirmed order counted as order value; draft was not');
    assert.ok(after.data.wonDeals >= 1);

    const lockedOrderItem = await owner.post('CustomerOrderItem', {customerOrderId: ctx.orderId, name: 'x', quantity: '1', unitPrice: '1'});
    assert.equal(lockedOrderItem.status, 409, 'confirmed order lines locked');

    const summary = await owner.get('JewelleryReport/summary?period=month');
    assert.equal(summary.status, 200, summary.text);
    const won = summary.data.wonVsLost.find(g => g.key === 'Closed Won');
    const lost = summary.data.wonVsLost.find(g => g.key === 'Closed Lost');
    assert.ok(won.count >= 1 && lost.count >= 1);
    assert.ok(summary.data.quotations.accepted >= 1);
    assert.ok(summary.data.salespeople.length >= 1);
    assert.ok(summary.data.enquiriesBySource.some(g => g.key === 'Walk-in'));

    const custom = await owner.get(`JewelleryReport/summary?period=custom&from=${today(-7)}&to=${today(1)}`);
    assert.equal(custom.status, 200);
    const badRange = await owner.get('JewelleryReport/summary?period=custom&from=2026-13-01&to=x');
    assert.equal(badRange.status, 400);
});

test('search and filters return the right records', async () => {
    const byName = await owner.get(`Account?where[0][type]=textFilter&where[0][value]=${encodeURIComponent(ctx.accountName)}`);
    assert.equal(byName.data.total, 1);
    assert.equal(byName.data.list[0].id, ctx.accountId);

    const byType = await owner.get('Account?where[0][type]=equals&where[0][attribute]=type&where[0][value]=Retail%20Jewellery%20Shop&maxSize=200');
    assert.ok(byType.data.list.every(a => a.type === 'Retail Jewellery Shop'));

    const open = await owner.get('Enquiry?primaryFilter=open&maxSize=200');
    assert.ok(open.data.list.every(e => !['Converted', 'Lost'].includes(e.status)));

    const accepted = await owner.get('Quotation?primaryFilter=accepted&maxSize=200');
    assert.ok(accepted.data.list.some(q => q.id === ctx.quotationId));
});

test('CSV import validates rows and reports errors without dropping them silently', async () => {
    const tag = Date.now().toString(36);
    const csv = [
        'Name,Type,Phone,Email,City',
        `Import Ok One ${tag},Wholesaler,+9197000${String(Date.now()).slice(-5)},one${tag}@example.com,Jaipur`,
        `Import Ok Two ${tag},Individual Buyer,,two${tag}@example.com,Delhi`,
        `Import Bad ${tag},Wholesaler,,not-an-email,Delhi`,
    ].join('\n');

    const file = await owner.post('Import/file', csv, {'Content-Type': 'text/csv'});
    assert.equal(file.status, 200, file.text);

    const result = await owner.post('Import', {
        entityType: 'Account', attachmentId: file.data.attachmentId,
        attributeList: ['name', 'type', 'phoneNumber', 'emailAddress', 'billingAddressCity'],
        headerRow: true, delimiter: ',', textQualifier: '"', dateFormat: 'YYYY-MM-DD', timeFormat: 'HH:mm',
        decimalMark: '.', personNameFormat: 'f l', action: 'create', currency: 'INR', defaultValues: {},
        idleMode: false, manualMode: false, silentMode: false, skipDuplicateChecking: false, timezone: 'UTC',
    });
    assert.equal(result.status, 200, result.text);
    assert.equal(result.data.countCreated, 2);

    const errors = await owner.get(`Import/${result.data.id}/errors`);
    assert.equal(errors.status, 200, errors.text);
    assert.equal(errors.data.total, 1, 'invalid row is reported');

    const imported = await owner.get(`Account?where[0][type]=textFilter&where[0][value]=Import%20Ok%20One%20${tag}`);
    assert.equal(imported.data.list[0].billingAddressCity, 'Jaipur', 'values preserved');
});

test('deactivated users lose access immediately', async () => {
    const userName = 'temp' + Date.now().toString(36);
    const password = 'Temp-Pass-2026';
    const id = await ensureSalesperson(owner, userName, password);
    const sp = client(A_URL, userName, password);

    assert.equal((await sp.get('App/user')).status, 200);
    assert.equal((await owner.put(`User/${id}`, {isActive: false})).status, 200);
    assert.equal((await sp.get('App/user')).status, 401);
    assert.equal((await sp.get('Account')).status, 401);
});
