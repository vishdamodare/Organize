import {writeFileSync} from 'node:fs';
const out = process.argv[2], EQ = process.argv[3];
const page = (await (await fetch('http://127.0.0.1:9333/json/list')).json()).find(t => t.type === 'page');
const ws = new WebSocket(page.webSocketDebuggerUrl); await new Promise(r => ws.onopen = r);
let id = 0; const pend = new Map(); const errors = [];
ws.onmessage = e => { const m = JSON.parse(e.data); if (m.id && pend.has(m.id)) { pend.get(m.id)(m); pend.delete(m.id); }
  if (m.method === 'Runtime.exceptionThrown') errors.push(JSON.stringify({t: m.params.exceptionDetails.text, d: m.params.exceptionDetails.exception?.description?.slice(0,300), v: m.params.exceptionDetails.exception?.value, url: m.params.exceptionDetails.url, line: m.params.exceptionDetails.lineNumber})); };
const send = (method, params = {}) => new Promise(r => { const i = ++id; pend.set(i, r); ws.send(JSON.stringify({id: i, method, params})); });
const sleep = ms => new Promise(r => setTimeout(r, ms));
const js = async e => (await send('Runtime.evaluate', {expression: e, awaitPromise: true, returnByValue: true})).result?.result?.value;
const shot = async n => writeFileSync(`${out}/${n}.png`, Buffer.from((await send('Page.captureScreenshot', {format: 'png'})).result.data, 'base64'));
await send('Runtime.enable');
const check = (label, ok) => console.log(ok ? 'PASS' : 'FAIL', label);

// 1. Convert enquiry via the detail action + confirmation modal
await js(`location.hash = '#Enquiry/view/${EQ}'`); await sleep(2500);
const hasAction = await js(`!!document.querySelector('[data-name="convertToDeal"], [data-action="convertToDeal"]')`);
check('Convert to Deal action present on open enquiry', hasAction);
await js(`document.querySelector('.detail-button-container .dropdown-toggle')?.click(); true`); await sleep(400);
await js(`(document.querySelector('[data-name="convertToDeal"]') || document.querySelector('[data-action="convertToDeal"]')).click(); true`); await sleep(800);
await shot('flow-confirm');
const modalText = await js(`document.querySelector('.modal-body')?.innerText ?? ''`);
check('confirmation modal shown', /Convert this enquiry/.test(modalText));
await js(`[...document.querySelectorAll('.modal-footer button, .modal button')].find(b => /yes|confirm|ok/i.test(b.innerText))?.click(); true`);
await sleep(2500);
check('navigated to the new deal', (await js('location.hash')).startsWith('#Opportunity/view/'));
await shot('flow-deal');

// 2. Validation errors on an empty customer form; modal/cancel closes
await js(`location.hash = '#Account/create'`); await sleep(2000);
await js(`document.querySelector('[data-name="save"], button[data-action="save"]').click(); true`); await sleep(800);
const invalid = await js(`document.querySelectorAll('.has-error, .cell.has-error').length`);
check('required-field validation shown on empty save', invalid > 0);
await shot('flow-validation');
await js(`document.querySelector('[data-name="cancel"], button[data-action="cancel"]')?.click(); true`); await sleep(1200);
check('cancel leaves the form', !(await js('location.hash')).includes('/create'));

// 3. Quick-create modal opens and closes
await js(`location.hash = '#Task'`); await sleep(1500);
await js(`document.querySelector('#nav-quick-create-dropdown, a[data-action="quickCreate"], .navbar .fa-plus')?.closest('a,button')?.click(); true`); await sleep(500);
await js(`document.querySelector('[data-action="quickCreate"][data-name="Task"]')?.click(); true`); await sleep(1500);
const modalOpen = await js(`!!document.querySelector('.modal.in, .modal[style*="display: block"]')`);
check('quick-create modal opens', modalOpen);
await js(`document.querySelector('.modal .close, .modal [data-name="cancel"]')?.click(); true`); await sleep(800);
check('modal closes', !(await js(`!!document.querySelector('.modal.in')`)));
console.log('exceptions:', JSON.stringify(errors));
ws.close();
