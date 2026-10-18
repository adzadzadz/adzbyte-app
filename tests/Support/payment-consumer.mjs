import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
import {readFile} from 'node:fs/promises';
import {checkSchema} from './contract-schema.mjs';

const origin=process.argv[2];
assert.equal(new URL(origin).hostname,'127.0.0.1');
const cookie=process.env.CHECKOUT_HTTP_COOKIE, other=process.env.CHECKOUT_HTTP_OTHER_COOKIE;
const ref=process.env.PAYMENT_HTTP_REFERENCE;
assert(cookie&&other&&ref);
const path='/api/v1/orders/'+ref+'/payment';
const doc=JSON.parse(await readFile(new URL('../../docs/api/openapi.json',import.meta.url)));
assert(doc.paths['/api/v1/orders/{order}/payment'].post.requestBody);
const schemas=doc.components.schemas;
let csrf;
async function call(method,suffix,body,status,schema,extra={}) {
 const response=await fetch(origin+path+suffix,{method,redirect:'error',headers:{Accept:'application/json',Cookie:cookie,Origin:origin,...(csrf?{'X-CSRF-TOKEN':csrf}:{}),...(body===undefined?{}:{'Content-Type':'application/json'}),...extra},body:body===undefined?undefined:JSON.stringify(body)});
 assert.equal(response.status,status,`${method} payment status`);
 assert.match(response.headers.get('cache-control'),/no-store/);assert.match(response.headers.get('cache-control'),/private/);
 assert.equal(response.headers.get('etag'),null);
 const json=await response.json();checkSchema(json,schemas[schema],schemas);
 for(const key of ['provider_payload','provider_session_id','actor_id','failure_code','request_hash','sk_test_'])assert(!JSON.stringify(json).includes(key));
 return json;
}
await call('GET','',undefined,401,'ErrorResponse',{Cookie:''});
const initial=await call('GET','',undefined,200,'PaymentAttemptResponse');
assert.equal(initial.data,null);csrf=initial.meta.csrf_token;
const input={expected_revision:2}, operation=randomUUID();
checkSchema(input,schemas.PaymentAttemptCommand,schemas);
const headers={'Idempotency-Key':operation};
await call('POST','',input,419,'ErrorResponse',{...headers,'X-CSRF-TOKEN':''});
await call('POST','',input,403,'ErrorResponse',{...headers,Origin:'https://evil.example'});
await call('POST','',{...input,amount_minor:1},422,'ErrorResponse',headers);
await call('GET','?owner=1',undefined,422,'ErrorResponse');
const otherCsrf=process.env.PAYMENT_HTTP_OTHER_CSRF;
assert(otherCsrf);
await call('POST','',input,404,'ErrorResponse',{...headers,Cookie:other,'X-CSRF-TOKEN':otherCsrf});
const created=await call('POST','',input,201,'PaymentAttemptResponse',headers);
assert.equal(created.data.state,'active');assert.equal(created.data.amount_minor,'12500');
assert.equal(created.data.checkout_url,'https://checkout.paymongo.com/cs_http#synthetic');
assert.equal((await call('POST','',input,201,'PaymentAttemptResponse',headers)).data.public_id,created.data.public_id);
await call('POST','',{expected_revision:3},409,'ErrorResponse',headers);
await call('POST','',input,409,'ErrorResponse',{'Idempotency-Key':randomUUID()});
assert.equal((await call('GET','',undefined,200,'PaymentAttemptResponse')).data.public_id,created.data.public_id);
await call('GET','',undefined,404,'ErrorResponse',{Cookie:other});
let limited=false;
for(let i=0;i<61;i++) {
 const response=await fetch(origin+path,{headers:{Accept:'application/json',Cookie:cookie}});
 if(response.status===429){assert(response.headers.get('retry-after'));checkSchema(await response.json(),schemas.ErrorResponse,schemas);limited=true;break;}
 assert.equal(response.status,200);
}
assert(limited);
console.log(JSON.stringify({passed:true,checks:['real encrypted sessions and CSRF','Origin and strict input','owned OpenAPI request/response','safe current session and replay/conflict','persistent throttling']}));
