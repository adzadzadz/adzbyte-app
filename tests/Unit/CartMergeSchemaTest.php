<?php

namespace Tests\Unit;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class CartMergeSchemaTest extends TestCase
{
    public function test_external_schema_checker_enforces_nullable_merge_and_strict_choice_contracts(): void
    {
        $script = <<<'JS'
            import fs from 'node:fs';
            import assert from 'node:assert/strict';
            import { checkSchema } from './tests/Support/contract-schema.mjs';
            const s = JSON.parse(fs.readFileSync('docs/api/openapi.json','utf8')).components.schemas;
            const ref = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
            const cart = {public_id:ref,revision:0,currency:'PHP',subtotal_minor:'0',subtotal_complete:true,selection_valid:false,requires_review:false,category_conflicts:[],items:[],effects:{replaced_products:[]}};
            const value = {data:{fingerprint:'a'.repeat(64),incoming:cart,saved:null,conflicts:[]},meta:{api_version:'v1'}};
            checkSchema(value,s.CartMergeResponse,s);
            checkSchema({...value,data:{...value.data,saved:cart}},s.CartMergeResponse,s);
            assert.throws(()=>checkSchema({...value,data:{...value.data,owner_id:1}},s.CartMergeResponse,s));
            assert.throws(()=>checkSchema({...value,data:{...value.data,saved:{user_id:1}}},s.CartMergeResponse,s));
            assert.throws(()=>checkSchema({...value,data:{...value.data,conflicts:[{key:ref,category:'Synthetic',products:[{public_id:ref,name:'Only one'}]}]}},s.CartMergeResponse,s));
            for(const choices of [{},[],{[ref]:ref}]) checkSchema({fingerprint:'a'.repeat(64),choices},s.CartAttachRequest,s);
            assert.throws(()=>checkSchema({fingerprint:'a'.repeat(64),choices:[ref]},s.CartAttachRequest,s));
            assert.throws(()=>checkSchema({fingerprint:'a'.repeat(64),choices:{owner:ref}},s.CartAttachRequest,s));
            assert.throws(()=>checkSchema({fingerprint:'a'.repeat(64),choices:{},user_id:1},s.CartAttachRequest,s));
            checkSchema({data:{retired:true},meta:{api_version:'v1'}},s.CartRetiredResponse,s);
            assert.throws(()=>checkSchema({data:{retired:true,target:ref},meta:{api_version:'v1'}},s.CartRetiredResponse,s));
            console.log('Merge/retirement contract positive and forbidden shapes passed.');
            JS;
        $process = new Process(['node', '--input-type=module', '-e', $script], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
    }
}
