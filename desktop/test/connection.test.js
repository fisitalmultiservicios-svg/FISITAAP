'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),http=require('node:http'),crypto=require('node:crypto'),bcrypt=require('bcryptjs');
const {createLocalServer}=require('../server');
const password=crypto.randomBytes(16).toString('hex');
const snapshot=()=>({id:'a'.repeat(32),tenant:{id:1,name:'Prueba',tax_included:false},branch:{id:1,name:'Principal'},products:[{id:1,name:'Artículo',sku:'TEST',price:1000,rate:0,min:1000,step:1000,stock:10000,track:true,allow_negative:false,groups:[],components:[]}],users:[{id:1,name:'Cajero',password_hash:bcrypt.hashSync(password,4)}],categories:[],customers:[]});
async function local(t,options={}){
 const dataDir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-connection-'));
 const service=await createLocalServer({dataDir,port:0,host:'127.0.0.1',autoSync:false,allowTestHttp:true,...options});
 t.after(async()=>{await new Promise(r=>service.server.close(r));fs.rmSync(dataDir,{recursive:true,force:true});});
 const request=async(route,data,actor={})=>{
  const response=await fetch(service.url+route,{method:'POST',headers:{Origin:service.url,'Content-Type':'application/json',Cookie:actor.cookie||'','X-CSRF-Token':actor.csrf||''},body:JSON.stringify(data)});
  return {status:response.status,data:await response.json(),cookie:response.headers.get('set-cookie')?.split(';')[0]};
 };
 return {service,request,pair:url=>request('/api/pair',{url,device_id:2,code:'fixture-code'})};
}
test('a shop URL returning real HTML gives directions and can be corrected without automatic pairing retries',async t=>{
 const calls=[];
 const web=http.createServer((req,res)=>{
  calls.push(req.url);
  if(req.url!=='/api/desktop/pair'&&req.url!=='/instalacion/api/desktop/pair'){res.writeHead(404,{'Content-Type':'text/html'});return res.end('<!doctype html><title>Tienda</title>');}
  res.writeHead(200,{'Content-Type':'application/json'});res.end(JSON.stringify({ok:true,token:'t'.repeat(64),snapshot:snapshot()}));
 });
 await new Promise(r=>web.listen(0,'127.0.0.1',r));t.after(()=>web.close());
 const base='http://127.0.0.1:'+web.address().port,e=await local(t);
 const bad=await e.pair(base+'/laventanita');assert.equal(bad.status,422);
 assert.match(bad.data.error,/Dirección web/);assert(bad.data.error.includes(base));assert(!bad.data.error.includes('Unexpected token'));
 assert.equal(e.service.store.data.connection,null);assert.equal(e.service.store.data.snapshot,null);assert.deepEqual(calls,['/laventanita/api/desktop/pair']);
 const good=await e.pair(base+'/');assert.equal(good.status,200);assert.equal(e.service.store.data.connection.url,base);assert.equal(e.service.store.data.snapshot.products.length,1);
 const saved=fs.readFileSync(e.service.store.file);
 assert.equal((await e.pair(base+'/laventanita')).status,422);assert(fs.readFileSync(e.service.store.file).equals(saved));
 const installed=await e.pair(base+'/instalacion/');assert.equal(installed.status,200);assert.equal(e.service.store.data.connection.url,base+'/instalacion');
 assert.equal(calls.filter(p=>p==='/api/desktop/pair').length,1);
});
test('HTML, empty and malformed web data never replace a connection or expose parser errors',async t=>{
 let reply;let encryptions=0;
 const e=await local(t,{fetch:async()=>reply(),tokenCodec:{encode:value=>{encryptions++;return value;},decode:value=>value}});
 const variants=[()=>new Response('<!doctype html><h1>Error</h1>',{status:500}),()=>new Response(''),()=>new Response('not JSON'),()=>Response.json(null),()=>Response.json([]),()=>Response.json({ok:true}),()=>Response.json({ok:true,token:'x',snapshot:{...snapshot(),products:[{id:1}]}})];
 for(const variant of variants){reply=variant;const result=await e.pair('https://web.test');assert.equal(result.status,422);assert.match(result.data.error,/web/);assert(!/Unexpected|TypeError|undefined|JSON/.test(result.data.error));assert.equal(e.service.store.data.connection,null);}
 assert.equal(encryptions,0);
 reply=()=>Response.json({ok:false,error:'El código venció. Crea un código nuevo.'},{status:401});assert.equal((await e.pair('https://web.test')).data.error,'El código venció. Crea un código nuevo.');
});
test('an HTML synchronization response preserves offline sales and a later valid response receives them once',async t=>{
 let online=false;const received=new Set();
 const e=await local(t,{fetch:async(url,options)=>{
  if(url.endsWith('/pair'))return Response.json({ok:true,token:'t'.repeat(64),snapshot:snapshot()});
  if(!online)return new Response('<!doctype html><title>Web unavailable</title>',{status:503});
  if(url.endsWith('/sync')){const data=JSON.parse(options.body);for(const sale of data.sales)received.add(sale.id);return Response.json({ok:true,acknowledged:data.sales.map(s=>({id:s.id,sale_id:1})),errors:[],closed_shifts:[]});}
  const snap=snapshot();snap.products[0].stock=9000;return Response.json({ok:true,snapshot:snap});
 }});
 assert.equal((await e.pair('https://web.test')).status,200);
 const login=await e.request('/api/login',{user_id:1,password,register:'Caja 1'}),actor={cookie:login.cookie,csrf:login.data.csrf};
 assert.equal((await e.request('/api/shift/open',{opening_cash:0},actor)).status,200);
 const id=crypto.randomUUID();assert.equal((await e.request('/api/sales',{id,items:[{id:1,quantity:1000,options:[]}],payments:{cash:1000,card:0,sinpe:0},customer:{}},actor)).status,200);
 const sale=structuredClone(e.service.store.data.sales[0]);
 await e.service.sync();assert.deepEqual(e.service.store.data.sales[0],sale);assert.equal(e.service.store.data.stocks[1],9000);assert.match(e.service.store.data.syncMessage,/Las ventas siguen guardadas/);assert(!e.service.store.data.syncMessage.includes('Unexpected token'));
 assert.equal((await e.pair('https://other.test')).status,422);
 online=true;await e.service.sync();await e.service.sync();assert.equal(received.size,1);assert.equal(e.service.store.data.sales.length,1);assert.equal(e.service.store.data.sales[0].synced,true);assert.equal(e.service.store.data.stocks[1],9000);
});
