import assert from 'node:assert/strict';
import { randomBytes, randomUUID } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { checkSchema } from './contract-schema.mjs';

const origin = new URL(process.argv[2]);
assert(['127.0.0.1', 'localhost', '[::1]'].includes(origin.hostname));
const token = process.env.CART_HTTP_TOKEN;
assert(token, 'Supply only a synthetic disposable credential.');
const proof = randomBytes(32).toString('hex');
const contract = JSON.parse(await readFile(new URL('../../docs/api/openapi.json', import.meta.url)));
const schemas = contract.components.schemas;
const check = (value, schema) => checkSchema(value, schema, schemas);
const checks = [];
for (const [path, methods] of Object.entries(contract.paths)) {
    if (!path.startsWith('/api/v1/storefront/carts')) continue;
    for (const operation of Object.values(methods)) {
        for (const response of Object.values(operation.responses)) {
            const example = response.content?.['application/json'];
            if (example?.example) check(example.example, example.schema);
        }
    }
}
const base = '/api/v1/storefront/carts';
async function call(method, path, body, status = 200, extra = {}) {
    const response = await fetch(new URL(path, origin), {
        method, redirect: 'error',
        headers: { Accept: 'application/json', Origin: 'https://adzbyte.com', Authorization: `Bearer ${token}`, 'X-Cart-Proof': proof, ...(body === undefined ? {} : { 'Content-Type': 'application/json' }), ...extra },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    assert.equal(response.status, status);
    assert.match(response.headers.get('cache-control'), /no-store/);
    assert.match(response.headers.get('cache-control'), /private/);
    assert.equal(response.headers.get('etag'), null);
    assert.equal(response.headers.get('set-cookie'), null);
    const payload = await response.json();
    check(payload, status < 400 ? schemas.CartResponse : schemas.ErrorResponse);
    assert(!JSON.stringify(payload).includes(proof));
    assert(!JSON.stringify(payload).includes(token));
    return payload;
}
const catalog = await (await fetch(new URL('/api/v1/products?include=category', origin))).json();
check(catalog, schemas.ProductListResponse);
const restricted = catalog.data.filter(p => p.category?.limit_one_per_cart);
const regular = catalog.data.filter(p => !p.category);
assert.equal(restricted.length, 2);
assert.equal(regular.length, 2);
const creation = randomUUID();
await call('POST', base, {}, 401, { Authorization: '' });
await call('POST', base, {}, 422, { 'X-Cart-Proof': '', 'Idempotency-Key': creation });
const created = await call('POST', base, {}, 201, { 'Idempotency-Key': creation });
const reference = created.data.public_id;
const path = `${base}/${reference}`;
assert.equal((await call('POST', base, {}, 201, { 'Idempotency-Key': creation })).data.public_id, reference);
assert.equal((await call('GET', `${base}/resolve?creation_operation_id=${creation}`)).data.public_id, reference);
await call('GET', path, undefined, 404, { 'X-Cart-Proof': randomBytes(32).toString('hex') });
checks.push('actual Bearer authentication, independent proof, create retry and read-only recovery');
for (const product of [restricted[0], ...regular]) {
    check({ product: product.public_id }, schemas.CartAddRequest);
    await call('POST', path + '/items', { product: product.public_id }, 200, { 'Idempotency-Key': randomUUID() });
}
const operation = randomUUID();
const replacement = await call('POST', path + '/items', { product: restricted[1].public_id }, 200, { 'Idempotency-Key': operation });
assert.equal(replacement.data.items.length, 3);
assert.deepEqual(replacement.data.effects.replaced_products, [{ public_id: restricted[0].public_id, name: restricted[0].name }]);
const expected = [restricted[1], ...regular].reduce((sum, product) => sum + BigInt(product.price.amount_minor), 0n);
assert.equal(replacement.data.subtotal_minor, expected.toString());
assert(expected > BigInt(Number.MAX_SAFE_INTEGER));
assert.deepEqual(await call('POST', path + '/items', { product: restricted[1].public_id }, 200, { 'Idempotency-Key': operation }), replacement);
await call('POST', path + '/items', { product: restricted[0].public_id }, 422, { 'Idempotency-Key': operation });
await call('POST', path + '/items', { product: restricted[0].public_id, quantity: 2 }, 422, { 'Idempotency-Key': randomUUID() });
checks.push('mixed categories, atomic replacement, stable notice replay and exact BigInt subtotal above native range');
const summary = await call('GET', path);
assert.deepEqual(summary.data.effects.replaced_products, []);
const versions = Object.fromEntries(summary.data.items.map(line => [line.product.public_id, { price: line.product.price.version, terms: line.product.terms.version }]));
check({ versions }, schemas.CartConfirmRequest);
assert.equal((await call('POST', path + '/confirm', { versions }, 200, { 'Idempotency-Key': randomUUID() })).data.selection_valid, true);
for (const line of summary.data.items) {
    await call('DELETE', `${path}/items/${line.product.public_id}`, {}, 200, { 'Idempotency-Key': randomUUID() });
}
const empty = await call('GET', path);
assert.equal(empty.data.items.length, 0);
assert.equal(empty.data.subtotal_minor, '0');
checks.push('reviewed offer versions, removal, empty cart, strict schemas and private no-store responses');
let limited = false;
for (let i = 0; i < 121; i++) {
    const response = await fetch(new URL(path, origin), { headers: { Authorization: `Bearer ${token}`, 'X-Cart-Proof': proof } });
    if (response.status === 429) {
        assert(response.headers.get('retry-after'));
        check(await response.json(), schemas.ErrorResponse);
        limited = true;
        break;
    }
    assert.equal(response.status, 200);
}
assert(limited, 'Rate limiting must persist across real HTTP requests.');
checks.push('database-backed throttle persists across real HTTP requests');
console.log(JSON.stringify({ result: 'passed', checks }));
