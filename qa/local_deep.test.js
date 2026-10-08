'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),crypto=require('node:crypto'),cp=require('node:child_process');
const bcrypt=require('../desktop/node_modules/bcryptjs');
const {createLocalServer,quote}=require('../desktop/server');
const password=crypto.randomBytes(16).toString('hex');
function snapshot(stock=1000000){return{offline_policy:{version:179,allowed:true,generation:1},id:'a'.repeat(32),generated_at:new Date().toISOString(),tenant:{id:1,name:'QA',tax_included:false},branch:{id:1,name:'QA'},products:[{id:1,name:'Product',price:1000,normal_price:1000,rate:1300,min:1000,step:1000,stock,track:true,allow_negative:false,bogo:false,groups:[],components:[]}],users:[{id:1,name:'A',password_hash:bcrypt.hashSync(password,4)},{id:2,name:'B',password_hash:bcrypt.hashSync(password,4)}],customers:[],categories:[]};}
async function environment(t,handler){
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-qa-'));
 const received=new Map(),linked=new Set(),closed=new Set(),calls=[];
 const remote=async(url,options)=>{
  const body=JSON.parse(options.body);calls.push({url,body});
  let value;
  if(url.endsWith('/pair'))value={ok:true,token:'b'.repeat(64),snapshot:snapshot()};
  else if(handler)value=await handler(url,body,{received,linked,closed});
  else if(url.endsWith('/sync')){
   assert(body.sales.length<=100 && body.shifts.length<=100);
   for(const shift of body.shifts)linked.add(shift.id);
   const errors=[],acknowledged=[];
   for(const sale of body.sales){if(!linked.has(sale.shift_id)){errors.push({id:sale.id,error:'Missing shift'});continue;}received.set(sale.id,sale);acknowledged.push({id:sale.id,sale_id:received.size});}
   const completed=body.shifts.filter(s=>s.closed_at && !errors.length).map(s=>s.id);completed.forEach(id=>closed.add(id));
   value={ok:true,acknowledged,errors,closed_shifts:completed};
  }else value={ok:true,snapshot:snapshot(1000000-received.size*1000)};
  return{ok:true,json:async()=>value};
 };
 const service=await createLocalServer({dataDir:dir,port:0,host:'127.0.0.1',autoSync:false,fetch:remote});
 t.after(async()=>{await new Promise(resolve=>service.server.close(resolve));fs.rmSync(dir,{recursive:true,force:true});});
 const request=async(route,data,actor)=>{
  const response=await fetch(service.url+route,{method:data===undefined?'GET':'POST',headers:{...(data===undefined?{}:{Origin:service.url,'Content-Type':'application/json','X-CSRF-Token':actor?.csrf||''}),Cookie:actor?.cookie||''},body:data===undefined?undefined:JSON.stringify(data)});
  return{status:response.status,data:await response.json(),cookie:response.headers.get('set-cookie')?.split(';')[0]};
 };
 await request('/api/pair',{url:'https://qa.invalid',device_id:1,code:'test'});
 const login=async(id,name)=>{const response=await request('/api/login',{user_id:id,register:name,password});assert.equal(response.status,200);return{cookie:response.cookie,csrf:response.data.csrf};};
 const sale=(extra={})=>({id:crypto.randomUUID(),items:[{id:1,quantity:1000,options:[]}],payments:{cash:2000,card:0,sinpe:0},customer:{},...extra});
 return{dir,service,request,login,sale,received,linked,closed,calls};
}
test('QA 50 simultaneous sales against 25 units cannot oversell',async t=>{
 const e=await environment(t),a=await e.login(1,'A'),b=await e.login(2,'B');
 await e.request('/api/shift/open',{opening_cash:0},a);await e.request('/api/shift/open',{opening_cash:0},b);
 const next=structuredClone(e.service.store.data);next.stocks[1]=25000;e.service.store.commit(next);
 const responses=await Promise.all(Array.from({length:50},(_,i)=>e.request('/api/sales',e.sale(),i%2?a:b)));
 assert.equal(responses.filter(x=>x.status===200).length,25);assert.equal(responses.filter(x=>x.status===422).length,25);
 assert.equal(e.service.store.data.stocks[1],0);assert.equal(e.service.store.data.sales.length,25);
});
test('QA 125 offline sales split into two batches and close only after all sales',async t=>{
 const e=await environment(t),a=await e.login(1,'A');await e.request('/api/shift/open',{opening_cash:500},a);
 for(let i=0;i<125;i++)assert.equal((await e.request('/api/sales',e.sale(),a)).status,200);
 await e.request('/api/shift/close',{closing_cash:141750},a);
 await e.service.sync();assert.equal(e.received.size,100);assert.equal(e.closed.size,0);assert.equal(e.service.store.data.sales.filter(x=>!x.synced).length,25);
 await e.service.sync();assert.equal(e.received.size,125);assert.equal(e.closed.size,1);assert.equal(e.service.store.data.sales.filter(x=>!x.synced).length,0);
 await e.service.sync();assert.equal(e.received.size,125);
});
test('QA pending sales get shift metadata even with 100 older open shifts',async t=>{
 const e=await environment(t),a=await e.login(1,'A');await e.request('/api/shift/open',{opening_cash:0},a);await e.request('/api/sales',e.sale(),a);
 const next=structuredClone(e.service.store.data),last=next.shifts[0];
 next.shifts=Array.from({length:100},(_,i)=>({...last,id:crypto.randomUUID(),user_id:100+i,register:'Older '+i})).concat(last);e.service.store.commit(next);
 await e.service.sync();assert.equal(e.received.size,1,'The pending sale is blocked behind unrelated open shifts');
});
test('QA sales created while a snapshot downloads keep their reserved stock',async t=>{
 let releaseSnapshot,enterSnapshot;
 const entered=new Promise(resolve=>enterSnapshot=resolve),release=new Promise(resolve=>releaseSnapshot=resolve);
 const e=await environment(t,async(url,body)=>{
  if(url.endsWith('/sync'))return{ok:true,acknowledged:[],errors:[],closed_shifts:[]};
  enterSnapshot();await release;return{ok:true,snapshot:snapshot()};
 });
 const a=await e.login(1,'A');await e.request('/api/shift/open',{opening_cash:0},a);
 const pending=e.service.sync();await entered;
 assert.equal((await e.request('/api/sales',e.sale(),a)).status,200);releaseSnapshot();await pending;
 assert.equal(e.service.store.data.stocks[1],999000);assert.equal(e.service.store.data.sales[0].synced,false);
});
test('QA an assigned principal cannot start re-pairing while offline sales are possible',async t=>{
 const e=await environment(t);
 let releasePair;const release=new Promise(resolve=>releasePair=resolve);
 // The pending request parses a deliberately slow response while a cashier can still sell.
 const remoteFetch=global.fetch;
 const secondSnapshot=snapshot();secondSnapshot.id='c'.repeat(32);secondSnapshot.tenant.id=9;
 // Hold response.json(), preserving the real local request path and atomic storage operations.
 const create=require('../desktop/server').createLocalServer;
 const directory=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-pair-race-'));
 const initial=structuredClone(e.service.store.data);fs.writeFileSync(path.join(directory,'operaciones.json'),JSON.stringify(initial));
 let enteredResolve;const entered=new Promise(resolve=>enteredResolve=resolve);
 const service=await create({dataDir:directory,port:0,host:'127.0.0.1',autoSync:false,fetch:async()=>({ok:true,json:async()=>{enteredResolve();await release;return{ok:true,token:'c'.repeat(64),snapshot:secondSnapshot};}})});
 t.after(async()=>{await new Promise(resolve=>service.server.close(resolve));fs.rmSync(directory,{recursive:true,force:true});});
 const result=await remoteFetch(service.url+'/api/pair',{method:'POST',headers:{Origin:service.url,'Content-Type':'application/json'},body:JSON.stringify({url:'https://other.invalid',device_id:2,code:'test'})});
 assert.equal(result.status,422);assert.match((await result.json()).error,/Libera primero/);
 const localPost=async(route,body,actor)=>remoteFetch(service.url+route,{method:'POST',headers:{Origin:service.url,'Content-Type':'application/json','X-CSRF-Token':actor?.csrf||'',Cookie:actor?.cookie||''},body:JSON.stringify(body)});
 const login=await localPost('/api/login',{user_id:1,register:'A',password});const actor={cookie:login.headers.get('set-cookie').split(';')[0],csrf:(await login.json()).csrf};
 assert.equal((await localPost('/api/shift/open',{opening_cash:0},actor)).status,200);
 assert.equal((await localPost('/api/sales',e.sale(),actor)).status,200);releasePair();
 assert.equal(service.store.data.snapshot.tenant.id,1);assert.equal(service.store.data.sales.length,1);
});
test('QA 1500 generated quotes agree between JavaScript and PHP to the cent',()=>{
 let seed=12345;const random=n=>{seed=(Math.imul(seed,1664525)+1013904223)>>>0;return seed%n;};
 const cases=[];
 for(let i=0;i<1500;i++){
  const snap=snapshot();snap.tenant.tax_included=!!random(2);const product=snap.products[0];
  product.price=1+random(500000);product.normal_price=product.price+random(10000);product.rate=[0,100,200,400,800,1300][random(6)];product.bogo=!!random(2);product.min=250;product.step=250;
  product.groups=[{name:'Extra',min:1,max:1,options:[{id:5,name:'Extra',price:random(30000)}]}];
  product.promo_expires='2026-10-08T00:00:00Z';const at=Date.parse(i%2?'2026-10-07T00:00:00Z':'2026-10-09T00:00:00Z');
  if(i%3===0)product.components=[{id:9,quantity:125+random(5000),track:true}];
  const rows=Array.from({length:1+random(5)},()=>({id:1,quantity:250*(1+random(400)),options:[5]}));
  const baseline=quote(snap,rows,at);const discount=random(baseline.net+1);
  cases.push({snapshot:snap,rows,at:at/1000,discount});
 }
 const code="require '/var/www/html/app/bootstrap.php';require '/var/www/html/app/pos_v141.php';require '/var/www/html/app/restructure_r1.php';require '/var/www/html/app/restructure_r2.php';$all=json_decode(stream_get_contents(STDIN),true);$out=[];foreach($all as $c){$q=r2_offline_quote($c['snapshot'],$c['rows'],$c['at'],$c['discount']);unset($q['lines']);$out[]=$q;}echo json_encode($out);";
 const php=JSON.parse(cp.execFileSync('docker',['exec','-i','fisitaap-r2-web','php','-r',code],{input:JSON.stringify(cases),maxBuffer:8000000}));
 cases.forEach((c,i)=>{const js=quote(c.snapshot,c.rows,c.at*1000,c.discount);delete js.lines;assert.deepEqual(js,php[i],`Generated quote ${i}`);});
});
