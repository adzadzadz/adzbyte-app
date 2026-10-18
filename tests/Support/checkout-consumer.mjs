import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
import {readFile} from 'node:fs/promises';
import {checkSchema} from './contract-schema.mjs';
const origin=process.argv[2];
assert.equal(new URL(origin).hostname,'127.0.0.1');
const cookie=process.env.CHECKOUT_HTTP_COOKIE, other=process.env.CHECKOUT_HTTP_OTHER_COOKIE;
assert(cookie&&other,'Expected disposable encrypted sessions.');
const schemas=JSON.parse(await readFile(new URL('../../docs/api/openapi.json',import.meta.url))).components.schemas;
let csrf;
const checks=[];
async function call(method,path,body,status,schema,extra={}){
 const r=await fetch(origin+path,{method,redirect:'error',headers:{Accept:'application/json',Cookie:cookie,Origin:origin,...(csrf?{'X-CSRF-TOKEN':csrf}:{}),...(body===undefined?{}:{'Content-Type':'application/json'}),...extra},body:body===undefined?undefined:JSON.stringify(body)});
 assert.equal(r.status,status,`Unexpected ${method} ${path} status`);
 assert.match(r.headers.get('cache-control'),/no-store/);assert.match(r.headers.get('cache-control'),/private/);assert.equal(r.headers.get('etag'),null);
 const j=await r.json();checkSchema(j,schemas[schema],schemas);assert(!JSON.stringify(j).includes(cookie));
 return {j,r};
}
await call('GET','/api/v1/checkout',undefined,401,'ErrorResponse',{Cookie:''});
const {j:review}=await call('GET','/api/v1/checkout',undefined,200,'CheckoutResponse');csrf=review.meta.csrf_token;
assert.equal(review.data.totals.total_minor,'12500');assert.equal(review.data.policy.tax_mode,'unconfigured');
const input={brief:{goal:'Synthetic HTTP goal',scope:'Synthetic HTTP scope'},billing:{name:'Synthetic buyer',email:'buyer@example.test',country:'PH'},accept_terms:true,fingerprint:review.data.fingerprint};
checkSchema(input,schemas.OrderSubmission,schemas);const uuid=randomUUID();
await call('POST','/api/v1/orders',input,419,'ErrorResponse',{'Idempotency-Key':uuid,'X-CSRF-TOKEN':''});
await call('POST','/api/v1/orders',input,403,'ErrorResponse',{'Idempotency-Key':uuid,Origin:'https://evil.example'});
await call('POST','/api/v1/orders',{...input,user_id:1},422,'ErrorResponse',{'Idempotency-Key':uuid});
await call('GET','/api/v1/checkout?owner=1',undefined,422,'ErrorResponse');
checks.push('real encrypted session authentication, CSRF, exact Origin and strict owner/query rejection');
const {j:created}=await call('POST','/api/v1/orders',input,201,'OrderResponse',{'Idempotency-Key':uuid});
const ref=created.data.public_id;assert.equal(created.data.approval_status,'pending');assert.equal(created.data.payment_status,'unpaid');assert.equal(created.data.accepted.lines[0].quantity,1);
assert.equal((await call('POST','/api/v1/orders',input,201,'OrderResponse',{'Idempotency-Key':uuid})).j.data.public_id,ref);
await call('POST','/api/v1/orders',{...input,brief:{...input.brief,goal:'Changed'}},409,'ErrorResponse',{'Idempotency-Key':uuid});
await call('GET','/api/v1/orders/'+ref,undefined,200,'OrderResponse');
const {j:list}=await call('GET','/api/v1/orders?per_page=1',undefined,200,'OrderListResponse');assert.equal(list.meta.pagination.total,1);
await call('GET','/api/v1/orders/'+ref,undefined,404,'ErrorResponse',{Cookie:other});
assert.equal((await call('GET','/api/v1/orders',undefined,200,'OrderListResponse',{Cookie:other})).j.data.length,0);
checks.push('actual submission/read/list OpenAPI consumer, one order on retry, changed-input conflict and cross-owner denial');
for(const key of ['user_id','actor_id','request_hash','content_hash','policy_version_id','cart_id'])assert(!JSON.stringify(created).includes('"'+key+'"'));
let limited=false;
for(let i=0;i<61;i++){
 const r=await fetch(origin+'/api/v1/checkout',{headers:{Accept:'application/json',Cookie:cookie}});
 if(r.status===429){assert(r.headers.get('retry-after'));checkSchema(await r.json(),schemas.ErrorResponse,schemas);limited=true;break;}
 assert.equal(r.status,200);
}
assert(limited);checks.push('private no-store projections, absent internal fields and persistent named throttling');
console.log(JSON.stringify({passed:true,checks}));
