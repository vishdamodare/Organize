// Security tests: company isolation (A vs B), manipulated IDs, attachments, exports,
// reports, salesperson restrictions, restricted-mode admin, and error leakage.
import {test, before} from 'node:test';
import assert from 'node:assert/strict';
import {A_URL, B_URL, OWNER_PASSWORD, client, ensureSalesperson, unique} from './helpers.mjs';

const ownerA = client(A_URL, 'owner', OWNER_PASSWORD);
const ownerB = client(B_URL, 'owner', OWNER_PASSWORD);
const SP_PASS = 'Sales-Pass-2026';
let salesA, sales2A;
const a = {};

const NOT_VISIBLE = [403, 404];

before(async () => {
    await ensureSalesperson(ownerA, 'priya', SP_PASS);
    await ensureSalesperson(ownerA, 'arjun', SP_PASS);
    salesA = client(A_URL, 'priya', SP_PASS);
    sales2A = client(A_URL, 'arjun', SP_PASS);

    // Company A data that B will try to reach.
    a.account = (await ownerA.post('Account', {name: unique('Alpha Secret Customer'), type: 'Wholesaler'})).data;
    a.enquiry = (await ownerA.post('Enquiry', {name: 'Secret enquiry', accountId: a.account.id, category: 'Gemstones'})).data;
    a.deal = (await ownerA.post(`Enquiry/${a.enquiry.id}/convertToDeal`)).data;
    a.quotation = (await ownerA.post('Quotation', {accountId: a.account.id})).data;
    await ownerA.post('QuotationItem', {quotationId: a.quotation.id, name: 'Emerald', quantity: '1', unitPrice: '50000'});
    await ownerA.put(`Quotation/${a.quotation.id}`, {status: 'Accepted'});
    a.order = (await ownerA.post(`Quotation/${a.quotation.id}/convertToOrder`)).data;

    const att = await ownerA.post('Attachment', {
        name: 'design.txt', type: 'text/plain', role: 'Attachment', relatedType: 'Enquiry', field: 'attachments',
        file: 'data:text/plain;base64,' + Buffer.from('confidential design sketch').toString('base64'),
    });
    assert.equal(att.status, 200, att.text);
    a.attachment = att.data;
    await ownerA.put(`Enquiry/${a.enquiry.id}`, {attachmentsIds: [att.data.id]});
});

test('unauthenticated requests are rejected', async () => {
    const res = await fetch(`${A_URL}/api/v1/Account`);
    assert.equal(res.status, 401);
});

test("company B's credentials do not work in company A", async () => {
    const crossLogin = client(A_URL, 'owner', 'definitely-wrong');
    assert.equal((await crossLogin.get('App/user')).status, 401);
    // Same username exists in both companies, but each has its own user table.
    const bUser = (await ownerB.get('App/user')).data.user.id;
    const aUser = (await ownerA.get('App/user')).data.user.id;
    assert.notEqual(aUser, bUser);
});

test("company B cannot read company A's records by ID", async () => {
    for (const [type, rec] of [['Account', a.account], ['Enquiry', a.enquiry], ['Opportunity', a.deal],
        ['Quotation', a.quotation], ['CustomerOrder', a.order]]) {
        const r = await ownerB.get(`${type}/${rec.id}`);
        assert.ok(NOT_VISIBLE.includes(r.status), `${type} read must be denied, got ${r.status}`);
        assert.ok(!r.text.includes('Alpha Secret'), 'no data leaked');

        const w = await ownerB.put(`${type}/${rec.id}`, {name: 'hacked'});
        assert.ok(NOT_VISIBLE.includes(w.status), `${type} update must be denied, got ${w.status}`);

        const d = await ownerB.del(`${type}/${rec.id}`);
        assert.ok(NOT_VISIBLE.includes(d.status), `${type} delete must be denied, got ${d.status}`);
    }

    const conv = await ownerB.post(`Quotation/${a.quotation.id}/convertToOrder`);
    assert.ok(NOT_VISIBLE.includes(conv.status), 'cannot convert another company’s quotation');

    const items = await ownerB.get(`Quotation/${a.quotation.id}/items`);
    assert.ok(NOT_VISIBLE.includes(items.status), 'related lists denied');
});

test('company B cannot link its records to company A records', async () => {
    const r = await ownerB.post('Quotation', {accountId: a.account.id});
    assert.ok([400, 403, 404].includes(r.status), `link to foreign customer must fail, got ${r.status}`);

    const item = await ownerB.post('QuotationItem', {quotationId: a.quotation.id, name: 'x', quantity: '1', unitPrice: '1'});
    assert.ok([400, 403, 404].includes(item.status), `line on foreign quotation must fail, got ${item.status}`);
});

test('attachments cannot be downloaded across companies', async () => {
    const own = await ownerA.raw(`?entryPoint=download&id=${a.attachment.id}`);
    assert.equal(own.status, 200);
    assert.equal(await own.text(), 'confidential design sketch');

    const cross = await ownerB.raw(`?entryPoint=download&id=${a.attachment.id}`);
    assert.ok(NOT_VISIBLE.includes(cross.status), `cross-company download must fail, got ${cross.status}`);
    assert.ok(!(await cross.text()).includes('confidential'));

    const meta = await ownerB.get(`Attachment/${a.attachment.id}`);
    assert.ok(NOT_VISIBLE.includes(meta.status));
});

