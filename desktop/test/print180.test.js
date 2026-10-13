'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {createPrintBridge}=require('../print-bridge'),{createLocalServer}=require('../server');

test('only a privately verified original cash document can pulse the drawer; web text cannot inject ESC/POS',async t=>{
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-drawer-'));t.after(()=>fs.rmSync(dir,{recursive:true,force:true}));const sent=[];
 const bridge=await createPrintBridge({dataDir:dir,port:0,print:async job=>{sent.push(job);return {status:'sent_to_printer'};}});t.after(()=>bridge.close());
 await bridge.print({id:'web-job',document:'receipt',text:'Sale\x1bp\x00\x19\xfa',drawer:true});assert.equal(sent[0].drawer,undefined);assert.equal(sent[0].text.includes('\x1b'),false);
 await bridge.printVerified({id:'verified-sale',document:'receipt',text:'Receipt',drawer:true});assert.equal(sent[1].drawer,true);
 await bridge.printVerified({id:'verified-command',document:'kitchen',text:'Kitchen',drawer:true});assert.equal(sent[2].drawer,undefined);
 await bridge.printVerified({id:'verified-copy',document:'receipt',text:'Copy',drawer:true,copy:true});assert.equal(sent[3].drawer,undefined);
 await bridge.printVerified({id:'demo',document:'receipt',text:'Demo',drawer:true,demo:true});assert.equal(sent[4].drawer,undefined);assert.equal(sent[4].printer.type,'browser');
});

test('a lost server print ACK is durable and retried after restart without printing another receipt',async t=>{
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-print-ack-'));t.after(()=>fs.rmSync(dir,{recursive:true,force:true}));let printed=0,ackLost=true,claimed=false,received=0;
 const remote=async(url,options)=>{const body=JSON.parse(options.body);if(url.endsWith('print-claim')){if(claimed)return {ok:true,json:async()=>({ok:true,job:null})};claimed=true;return {ok:true,json:async()=>({ok:true,job:{id:1,claim:'c'.repeat(32),payload:{id:'saved-receipt',text:'Receipt'}}})};}assert.equal(body.id,1);if(ackLost)throw new Error('lost ACK');received++;return {ok:true,json:async()=>({ok:true})};};
 let service=await createLocalServer({dataDir:dir,port:0,host:'127.0.0.1',autoSync:false,fetch:remote,print:async()=>{printed++;return {status:'sent_to_printer'};}});t.after(()=>service.close());service.store.commit({...service.store.data,connection:{url:'https://fisitaap.example',token:'private'}});
 const result=await service.flushPrint();assert.equal(result.ok,false);assert.equal(printed,1);assert.equal(service.store.data.printAcks.length,1);await new Promise(resolve=>service.server.close(resolve));
 service=await createLocalServer({dataDir:dir,port:0,host:'127.0.0.1',autoSync:false,fetch:remote,print:async()=>{printed++;return {status:'sent_to_printer'};}});ackLost=false;await service.flushPrint();assert.equal(printed,1);assert.equal(received,1);assert.equal(service.store.data.printAcks.length,0);
});
