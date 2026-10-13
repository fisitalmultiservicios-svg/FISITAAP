'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {LocalStore}=require('../local-store');
function fixture(t){const dir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-storage-'));t.after(()=>fs.rmSync(dir,{recursive:true,force:true}));return dir;}
function data(){return {version:1,connection:{url:'https://web.test',token:'protected-connection'},snapshot:{id:'1',tenant:{id:1,name:'Store'},branch:{id:1},products:Array.from({length:1000},(_,i)=>({id:i+1,name:'Product '+i,stock:1000,components:[{id:2000+i,stock:1000}],price:10000})),users:[],offline_policy:{version:180,allowed:true},cash_state:{shifts:[]}},stocks:{},sales:[{id:'pending-sale',synced:false}],shifts:[{id:'open-shift'}]};}
test('a thousand-product catalog is written once while cash, stock and permission overlays change',t=>{
 const dir=fixture(t),store=new LocalStore(dir),first=data();store.commit(first);const originalSize=fs.statSync(store.file).size;let next=structuredClone(first);next.snapshot.id='2';next.snapshot.generated_at='later';next.snapshot.products[0].stock=900;next.snapshot.products[0].components[0].stock=800;next.snapshot.offline_policy.allowed=false;next.snapshot.cash_state.shifts=[{id:'shift',server_cash_net:100}];next.sales.push({id:'second-sale',synced:false});store.commit(next);
 assert.equal(fs.readdirSync(path.join(dir,'catalogos')).length,1);assert.deepEqual(new LocalStore(dir).data,next);assert.ok(fs.statSync(store.file).size<JSON.stringify(next).length*.6);assert.ok(originalSize>0);assert.equal(first.snapshot.products[0].stock,1000);
});
test('legacy operations migrate without changing pending sales and the backup is complete without catalog files',t=>{
 const dir=fixture(t),old=data();fs.writeFileSync(path.join(dir,'operaciones.json'),JSON.stringify(old));const store=new LocalStore(dir);assert.deepEqual(store.data,old);store.commit(old);const backup=store.backup();assert.deepEqual(JSON.parse(fs.readFileSync(backup,'utf8')),old);assert.deepEqual(JSON.parse(fs.readFileSync(path.join(dir,'backups','antes-almacenamiento-180.json'),'utf8')),old);
 const restored=fixture(t);fs.copyFileSync(backup,path.join(restored,'operaciones.json'));assert.deepEqual(new LocalStore(restored).data,old);
});
test('a changed catalog creates a new immutable file, and damaged data stops opening instead of emptying the box',t=>{
 const dir=fixture(t),store=new LocalStore(dir),next=data();store.commit(next);next.snapshot.products[0].price=999;store.commit(next);assert.equal(fs.readdirSync(path.join(dir,'catalogos')).length,2);const current=JSON.parse(fs.readFileSync(store.file,'utf8'));fs.writeFileSync(path.join(dir,'catalogos',current.storage180.catalog+'.json'),'{}');assert.throws(()=>new LocalStore(dir),/comprobación/);assert.equal(JSON.parse(fs.readFileSync(store.file,'utf8')).sales[0].id,'pending-sale');
});
test('a stock-only reply retains products, prices and users and rejects another business or incomplete stock',()=>{
 const {snapshotResponse}=require('../server'),snapshot=data().snapshot,stocks={};for(const p of snapshot.products){stocks[p.id]=900;for(const c of p.components)stocks[c.id]=800;}
 const delta={id:snapshot.id,tenant_id:1,branch_id:1,generated_at:'later',stocks,cash_state:{shifts:[]},offline_policy:{version:180,allowed:false}},response={snapshot_delta180:delta},fresh=snapshotResponse(snapshot,response);
 assert.equal(fresh.products[0].stock,900);assert.equal(fresh.products[0].components[0].stock,800);assert.equal(fresh.products[0].price,10000);assert.equal(snapshot.products[0].stock,1000);assert.equal(fresh.offline_policy.allowed,false);assert.throws(()=>snapshotResponse(snapshot,{snapshot_delta180:{...delta,tenant_id:2}}),/no corresponde/);assert.throws(()=>snapshotResponse(snapshot,{snapshot_delta180:{...delta,stocks:{}}}),/existencias/);assert.deepEqual(snapshotResponse(snapshot,{snapshot:data().snapshot}),snapshot);assert.throws(()=>snapshotResponse(snapshot,{snapshot:{...data().snapshot,tenant:{id:2}}}),/no corresponde/);
});
