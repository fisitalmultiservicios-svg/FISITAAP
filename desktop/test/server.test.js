'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),crypto=require('node:crypto');
const bcrypt=require('bcryptjs');
const {createLocalServer,quote,LocalStore}=require('../server');
const pass=crypto.randomBytes(16).toString('hex');
const fixture=(stock=10000)=>({id:'a'.repeat(32),generated_at:new Date().toISOString(),tenant:{id:1,name:'Prueba',tax_included:false},branch:{id:1,name:'Principal'},products:[{id:1,name:'Artículo',sku:'SKU1',price:1000,rate:1300,cost:5,min:1000,step:1000,stock,track:true,allow_negative:false,category_id:1,bogo:false,groups:[],components:[]}],users:[{id:1,name:'Caja A',password_hash:bcrypt.hashSync(pass,4)},{id:2,name:'Caja B',password_hash:bcrypt.hashSync(pass,4)}],customers:[],categories:[{id:1,name:'Productos'}]});
async function environment(t,stock=10000){
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-local-test-'));let online=false;const remoteSales=new Map(),calls=[];
 const fakeFetch=async(url,options)=>{if(!online&& !url.endsWith('/pair'))throw new Error('offline');const data=JSON.parse(options.body||'{}');calls.push({url,data});if(url.endsWith('/pair'))return{ok:true,json:async()=>({ok:true,token:'t'.repeat(64),snapshot:fixture(stock)})};if(url.endsWith('/sync')){const ack=[];for(const sale of data.sales){const old=remoteSales.get(sale.id);if(old)assert.deepEqual(sale,old);else remoteSales.set(sale.id,sale);ack.push({id:sale.id,sale_id:remoteSales.size});}return{ok:true,json:async()=>({ok:true,acknowledged:ack,errors:[],closed_shifts:data.shifts.filter(s=>s.closed_at).map(s=>s.id)})};}const snap=fixture(stock-remoteSales.size*1000);return{ok:true,json:async()=>({ok:true,snapshot:snap})};};
 let service=await createLocalServer({dataDir:dir,port:0,host:'127.0.0.1',autoSync:false,allowTestHttp:true,fetch:fakeFetch});
 const request=async(route,data,cookie='',csrf='',origin)=>{const base=service.url;const r=await fetch(base+route,{method:data===undefined?'GET':'POST',headers:{...(data===undefined?{}:{'Content-Type':'application/json','Origin':origin||base,'X-CSRF-Token':csrf}),Cookie:cookie},body:data===undefined?undefined:JSON.stringify(data)});return{status:r.status,data:await r.json(),cookie:r.headers.get('set-cookie')?.split(';')[0]};};
 await request('/api/pair',{url:'http://web.test',device_id:1,code:'test'});
 const login=async(id,register)=>{const r=await request('/api/login',{user_id:id,register,password:pass});assert.equal(r.status,200);return{cookie:r.cookie,csrf:r.data.csrf,register,id};};
 const post=(actor,route,data)=>request(route,data,actor.cookie,actor.csrf);
 const sale=(actor,id=crypto.randomUUID())=>({id,items:[{id:1,quantity:1000,options:[],note:''}],payments:{cash:2000,card:0,sinpe:0},customer:{}});
 const close=()=>new Promise(resolve=>service.server.close(resolve));
 t.after(async()=>{await close();fs.rmSync(dir,{recursive:true,force:true});});
 return{request,post,login,sale,store:()=>service.store,service:()=>service,online:()=>online=true,remoteSales,calls,restart:async()=>{await close();service=await createLocalServer({dataDir:dir,port:0,host:'127.0.0.1',autoSync:false,allowTestHttp:true,fetch:fakeFetch});}};
}
test('integer totals: 2x1, extras, included tax and option limits',()=>{
 const s=fixture();s.products[0].bogo=true;s.products[0].groups=[{id:1,min:1,max:1,options:[{id:5,name:'Extra',price:300}]}];
 assert.throws(()=>quote(s,[{id:1,quantity:3000,options:[]}]),/opciones/);
 const out=quote(s,[{id:1,quantity:3000,options:[5]}]);assert.equal(out.net,2900);assert.equal(out.tax,377);assert.equal(out.total,3277);assert.equal(out.stock[1],3000);
 s.tenant.tax_included=true;const included=quote(s,[{id:1,quantity:3000,options:[5]}]);assert.equal(included.total,2900);assert.equal(included.net+included.tax,2900);
 assert.throws(()=>quote(s,[{id:1,quantity:1500,options:[5]}]),/incremento/);
});
test('authentication, CSRF, host/origin and no password hashes in cashier state',async t=>{
 const e=await environment(t);assert.equal((await e.request('/api/state')).status,401);assert.equal((await e.request('/api/sales',{})).status,401);
 const a=await e.login(1,'Caja 1');assert.equal((await e.request('/api/shift/open',{opening_cash:0},a.cookie,'bad')).status,403);assert.equal((await e.request('/api/shift/open',{opening_cash:0},a.cookie,a.csrf,'http://attacker.test')).status,403);
 const result=await e.request('/api/state',undefined,a.cookie);assert.equal(result.status,200);assert.equal(JSON.stringify(result.data).includes('password_hash'),false);assert.equal(JSON.stringify(result.data).includes('token'),false);
 const setup=await e.request('/api/setup');assert.equal(setup.data.webUrl,'http://web.test');assert.equal(setup.data.pending,0);assert.equal(JSON.stringify(setup.data).includes('password_hash'),false);assert.equal(JSON.stringify(setup.data).includes('token'),false);
});
test('two registers offline: inventory serialized, no overselling or double payment on retry',async t=>{
 const e=await environment(t,1000),a=await e.login(1,'Caja 1'),b=await e.login(2,'Caja 2');
 await e.post(a,'/api/shift/open',{opening_cash:500});await e.post(b,'/api/shift/open',{opening_cash:0});
 const sa=e.sale(a),sb=e.sale(b);const results=await Promise.all([e.post(a,'/api/sales',sa),e.post(b,'/api/sales',sb)]);assert.deepEqual(results.map(r=>r.status).sort(),[200,422]);assert.equal(e.store().data.sales.length,1);assert.equal(e.store().data.stocks[1],0);
 const win=results[0].status===200?a:b,request=results[0].status===200?sa:sb;const retry=await e.post(win,'/api/sales',request);assert.equal(retry.status,200);assert.equal(e.store().data.sales.length,1);assert.equal(retry.data.sale.change,870);
 const changed={...request,payments:{cash:3000,card:0,sinpe:0}};assert.equal((await e.post(win,'/api/sales',changed)).status,422);assert.equal(e.store().data.sales.length,1);
});
test('durable restart, failed internet, outbox retry and shift closing received once',async t=>{
 const e=await environment(t),a=await e.login(1,'Caja 1');await e.post(a,'/api/shift/open',{opening_cash:500});const request=e.sale(a);await e.post(a,'/api/sales',request);await e.service().sync();assert.equal(e.store().data.sales[0].synced,false);
 await e.restart();assert.equal(e.store().data.sales.length,1);assert.equal(e.store().data.stocks[1],9000);const again=await e.login(1,'Caja 1');assert.equal((await e.post(again,'/api/sales',request)).status,200);const closed=await e.post(again,'/api/shift/close',{closing_cash:1630});assert.equal(closed.data.shift.expected_cash,1630);assert.equal(closed.data.shift.difference,0);
 e.online();await e.service().sync();assert.equal(e.remoteSales.size,1);assert.equal(e.store().data.sales[0].synced,true);assert.equal(e.store().data.shifts[0].syncedClosed,true);await e.service().sync();assert.equal(e.remoteSales.size,1);
});
test('disk write failure leaves stock and sales unchanged',async t=>{
 const e=await environment(t),a=await e.login(1,'Caja 1');await e.post(a,'/api/shift/open',{opening_cash:0});const original=e.store().commit;e.store().commit=()=>{throw new Error('Disk unavailable');};const r=await e.post(a,'/api/sales',e.sale(a));assert.equal(r.status,422);assert.equal(e.store().data.sales.length,0);assert.equal(e.store().data.stocks[1],10000);e.store().commit=original;
});
test('corrupt operation storage fails closed and preserves the original file',()=>{
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-corrupt-test-')),file=path.join(dir,'operaciones.json');fs.writeFileSync(file,'corrupt');assert.throws(()=>new LocalStore(dir));assert.equal(fs.readFileSync(file,'utf8'),'corrupt');fs.rmSync(dir,{recursive:true,force:true});
});
test('offline promotions expire using the sale time, including historical synchronization',()=>{
 const s=fixture(),p=s.products[0];p.normal_price=1000;p.price=500;p.bogo=true;p.promo_expires='2026-01-02T00:00:00Z';const rows=[{id:1,quantity:2000,options:[]}];
 assert.equal(quote(s,rows,Date.parse('2026-01-01T00:00:00Z')).total,565);
 assert.equal(quote(s,rows,Date.parse('2026-01-03T00:00:00Z')).total,2260);
});
