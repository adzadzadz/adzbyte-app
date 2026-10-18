import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
import {readFile} from 'node:fs/promises';
import {checkSchema} from './contract-schema.mjs';
const origin=process.argv[2];
assert.equal(new URL(origin).hostname,'127.0.0.1');
const cookie=process.env.CHECKOUT_HTTP_COOKIE, other=process.env.CHECKOUT_HTTP_OTHER_COOKIE;
const ref=process.env.PAYMENT_HTTP_REFERENCE, event=process.env.PAYMENT_HTTP_EVENT;
assert(cookie&&other&&ref&&event);
const path='/api/v1/management/payments/'+ref;
const doc=JSON.parse(await readFile(new URL('../../docs/api/openapi.json',import.meta.url)));
assert(doc.paths['/api/v1/management/payments/{payment}/reconcile'].post.requestBody);
const schemas=doc.components.schemas;
let csrf;
async function call(method,suffix,body,status,extra={}) {
 const response=await fetch(origin+path+suffix,{method,redirect:'error',headers:{Accept:'application/json',Cookie:cookie,Origin:origin,...(csrf?{'X-CSRF-TOKEN':csrf}:{}),...(body===undefined?{}:{'Content-Type':'application/json'}),...extra},body:body===undefined?undefined:JSON.stringify(body)});
 assert.equal(response.status,status,`${method} payment operations ${suffix}`);
 assert.match(response.headers.get('cache-control'),/no-store/);assert.match(response.headers.get('cache-control'),/private/);
 assert.equal(response.headers.get('etag'),null);
 const json=await response.json();checkSchema(json,schemas[status===200?'PaymentOperationsResponse':'ErrorResponse'],schemas);
 for(const key of ['provider_payload','checkout_url','evidence','request_hash','synthetic_client_key','sk_test_'])assert(!JSON.stringify(json).includes(key));
 return json;
}
await call('GET','',undefined,401,{Cookie:''});
await call('GET','',undefined,403,{Cookie:other});
const initial=await call('GET','',undefined,200);csrf=initial.meta.csrf_token;
assert.equal(initial.data.events[0].state,'rejected');assert.equal(initial.data.order.revision,3);
const body={event,expected_revision:3,note:'Synthetic real HTTP signed-receipt reconciliation'};
checkSchema(body,schemas.PaymentReconciliationCommand,schemas);
const headers={'Idempotency-Key':randomUUID()};
await call('POST','/reconcile',body,419,{...headers,'X-CSRF-TOKEN':''});
await call('POST','/reconcile',body,403,{...headers,Origin:'https://evil.example'});
await call('POST','/reconcile',{...body,amount_minor:1},422,headers);
await call('POST','/reconcile',{...body,expected_revision:'3'},422,headers);
await call('POST','/reconcile',body,403,{...headers,Cookie:other,'X-CSRF-TOKEN':process.env.PAYMENT_HTTP_OTHER_CSRF});
await call('GET','?extra=1',undefined,422);
const result=await call('POST','/reconcile',body,200,headers);
assert.equal(result.data.order.payment_status,'paid');assert.equal(result.data.order.revision,4);
assert.equal(result.data.events[0].state,'rejected');assert.equal(result.data.resolutions.length,1);
assert.equal((await call('POST','/reconcile',body,200,headers)).data.resolutions[0].public_id,result.data.resolutions[0].public_id);
await call('POST','/reconcile',{...body,note:'Changed command'},409,headers);
await call('POST','/reconcile',body,409,{'Idempotency-Key':randomUUID()});
let limited=false;
for(let i=0;i<61;i++) {
 const response=await fetch(origin+path,{headers:{Accept:'application/json',Cookie:cookie}});
 if(response.status===429){assert(response.headers.get('retry-after'));checkSchema(await response.json(),schemas.ErrorResponse,schemas);limited=true;break;}
 assert.equal(response.status,200);
}
assert(limited);
console.log(JSON.stringify({passed:true,checks:['real Admin session/CSRF/Origin','strict OpenAPI command and private projection','customer denial','preserved receipt and immutable resolution replay','persistent throttling']}));
