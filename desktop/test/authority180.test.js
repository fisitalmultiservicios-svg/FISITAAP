'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),crypto=require('node:crypto');
const {createLocalServer}=require('../server');

test('web cash authority keeps the paired device secret native and forwards only a session-bound challenge',async t=>{
  const dir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-authority-'));t.after(()=>fs.rmSync(dir,{recursive:true,force:true}));
  const token=crypto.randomBytes(32).toString('hex'),nonce=crypto.randomBytes(32).toString('hex'),calls=[];
  const service=await createLocalServer({dataDir:dir,port:0,host:'127.0.0.1',autoSync:false,tokenCodec:{encode:x=>'private:'+x,decode:x=>x.slice(8)},fetch:async(url,options)=>{calls.push({url,options});return {ok:true,json:async()=>({ok:true,proof:'ab'.repeat(100)+'.'+'c'.repeat(64)})};}});
  t.after(()=>service.close());service.store.commit({...service.store.data,connection:{url:'https://fisitaap.example',token:'private:'+token}});
  const proof=await service.authorize({nonce,user_id:42});
  assert.equal(proof.ok,true);assert.equal(JSON.stringify(proof).includes(token),false);assert.equal(calls.length,1);
  assert.equal(calls[0].url,'https://fisitaap.example/api/desktop/web-session');assert.deepEqual(JSON.parse(calls[0].options.body),{nonce,user_id:42});assert.equal(calls[0].options.headers.Authorization,'Bearer '+token);
  assert.throws(()=>service.authorize({nonce:'spoof',user_id:42}),/inválida/);assert.throws(()=>service.authorize({nonce,user_id:'42'}),/inválida/);assert.equal(calls.length,1);
});

test('the production local register rejects an ordinary browser even with matching Origin and login data',async t=>{
  const dir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-local-authority-'));t.after(()=>fs.rmSync(dir,{recursive:true,force:true}));
  const service=await createLocalServer({dataDir:dir,port:0,host:'127.0.0.1',autoSync:false,requireNative:true});t.after(()=>service.close());
  const response=await fetch(service.url+'/api/login',{method:'POST',headers:{Origin:service.url,'Content-Type':'application/json'},body:JSON.stringify({user_id:1,password:'example',register:'Box'})});
  assert.equal(response.status,403);assert.match((await response.json()).error,/únicamente/);
  assert.equal((await fetch(service.url+'/api/state')).status,403);
  const internal=await service.localRequest({path:'/api/state'});assert.equal(internal.status,401);assert.match(internal.body.error,/Ingresa/);
  assert.equal((await service.localRequest({path:'/api/setup'})).status,200);
  await assert.rejects(service.localRequest({path:'https://attacker.test/api/state'}),/no disponible/);
  assert.equal(service.nativeSecret,undefined);
});

test('the same web shift survives offline sales, failed refresh, acknowledgments and restart without double cash',async t=>{
  const {adoptCashState,expectedCash,LocalStore}=require('../server');
  const dir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-shared-shift-'));t.after(()=>fs.rmSync(dir,{recursive:true,force:true}));
  const journal=new LocalStore(dir),shiftId=crypto.randomUUID(),saleId=crypto.randomUUID();
  let state={...journal.data,snapshot:{id:'catalog',tenant:{id:1},branch:{id:2}},sales:[],shifts:[]};
  const cash={version:180,tenant_id:1,device_id:3,branch_id:2,register:{id:4,name:'Caja autorizada'},shifts:[{id:shiftId,server_id:10,user_id:5,register:'Caja autorizada',opening_cash:10000,opened_at:new Date().toISOString(),server_cash_net:20000,confirmed_max:20}],acknowledged:[]};
  adoptCashState(state,cash);assert.equal(state.shifts.length,1);assert.equal(state.shifts[0].id,shiftId);assert.equal(expectedCash(state,state.shifts[0]),30000);
  state.sales.push({id:saleId,shift_id:shiftId,cashApplied:5000,synced:false});assert.equal(expectedCash(state,state.shifts[0]),35000);
  // ACK persisted, but the subsequent catalog request failed: the local cash remains counted.
  Object.assign(state.sales[0],{synced:true,webSale:21});assert.equal(expectedCash(state,state.shifts[0]),35000);
  journal.commit(state);state=new LocalStore(dir).data;assert.equal(expectedCash(state,state.shifts[0]),35000);
  adoptCashState(state,{...cash,shifts:[{...cash.shifts[0],server_cash_net:25000,confirmed_max:21}],acknowledged:[{id:saleId,sale_id:21}]});
  assert.equal(expectedCash(state,state.shifts[0]),35000);assert.equal(state.shifts.length,1);
  assert.throws(()=>adoptCashState(state,{...cash,tenant_id:99}),/incompatible/);
  assert.throws(()=>adoptCashState(state,{...cash,device_id:99}),/otro equipo/);
});

test('an authoritative web close cannot silently allow more sales in the adopted offline shift',()=>{
  const {adoptCashState}=require('../server'),id=crypto.randomUUID();
  const state={snapshot:{id:'cat',tenant:{id:1},branch:{id:2}},shifts:[{id,user_id:4,opening_cash:100,server_id:10,closed_at:null}],sales:[{id:crypto.randomUUID(),shift_id:id,synced:false}]};
  adoptCashState(state,{version:180,tenant_id:1,branch_id:2,device_id:3,shifts:[],acknowledged:[]});
  assert.equal(state.shifts[0].server_closed,true);assert.equal(state.sales[0].synced,false);
});
