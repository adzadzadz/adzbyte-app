import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
import {readFile} from 'node:fs/promises';
import {checkSchema} from './contract-schema.mjs';
const origin=process.argv[2];
assert.equal(new URL(origin).hostname,'127.0.0.1');
const cookie=process.env.CHECKOUT_HTTP_COOKIE, other=process.env.CHECKOUT_HTTP_OTHER_COOKIE;
const ref=process.env.PAYMENT_HTTP_REFERENCE, attempt=process.env.PAYMENT_HTTP_ATTEMPT, admin=process.env.PAYMENT_HTTP_ADMIN==='1';
const doc=JSON.parse(await readFile(new URL('../../docs/api/openapi.json',import.meta.url))), schemas=doc.components.schemas;
const readPath=admin?'/api/v1/management/payments/'+attempt:'/api/v1/orders/'+ref+'/review';
const commandPath=admin?readPath+'/expire':'/api/v1/orders/'+ref+'/cancel';
assert(doc.paths[admin?'/api/v1/management/payments/{payment}/expire':'/api/v1/orders/{order}/cancel'].post.requestBody);
let csrf;
async function call(method,path,body,status,schema,extra={}) {
 const response=await fetch(origin+path,{method,redirect:'error',headers:{Accept:'application/json',Cookie:cookie,Origin:origin,...(csrf?{'X-CSRF-TOKEN':csrf}:{}),...(body===undefined?{}:{'Content-Type':'application/json'}),...extra},body:body===undefined?undefined:JSON.stringify(body)});
 assert.equal(response.status,status,`${method} ${path}`);
 assert.match(response.headers.get('cache-control'),/no-store/);assert.match(response.headers.get('cache-control'),/private/);
 assert.equal(response.headers.get('etag'),null);
 const json=await response.json();checkSchema(json,schemas[schema],schemas);
 for(const key of ['provider_payload','checkout_url','proof','request_hash','sk_test_'])assert(!JSON.stringify(json).includes(key));
 return json;
}
const readSchema=admin?'PaymentOperationsResponse':'OrderReviewResponse';
const resultSchema=admin?'PaymentOperationsResponse':'PaymentCancellationResponse';
await call('GET',readPath,undefined,401,'ErrorResponse',{Cookie:''});
const initial=await call('GET',readPath,undefined,200,readSchema);csrf=initial.meta.csrf_token;
const body={expected_revision:3,...admin?{note:'Synthetic independent HTTP expiry'}:{}};
checkSchema(body,schemas[admin?'PaymentExpiryCommand':'PaymentCancellationCommand'],schemas);
const headers={'Idempotency-Key':randomUUID()};
await call('POST',commandPath,body,419,'ErrorResponse',{...headers,'X-CSRF-TOKEN':''});
await call('POST',commandPath,body,403,'ErrorResponse',{...headers,Origin:'https://evil.example'});
await call('POST',commandPath,{...body,amount_minor:1},422,'ErrorResponse',headers);
await call('POST',commandPath,{...body,expected_revision:'3'},422,'ErrorResponse',headers);
await call('POST',commandPath+'?extra=1',body,422,'ErrorResponse',headers);
await call('POST',commandPath,body,admin?403:404,'ErrorResponse',{...headers,Cookie:other,'X-CSRF-TOKEN':process.env.PAYMENT_HTTP_OTHER_CSRF});
const result=await call('POST',commandPath,body,admin?200:201,resultSchema,headers);
assert.equal(result.data.order.payment_status,'unpaid');assert.equal(result.data.order.status,admin?'open':'cancelled');
assert.equal(admin?result.data.controls[0].state:result.data.command.state,'confirmed');
const replay=await call('POST',commandPath,body,admin?200:201,resultSchema,headers);
assert.equal(admin?replay.data.controls[0].public_id:replay.data.command.public_id,admin?result.data.controls[0].public_id:result.data.command.public_id);
await call('POST',commandPath,{...body,note:'Changed original command'},409,'ErrorResponse',headers);
let limited=false;
for(let i=0;i<61;i++) {
 const response=await fetch(origin+readPath,{headers:{Accept:'application/json',Cookie:cookie}});
 if(response.status===429){assert(response.headers.get('retry-after'));checkSchema(await response.json(),schemas.ErrorResponse,schemas);limited=true;break;}
 assert.equal(response.status,200);
}
assert(limited);
console.log(JSON.stringify({passed:true,checks:['real encrypted session and CSRF','Origin/owner/capability/strict input','OpenAPI command/projection','verified expiry and protected command replay','persistent throttling']}));
