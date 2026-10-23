// Independent HTTP consumer; intentionally has no Laravel or application imports.
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { checkSchema } from './contract-schema.mjs';

const origin = new URL(process.argv[2] ?? 'http://127.0.0.1:8814');
assert(['127.0.0.1', 'localhost', '[::1]'].includes(origin.hostname), 'Use a disposable localhost test server.');
const contract = JSON.parse(await readFile(new URL('../../docs/api/openapi.json', import.meta.url)));
const schemas = contract.components.schemas;

const check = (value, schema) => checkSchema(value, schema, schemas);

for (const path of ['/api/v1/products', '/api/v1/products/{public_id}']) {
    const documented = contract.paths[path].get.responses['200'].content['application/json'];
    check(documented.example, documented.schema);
}

let path = '/api/v1/products?per_page=1';
let products = 0;
const seen = new Set();
while (path) {
    assert(!seen.has(path), 'Pagination loop');
    seen.add(path);
    assert(path.startsWith('/api/v1/products?'), 'Pagination must stay on the catalog path');
    const response = await fetch(new URL(path, origin), { headers: { Accept: 'application/json' } });
    assert.equal(response.status, 200);
    assert.match(response.headers.get('cache-control'), /no-cache/);
    const payload = await response.json();
    check(payload, schemas.ProductListResponse);
    const conditional = await fetch(new URL(path, origin), { headers: { 'If-None-Match': response.headers.get('etag') } });
    assert.equal(conditional.status, 304);
    assert.equal(await conditional.text(), '');
    for (const product of payload.data) {
        const detail = await fetch(new URL(`/api/v1/products/${product.public_id}`, origin));
        assert.equal(detail.status, 200);
        const result = await detail.json();
        check(result, schemas.ProductResponse);
        assert.deepEqual(result.data, product);
        const enhanced = await fetch(new URL(`/api/v1/products/${product.public_id}?include=category`, origin));
        assert.equal(enhanced.status, 200);
        const included = await enhanced.json();
        check(included, schemas.ProductResponse);
        assert(Object.hasOwn(included.data, 'category'));
        const { category, ...original } = included.data;
        assert.deepEqual(original, product);
        if (category !== null) assert.equal(typeof category.limit_one_per_cart, 'boolean');
        if (product.price !== null) assert(BigInt(product.price.amount_minor) >= 0n);
        products++;
    }
    path = payload.links.next;
    assert(seen.size < 100, 'Run this consumer against a small synthetic fixture catalog.');
}
assert(products > 0, 'Seed synthetic published fixtures before verifying.');
const invalid = await fetch(new URL('/api/v1/products?include=changes', origin));
assert.equal(invalid.status, 422);
check(await invalid.json(), schemas.ErrorResponse);
assert.match(invalid.headers.get('cache-control'), /no-store/);
console.log(JSON.stringify({ result: 'passed', products, pages: seen.size, checks: ['OpenAPI examples', 'response schemas', 'pagination', 'detail parity', 'opt-in category compatibility', 'exact BigInt money', 'ETag/304', 'validation/no-store'] }));
