'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),crypto=require('node:crypto'),bcrypt=require('bcryptjs');
const {createLocalServer}=require('../server');

test('pairing before cashier assignment is repaired by native web authorization and sells offline immediately, after restart, and synchronizes once',async t=>{
  const directory=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-181-'));let service,online=true;const received=new Set();
  const user={id:42,name:'Cashier',password_hash:bcrypt.hashSync('fixture-password',4),permissions:{shift:true,discount:true,create_customer:true,release:true}};
  const cash={version:180,tenant_id:1,branch_id:2,device_id:3,register:{id:4,name:'Assigned register',user_id:42},shifts:[],acknowledged:[]};
  const snapshot={id:'a'.repeat(32),tenant:{id:1,name:'Test',tax_included:false},branch:{id:2,name:'Main'},offline_policy:{version:180,allowed:true,generation:1},cash_state:cash,users:[],categories:[],customers:[],products:[{id:1,name:'Test item',sku:'P1',price:1000,rate:0,min:1000,step:1000,stock:10000,track:true,allow_negative:false,groups:[],components:[]}]};
  const remote=async(url,options)=>{
    if(!online)throw new Error('Network disconnected');const data=JSON.parse(options.body);
    if(url.endsWith('/web-session'))return Response.json({ok:true,proof:'private-proof',cash:true,bound:true,users180:[user],cash_state:cash,offline_policy:snapshot.offline_policy});
    if(url.endsWith('/sync')){for(const sale of data.sales)received.add(sale.id);return Response.json({ok:true,acknowledged:data.sales.map(s=>({id:s.id,sale_id:1})),errors:[],closed_shifts:[]});}
    if(url.endsWith('/snapshot'))throw new Error('Snapshot response lost after acknowledgment');
    throw new Error('Unexpected route');
  };
  const start=async()=>createLocalServer({dataDir:directory,port:0,host:'127.0.0.1',autoSync:false,requireNative:true,fetch:remote});
  t.after(()=>{service?.close();fs.rmSync(directory,{recursive:true,force:true});});service=await start();
  service.store.commit({...service.store.data,connection:{url:'https://example.test',token:'test-device-token'},snapshot,stocks:{1:10000}});
  assert.equal((await service.localRequest({path:'/api/resume',data:{}})).status,401);
  const result=await service.authorize({nonce:'a'.repeat(64),user_id:42,confirm:true});
  assert.equal(result.ok,true);assert.equal(JSON.stringify(result).includes(user.password_hash),false);assert.equal(result.users180,undefined);
  assert.equal(service.store.data.snapshot.users[0].id,42);assert.equal(service.store.data.authorizedWebHash,user.password_hash);
  online=false;const resume=await service.localRequest({path:'/api/resume',data:{}});assert.equal(resume.status,200);let csrf=resume.body.csrf;
  assert.equal((await service.localRequest({path:'/api/shift/open',data:{opening_cash:0},csrf})).status,200);
  const id=crypto.randomUUID(),sale={id,items:[{id:1,quantity:1000,options:[]}],payments:{cash:1000,card:0,sinpe:0},customer:{}};
  assert.equal((await service.localRequest({path:'/api/sales',data:sale,csrf})).status,200);
  assert.equal(service.store.data.sales.length,1);assert.equal(service.store.data.sales[0].synced,false);
  await new Promise(resolve=>service.server.close(resolve));service.close();service=await start();
  const afterRestart=await service.localRequest({path:'/api/resume',data:{}});assert.equal(afterRestart.status,200);csrf=afterRestart.body.csrf;
  assert.equal((await service.localRequest({path:'/api/sales',data:sale,csrf})).status,200);assert.equal(service.store.data.sales.length,1);
  await service.sync();assert.equal(service.store.data.sales[0].synced,false);assert.equal(service.isPrincipal(),true);
  online=true;await service.sync();await service.sync();assert.equal(received.size,1);assert.equal(service.store.data.sales[0].synced,true);assert.equal(service.store.data.stocks[1],9000);
});

test('malformed native user refresh cannot partially replace cached cashier authority',async t=>{
  const directory=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-181-invalid-'));
  const cash={version:180,tenant_id:1,branch_id:2,device_id:3,register:{name:'Register'},shifts:[],acknowledged:[]};
  const service=await createLocalServer({dataDir:directory,port:0,host:'127.0.0.1',autoSync:false,fetch:async()=>Response.json({ok:true,cash:true,bound:true,cash_state:cash,users180:[{id:'untrusted',password_hash:'invalid'}]})});
  t.after(()=>{service.close();fs.rmSync(directory,{recursive:true,force:true});});
  service.store.commit({...service.store.data,connection:{url:'https://example.test',token:'fixture'},snapshot:{id:'original',tenant:{id:1},branch:{id:2},users:[]}});
  const original=JSON.stringify(service.store.data);
  await assert.rejects(service.authorize({nonce:'a'.repeat(64),user_id:42,confirm:true}),/incompatibles/);
  assert.equal(JSON.stringify(service.store.data),original);
});
