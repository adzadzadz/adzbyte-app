import assert from 'node:assert/strict';
import {createHmac} from 'node:crypto';
import {readFile} from 'node:fs/promises';
import {checkSchema} from './contract-schema.mjs';
const origin=process.argv[2];
assert.equal(new URL(origin).hostname,'127.0.0.1');
const schemas=JSON.parse(await readFile(new URL('../../docs/api/openapi.json',import.meta.url))).components.schemas;
const payload=JSON.parse(process.env.PAYMENT_HTTP_EVENT);
checkSchema(payload,schemas.PayMongoEvent,schemas);
const body=JSON.stringify(payload), timestamp=Math.floor(Date.now()/1000);
const signature='t='+timestamp+',te='+createHmac('sha256','whsec_synthetic_payment_events_only').update(timestamp+'.'+body).digest('hex')+',li=';
async function call(raw,header,status,suffix='') {
 const response=await fetch(origin+'/api/v1/webhooks/paymongo'+suffix,{method:'POST',redirect:'error',headers:{'Content-Type':'application/json','Paymongo-Signature':header},body:raw});
 assert.equal(response.status,status);
 assert.match(response.headers.get('cache-control'),/no-store/);
 const json=await response.json();checkSchema(json,schemas[status===200?'PayMongoAcknowledgement':'ErrorResponse'],schemas);
 if(status===200)assert.deepEqual(json,{received:true});
}
await call(body,'',401);
await call(body+' ',signature,401);
await call(body,signature,422,'?owner=1');
await call(body,signature,200);
await call(body,signature,200);
const method=await fetch(origin+'/api/v1/webhooks/paymongo');assert.equal(method.status,405);
console.log(JSON.stringify({passed:true,checks:['actual raw HTTP body and signature header','no customer session or CSRF bypass','tampered bytes and strict query rejected','canonical duplicate acknowledges once','OpenAPI receipt/error envelopes']}));
