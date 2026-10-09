// Tiny CDP driver: login, visit pages, screenshot, collect console errors.
import {writeFileSync} from 'node:fs';
const [, , outDir, ...routes] = process.argv;
const ver = await (await fetch('http://127.0.0.1:9333/json/version')).json();
const targets = await (await fetch('http://127.0.0.1:9333/json/list')).json();
const page = targets.find(t => t.type === 'page');
const ws = new WebSocket(page.webSocketDebuggerUrl);
await new Promise(r => ws.onopen = r);
let id = 0; const pending = new Map(); const errors = [];
ws.onmessage = e => { const m = JSON.parse(e.data);
  if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); }
  if (m.method === 'Runtime.exceptionThrown') errors.push(m.params.exceptionDetails.exception?.description ?? m.params.exceptionDetails.text);
  if (m.method === 'Runtime.consoleAPICalled' && m.params.type === 'error') errors.push(m.params.args.map(a => a.value ?? a.description).join(' '));
};
const send = (method, params = {}) => new Promise(r => { const i = ++id; pending.set(i, r); ws.send(JSON.stringify({id: i, method, params})); });
const sleep = ms => new Promise(r => setTimeout(r, ms));
const evalJs = async expr => (await send('Runtime.evaluate', {expression: expr, awaitPromise: true, returnByValue: true})).result?.result?.value;
await send('Runtime.enable'); await send('Page.enable');
const [w, h] = (process.env.VIEWPORT ?? '1366x900').split('x').map(Number);
await send('Emulation.setDeviceMetricsOverride', {width: w, height: h, deviceScaleFactor: 1, mobile: w < 600});
await send('Network.clearBrowserCookies'); await send('Page.navigate', {url: 'http://127.0.0.1:8080/'}); await sleep(1500); await evalJs('localStorage.clear(); sessionStorage.clear(); true'); await send('Page.reload'); await sleep(2500);
await evalJs(`(() => { const u=document.querySelector('#field-userName'), p=document.querySelector('#field-password');
  if (!u) return 'no-login'; u.value='${process.env.UI_USER ?? 'owner'}'; p.value='${process.env.UI_PASS ?? 'Demo-Owner-2026'}';
  document.querySelector('#btn-login').click(); return 'ok'; })()`);
await sleep(3500);
for (const route of routes) {
  const [hash, name] = route.split('=');
  await evalJs(`location.hash = '${hash}'`); await sleep(3000);
  const shot = await send('Page.captureScreenshot', {format: 'png'});
  writeFileSync(`${outDir}/${name}.png`, Buffer.from(shot.result.data, 'base64'));
  const title = await evalJs(`document.querySelector('.page-header, h3, .header-title')?.innerText?.slice(0,80) ?? ''`);
  console.log(name, '|', title.replace(/\s+/g, ' '));
}
console.log('console errors:', JSON.stringify(errors.slice(0, 10)));
ws.close();
