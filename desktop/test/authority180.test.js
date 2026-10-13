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

test('a confirmed web payment clears its uncertainty marker with the same authoritative cash snapshot',()=>{
 const {adoptCashState,expectedCash}=require('../server'),id=crypto.randomUUID(),key=crypto.randomBytes(16).toString('hex');
 const state={snapshot:{id:'cat',tenant:{id:1},branch:{id:2}},shifts:[],sales:[],webPayments:[{key,user_id:4}]};
 adoptCashState(state,{version:180,tenant_id:1,branch_id:2,device_id:3,shifts:[{id,user_id:4,server_id:10,register:'Caja',opening_cash:100,opened_at:new Date().toISOString(),server_cash_net:5000,confirmed_max:20}],acknowledged:[],web_payments:[key]});
 assert.deepEqual(state.webPayments,[]);assert.equal(expectedCash(state,state.shifts[0]),5100);
});

test('offline 180 honors action permissions and uses the assigned box rather than a typed register name',async t=>{
 const bcrypt=require('bcryptjs'),dir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-offline-permissions-'));t.after(()=>fs.rmSync(dir,{recursive:true,force:true}));
 const service=await createLocalServer({dataDir:dir,port:0,host:'127.0.0.1',autoSync:false,requireNative:true});t.after(()=>service.close());const password=crypto.randomBytes(12).toString('hex'),id=crypto.randomUUID();
 const snapshot={id:'catalog',tenant:{id:1,name:'Test',tax_included:false},branch:{id:2,name:'Principal'},offline_policy:{version:180,allowed:true,generation:1},cash_state:{version:180,device_id:3,tenant_id:1,branch_id:2,register:{name:'Assigned box'},shifts:[],acknowledged:[]},users:[{id:4,name:'Cashier',password_hash:bcrypt.hashSync(password,4),permissions:{shift:false,discount:false,create_customer:false,release:false}}],categories:[],customers:[],products:[{id:1,name:'Item',price:1000,rate:0,min:1000,step:1000,stock:10000,track:true,groups:[],components:[]}]};
 service.store.commit({...service.store.data,snapshot,stocks:{1:10000},shifts:[{id,user_id:4,register:'Assigned box',opening_cash:0,opened_at:new Date().toISOString(),closed_at:null,syncedClosed:false}]});
 const login=await service.localRequest({path:'/api/login',data:{user_id:4,password,register:'An arbitrary second box'}});assert.equal(login.status,200);const csrf=login.body.csrf;
 const read=await service.localRequest({path:'/api/state'});assert.equal(read.body.register,'Assigned box');assert.equal(read.body.user.permissions.discount,false);
 for(const [route,data]of [['/api/shift/open',{opening_cash:0}],['/api/shift/close',{closing_cash:0}],['/api/primary/release',{}]]){const out=await service.localRequest({path:route,csrf,data});assert.equal(out.status,422);assert.match(out.body.error,/rol no permite/);}
 const sale={id:crypto.randomUUID(),items:[{id:1,quantity:1000,options:[]}],payments:{cash:1000,card:0,sinpe:0},customer:{}};
 const discount=await service.localRequest({path:'/api/sales',csrf,data:{...sale,discount:100}});assert.equal(discount.status,422);assert.match(discount.body.error,/rol no permite/);assert.equal(service.store.data.sales.length,0);assert.equal(service.store.data.stocks[1],10000);
 const create=await service.localRequest({path:'/api/sales',csrf,data:{...sale,customer:{name:'New',phone:'88888888'}}});assert.equal(create.status,422);assert.equal(service.store.data.sales.length,0);
 const permitted=await service.localRequest({path:'/api/sales',csrf,data:sale});assert.equal(permitted.status,200);assert.equal(service.store.data.sales.length,1);
});
