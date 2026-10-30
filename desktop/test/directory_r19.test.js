'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),crypto=require('node:crypto');
const {createLocalServer}=require('../server');

test('R19 device directory challenge passes through the existing installed bridge without granting cash, exposing credentials or rewriting pending sales',async t=>{
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-directory-r19-'));t.after(()=>fs.rmSync(dir,{recursive:true,force:true}));
 const deviceToken=crypto.randomBytes(32).toString('hex'),nonce='d19ad19ad19ad19ad19ad19ad19ad19a'+crypto.randomBytes(16).toString('hex');let sent;
 const service=await createLocalServer({dataDir:dir,port:0,host:'127.0.0.1',autoSync:false,tokenCodec:{encode:x=>'private:'+x,decode:x=>x.slice(8)},fetch:async(url,options)=>{sent={url,data:JSON.parse(options.body)};return {ok:true,json:async()=>({ok:true,cash:false,proof:'opaque-device-directory-proof'})};}});t.after(()=>service.close());
 const oldUser={id:55,name:'Old cached user',email:'cached@example.invalid',password_hash:'private-offline-hash',permissions:{shift:true}};
 service.store.commit({...service.store.data,connection:{url:'https://fisitaap.example',token:'private:'+deviceToken},snapshot:{id:'old-snapshot',users:[oldUser],tenant:{id:1},branch:{id:2}},sales:[{id:crypto.randomUUID(),shift_id:'pending-shift',synced:false}],shifts:[{id:'pending-shift',user_id:55}],authorizedWebUser:null});
 const before=JSON.parse(JSON.stringify(service.store.data)),reply=await service.authorize({nonce,user_id:55});
 assert.deepEqual(reply,{ok:true,proof:'opaque-device-directory-proof',cash:false});assert.equal(sent.url,'https://fisitaap.example/api/desktop/web-session');assert.equal(sent.data.nonce,nonce);assert.equal(sent.data.user_id,55);
 assert.deepEqual(service.store.data.snapshot,before.snapshot);assert.deepEqual(service.store.data.sales,before.sales);assert.deepEqual(service.store.data.shifts,before.shifts);assert.equal(service.store.data.authorizedWebUser,null);assert.equal(JSON.stringify(reply).includes(deviceToken),false);assert.equal(JSON.stringify(reply).includes(oldUser.password_hash),false);
});
