'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),crypto=require('node:crypto'),bcrypt=require('bcryptjs');
const {createLocalServer}=require('../server');
const password=crypto.randomBytes(16).toString('hex');
const userHash=bcrypt.hashSync(password,4);
async function environment(t){
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-primary179-'));let allowed=false,generation=1,offline=false,failRelease=false,unauthorized=false;
 const snap=()=>({id:'a'.repeat(32),generated_at:new Date().toISOString(),offline_policy:{version:179,allowed,generation},tenant:{id:1,name:'Test',tax_included:false},branch:{id:1,name:'Test'},products:[{id:1,name:'P',price:1000,rate:0,min:1000,step:1000,stock:10000,track:true,allow_negative:false,groups:[],components:[]}],users:[{id:1,name:'Cashier',password_hash:userHash}],customers:[],categories:[]});
 const remote=async(url,init)=>{
  if(offline)throw Error('offline');
  if(unauthorized)return Response.json({ok:false,error:'desactivado'},{status:401});
  if(url.endsWith('/pair'))return Response.json({ok:true,token:'a'.repeat(64),snapshot:snap()});
  if(url.endsWith('/snapshot'))return Response.json({ok:true,snapshot:snap()});
  if(url.endsWith('/release')){if(failRelease)throw Error('confirmation lost');allowed=false;generation++;return Response.json({ok:true});}
  const data=JSON.parse(init.body);return Response.json({ok:true,acknowledged:data.sales.map((s,i)=>({id:s.id,sale_id:i+1})),errors:[],closed_shifts:data.shifts.filter(s=>s.closed_at).map(s=>s.id)});
 };
 let service=await createLocalServer({dataDir:dir,port:0,autoSync:false,fetch:remote});
 const request=async(route,data,actor={},base=service.url)=>{const r=await fetch(base+route,{method:data===undefined?'GET':'POST',headers:{Host:new URL(base).host,Origin:base,'Content-Type':'application/json',Cookie:actor.cookie||'','X-CSRF-Token':actor.csrf||''},body:data===undefined?undefined:JSON.stringify(data)});return {status:r.status,data:await r.json(),cookie:r.headers.get('set-cookie')?.split(';')[0]};};
 await request('/api/pair',{url:'https://web.test',device_id:1,code:'test'});
 const login=await request('/api/login',{user_id:1,register:'Main',password});assert.equal(login.status,200);
 let actor={cookie:login.cookie,csrf:login.data.csrf};
 t.after(async()=>{await new Promise(r=>service.server.close(r));fs.rmSync(dir,{recursive:true,force:true});});
 return {get service(){return service;},get actor(){return actor;},request,grant:async()=>{allowed=true;await service.sync();},offline:value=>offline=value,unauthorized:()=>unauthorized=true,failRelease:value=>failRelease=value,revision:value=>generation=value,
  sale:()=>({id:crypto.randomUUID(),items:[{id:1,quantity:1000,options:[]}],payments:{cash:1000},customer:{}}),
  restart:async()=>{await new Promise(r=>service.server.close(r));service=await createLocalServer({dataDir:dir,port:0,autoSync:false,fetch:remote});const login=await request('/api/login',{user_id:1,register:'Main',password});actor={cookie:login.cookie,csrf:login.data.csrf};}};
}
test('unassigned and legacy catalogs fail closed; assigned primary sells through an internet outage',async t=>{
 const e=await environment(t);assert.equal((await e.request('/api/shift/open',{opening_cash:0},e.actor)).status,403);
 await e.grant();e.offline(true);assert.equal((await e.request('/api/shift/open',{opening_cash:0},e.actor)).status,200);
 const sale=e.sale();assert.equal((await e.request('/api/sales',sale,e.actor)).status,200);assert.equal((await e.request('/api/sales',sale,e.actor)).status,200);assert.equal(e.service.store.data.sales.length,1);
 await e.restart();assert.equal(e.service.isPrincipal(),true);assert.equal(e.service.store.data.sales[0].synced,false);
 const next=structuredClone(e.service.store.data);delete next.snapshot.offline_policy;e.service.store.commit(next);
 assert.equal((await e.request('/api/sales',e.sale(),e.actor)).status,403);assert.equal(e.service.store.data.sales.length,1);
});
test('a secondary using the same local network cannot log in or charge on the primary',async t=>{
 const e=await environment(t);await e.grant();await e.request('/api/shift/open',{opening_cash:0},e.actor);
 const address=Object.values(os.networkInterfaces()).flat().find(x=>x&&x.family==='IPv4'&&!x.internal);assert(address,'LAN fixture required');
 const lan='http://'+address.address+':'+e.service.port;
 assert.equal((await e.request('/api/login',{user_id:1,register:'Second',password},{},lan)).status,403);
 assert.equal((await e.request('/api/sales',e.sale(),e.actor,lan)).status,403);assert.equal(e.service.store.data.sales.length,0);
});
test('release requires closed synchronized shifts and survives a lost confirmation or restart',async t=>{
 const e=await environment(t);await e.grant();await e.request('/api/shift/open',{opening_cash:0},e.actor);await e.request('/api/sales',e.sale(),e.actor);
 assert.equal((await e.request('/api/primary/release',{},e.actor)).status,422);assert(e.service.isPrincipal());
 await e.request('/api/shift/close',{closing_cash:1000},e.actor);await e.service.sync();e.failRelease(true);
 assert.equal((await e.request('/api/primary/release',{},e.actor)).status,422);assert.equal(e.service.isPrincipal(),false);
 await e.restart();assert.equal(e.service.isPrincipal(),false);await e.service.sync();assert.equal(e.service.isPrincipal(),false);
 e.failRelease(false);assert.equal((await e.request('/api/primary/release',{},e.actor)).status,200);assert.equal(e.service.isPrincipal(),false);
 await e.grant();assert.equal(e.service.isPrincipal(),true); // A fresh generation from a deliberate owner assignment.
});
test('a real revocation blocks future local sales but never deletes the outbox',async t=>{
 const e=await environment(t);await e.grant();await e.request('/api/shift/open',{opening_cash:0},e.actor);await e.request('/api/sales',e.sale(),e.actor);
 e.unauthorized();await e.service.sync();assert.equal(e.service.isPrincipal(),false);assert.equal(e.service.store.data.sales.length,1);assert.equal(e.service.store.data.sales[0].synced,false);
 assert.equal((await e.request('/api/sales',e.sale(),e.actor)).status,403);
});
