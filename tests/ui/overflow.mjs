const routes = process.argv.slice(2);
const p = (await (await fetch('http://127.0.0.1:9333/json/list')).json()).find(t => t.type === 'page');
const ws = new WebSocket(p.webSocketDebuggerUrl); await new Promise(r => ws.onopen = r);
let id = 0; const pend = new Map(); ws.onmessage = e => { const m = JSON.parse(e.data); if (pend.has(m.id)) { pend.get(m.id)(m); pend.delete(m.id); } };
const send = (method, params = {}) => new Promise(r => { const i = ++id; pend.set(i, r); ws.send(JSON.stringify({id: i, method, params})); });
const js = async e => (await send('Runtime.evaluate', {expression: e, returnByValue: true})).result?.result?.value;
const sleep = ms => new Promise(r => setTimeout(r, ms));
await send('Page.navigate', {url: 'http://127.0.0.1:8080/'}); await sleep(2500);
await js(`document.querySelector('#field-userName') && (document.querySelector('#field-userName').value='owner', document.querySelector('#field-password').value='Demo-Owner-2026', document.querySelector('#btn-login').click())`); await sleep(3500);
for (const w of [360, 390, 414, 768]) {
  await send('Emulation.setDeviceMetricsOverride', {width: w, height: 800, deviceScaleFactor: 1, mobile: w < 600});
  const bad = [];
  for (const r of routes) { await js(`location.hash='${r}'`); await sleep(2200);
    const o = await js(`document.documentElement.scrollWidth - window.innerWidth`); if (o > 1) bad.push(`${r} (+${o}px)`); }
  console.log(w + 'px:', bad.length ? 'OVERFLOW ' + bad.join(', ') : 'ok — no sideways page scroll');
}
ws.close();
