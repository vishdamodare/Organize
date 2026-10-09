// Minimal REST client for EspoCRM's API (Basic auth over the native auth layer).
export const A_URL = process.env.TEST_A_URL ?? 'http://127.0.0.1:8091';
export const B_URL = process.env.TEST_B_URL ?? 'http://127.0.0.1:8092';
export const OWNER_PASSWORD = process.env.OWNER_PASSWORD ?? 'Owner-Test-2026';

export function client(baseUrl, username, password) {
    const auth = 'Basic ' + Buffer.from(`${username}:${password}`).toString('base64');

    async function request(method, path, body, headers = {}) {
        const res = await fetch(`${baseUrl}/api/v1/${path}`, {
            method,
            headers: {Authorization: auth, 'Content-Type': 'application/json', ...headers},
            body: body === undefined ? undefined : (typeof body === 'string' ? body : JSON.stringify(body)),
        });
        const text = await res.text();
        let data = null;
        try { data = text ? JSON.parse(text) : null; } catch { data = text; }

        return {status: res.status, data, text, reason: res.headers.get('x-status-reason')};
    }

    return {
        baseUrl,
        get: (p, h) => request('GET', p, undefined, h),
        post: (p, b, h) => request('POST', p, b, h),
        put: (p, b, h) => request('PUT', p, b, h),
        del: (p, h) => request('DELETE', p, undefined, h),
        raw: async (path) => fetch(`${baseUrl}/${path}`, {headers: {Authorization: auth}}),
    };
}

export const unique = (p) => `${p} ${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;

/** Create (or reuse) a Salesperson user in a company, as its owner. */
export async function ensureSalesperson(owner, userName, password) {
    const roles = await owner.get('Role?where[0][type]=equals&where[0][attribute]=name&where[0][value]=Salesperson');
    const roleId = roles.data.list[0].id;
    const found = await owner.get(`User?where[0][type]=equals&where[0][attribute]=userName&where[0][value]=${userName}`);
    const body = {userName, firstName: 'Sales', lastName: userName, type: 'regular', isActive: true,
        password, passwordConfirm: password, rolesIds: [roleId], emailAddress: `${userName}@example.com`};

    const r = found.data.total ? await owner.put(`User/${found.data.list[0].id}`, body) : await owner.post('User', body);
    if (r.status !== 200) throw new Error(`user setup failed ${r.status} ${r.text}`);

    return r.data.id;
}

export function today(offsetDays = 0) {
    const d = new Date(Date.now() + offsetDays * 86400000);
    return d.toISOString().slice(0, 10);
}

export function utcDateTime(offsetHours) {
    return new Date(Date.now() + offsetHours * 3600000).toISOString().slice(0, 19).replace('T', ' ');
}
