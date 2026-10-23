import assert from 'node:assert/strict';

// Only the JSON Schema keywords used in this repository's OpenAPI contract.
export function checkSchema(value, schema, schemas) {
    if (schema.$ref) return checkSchema(value, schemas[schema.$ref.split('/').at(-1)], schemas);
    for (const keyword of ['anyOf', 'oneOf']) {
        if (!schema[keyword]) continue;
        const matches = schema[keyword].filter(candidate => {
            try { checkSchema(value, candidate, schemas); return true; }
            catch (error) { if (error.code === 'ERR_ASSERTION') return false; throw error; }
        }).length;
        assert(keyword === 'anyOf' ? matches >= 1 : matches === 1, `Invalid ${keyword} selection`);
        if (!schema.type) return;
    }
    if (!schema.type && schema.const !== undefined) { assert.equal(value, schema.const); return; }
    const types = [].concat(schema.type);
    const type = value === null ? 'null' : Array.isArray(value) ? 'array' : typeof value === 'number' && Number.isInteger(value) ? 'integer' : typeof value;
    assert(types.includes(type), `Expected ${types}, received ${type}`);
    if (value === null) return;
    if (schema.const !== undefined) assert.equal(value, schema.const);
    if (schema.pattern) assert(new RegExp(schema.pattern).test(value));
    if (schema.minimum !== undefined) assert(value >= schema.minimum);
    if (schema.maximum !== undefined) assert(value <= schema.maximum);
    if (typeof value === 'string') {
        if (schema.minLength !== undefined) assert([...value].length >= schema.minLength);
        if (schema.maxLength !== undefined) assert([...value].length <= schema.maxLength);
    }
    if (type === 'object') {
        for (const key of schema.required ?? []) assert(Object.hasOwn(value, key), `Missing ${key}`);
        if (schema.minProperties !== undefined) assert(Object.keys(value).length >= schema.minProperties);
        if (schema.maxProperties !== undefined) assert(Object.keys(value).length <= schema.maxProperties);
        for (const [key, item] of Object.entries(value)) {
            assert(schema.properties?.[key] || schema.additionalProperties !== false, `Unexpected ${key}`);
            if (schema.propertyNames) checkSchema(key, schema.propertyNames, schemas);
            const child = schema.properties?.[key] ?? (typeof schema.additionalProperties === 'object' ? schema.additionalProperties : null);
            if (child) checkSchema(item, child, schemas);
        }
    }
    if (type === 'array') {
        if (schema.minItems !== undefined) assert(value.length >= schema.minItems);
        if (schema.maxItems !== undefined) assert(value.length <= schema.maxItems);
        for (const item of value) checkSchema(item, schema.items, schemas);
    }
}