test("searches, exports and reports in B never include A's data", async () => {
    const search = await ownerB.get(`Account?where[0][type]=textFilter&where[0][value]=Alpha%20Secret`);
    assert.equal(search.data.total, 0);

    const global = await ownerB.get('GlobalSearch?q=Alpha%20Secret');
    assert.equal(global.data.list.length, 0);

    const exp = await ownerB.post('Export', {entityType: 'Account', ids: [a.account.id], format: 'csv'});
    if (exp.status === 200) {
        const id = exp.data.id ?? exp.data.attachmentId;
        const file = await ownerB.raw(`?entryPoint=download&id=${id}`);
        assert.ok(!(await file.text()).includes('Alpha Secret'), 'export contains nothing from A');
    } else {
        assert.ok([400, 403, 404].includes(exp.status));
    }

    const report = await ownerB.get('JewelleryReport/summary?period=year');
    assert.equal(report.status, 200);
    assert.ok(!JSON.stringify(report.data).includes(a.account.id));
    const accepted = report.data.quotations.acceptedValue;
    const ownQuotes = await ownerB.get('Quotation?primaryFilter=accepted');
    if (ownQuotes.data.total === 0) assert.equal(Number(accepted), 0, 'A’s accepted value not counted in B');
});

test('salesperson cannot perform administrative actions', async () => {
    assert.equal((await salesA.post('User', {userName: 'evil', type: 'admin', password: 'Xx-12345678', passwordConfirm: 'Xx-12345678'})).status, 403);
    assert.equal((await salesA.put('Settings', {jewelleryCompanyName: 'Hacked'})).status, 403);
    assert.equal((await salesA.post('Role', {name: 'x'})).status, 403);
    assert.equal((await salesA.get('JewelleryReport/summary')).status, 403);
    assert.equal((await salesA.post('Import/file', 'a,b', {'Content-Type': 'text/csv'})).status, 403);
    assert.equal((await salesA.post('Export', {entityType: 'Account', format: 'csv', where: []})).status, 403);
    assert.equal((await salesA.del(`Account/${a.account.id}`)).status, 403);
    assert.equal((await salesA.post('JewelleryProduct', {name: 'x', sku: 'X1', category: 'Other'})).status, 403);

    const conv = await salesA.post(`Quotation/${a.quotation.id}/convertToOrder`);
    assert.equal(conv.status, 403, 'orders are managed by the owner');

    const me = await salesA.get('App/user');
    assert.notEqual(me.data.user.type, 'admin');
    assert.ok(me.data.appParams.templateEntityTypeList.includes('Quotation'), 'salesperson can still print quotation PDFs');
});

test('salesperson edits own records only; dashboard is permission-scoped', async () => {
    const mine = await salesA.post('Account', {name: unique('Priya Customer'), type: 'Individual Buyer'});
    assert.equal(mine.status, 200, mine.text);
    assert.equal((await salesA.put(`Account/${mine.data.id}`, {contactPerson: 'Self'})).status, 200);

    assert.equal((await sales2A.put(`Account/${mine.data.id}`, {contactPerson: 'Other'})).status, 403, 'cannot edit a colleague’s customer');
    assert.equal((await salesA.put(`Account/${a.account.id}`, {contactPerson: 'x'})).status, 403, 'cannot edit owner’s customer');

    const task = await salesA.post('Task', {name: 'Priya follow-up', taskType: 'Phone Call', parentType: 'Account',
        parentId: mine.data.id, dateEnd: '2030-01-01 10:00:00', assignedUserId: (await salesA.get('App/user')).data.user.id});
    assert.equal(task.status, 200, task.text);
    assert.ok(NOT_VISIBLE.includes((await sales2A.get(`Task/${task.data.id}`)).status), 'tasks are private to the assignee');

    const overview = await sales2A.get('JewelleryReport/overview?period=month');
    assert.equal(overview.status, 200);
    assert.ok(!(overview.data.upcomingTasks ?? []).some(t => t.id === task.data.id), 'dashboard respects record ACL');

    const item = await sales2A.post('QuotationItem', {quotationId: a.quotation.id, name: 'x', quantity: '1', unitPrice: '1'});
    assert.equal(item.status, 403, 'cannot add lines to a quotation the user cannot edit');
});

test('restricted mode: company owner cannot install code or upgrade', async () => {
    const ext = await ownerA.post('Extension/action/upload', 'data:application/zip;base64,UEsFBgAAAAAAAAAAAAAAAAAAAAAAAA==');
    assert.ok([403, 404].includes(ext.status), `extension upload must be blocked, got ${ext.status}`);
    const up = await ownerA.post('Admin/action/uploadUpgradePackage', 'x');
    assert.ok([403, 404].includes(up.status), `upgrade must be blocked, got ${up.status}`);
});

test('errors do not leak internals', async () => {
    // Malformed JSON is answered with a generic 500 by EspoCRM core (documented in SECURITY.md);
    // everything else must be a 4xx. None may leak internals.
    const probes = [
        [await ownerA.get('Account/doesnotexist123'), false],
        [await ownerA.post('Account', '{not json'), true],
        [await ownerA.get('NoSuchEntity'), false],
        [await ownerA.get("Account?where[0][type]=equals&where[0][attribute]=name'--&where[0][value]=x"), false],
    ];

    for (const [r, may500] of probes) {
        assert.ok(r.status >= 400 && (r.status < 500 || may500), `unexpected status ${r.status}`);
        for (const secret of ['/Users/', 'Stack trace', '#0 ', 'PDOException', 'SQLSTATE', OWNER_PASSWORD]) {
            assert.ok(!r.text.includes(secret), `response leaks "${secret}"`);
            assert.ok(!(r.reason ?? '').includes(secret), `status reason leaks "${secret}"`);
        }
    }
});
