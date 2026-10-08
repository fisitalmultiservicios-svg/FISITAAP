'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {DesktopWorkspace,webBase}=require('../workspace'),{electronFixture}=require('./electron-fixture');
function environment(t,options={}){
 const fixture=electronFixture();let online=options.online!==false,central=true;
 const data={connection:options.configured===false?null:{url:'https://web.test',token:'protected-secret'},snapshot:options.configured===false?null:{tenant:{name:'Negocio'}},sales:[]};
 const service={url:'http://127.0.0.1:18766',store:{data},sync:async()=>{for(const sale of data.sales)sale.synced=true;}};
 const calls=[];
 const request=options.fetch||(async(url,init)=>{calls.push({url,init});if(url.endsWith('/api/setup')){if(!central)throw new Error('No LAN');return Response.json({ok:true,configured:true,webUrl:'https://web.test',pending:options.clientPending||0});}if(!online)throw new Error('No internet');return url.endsWith('/api/desktop/status')?Response.json({ok:true,system:'fisitaap',desktop_protocol:1}):Response.json({ok:false,error:'Usa POST.'},{status:405});});
 const workspace=new DesktopWorkspace({electron:fixture.electron,service,fetch:request,monitorMs:0,...options});
 t.after(()=>workspace.close());return {workspace,fixture,data,service,calls,online:value=>online=value,central:value=>central=value};
}
test('online startup displays the actual web application with separate session and printing preload',async t=>{
 const e=environment(t);await e.workspace.start();
 assert.equal(e.workspace.snapshot().mode,'web');assert.equal(e.workspace.webView.webContents.url,'https://web.test/owner-login');
 assert.equal(e.workspace.win.children[0],e.workspace.webView);assert.equal(e.workspace.localView.webContents.url,e.service.url);
 const prefs=e.workspace.webView.options.webPreferences;assert.match(prefs.preload,/web-preload\.js$/);assert.equal(prefs.nodeIntegration,false);assert.equal(prefs.sandbox,true);assert.equal(prefs.partition,'persist:fisitaap-web');
 assert(e.calls.every(call=>!call.init?.headers?.Authorization));
 assert(!JSON.stringify(e.workspace.snapshot()).includes('protected-secret'));
});
test('an outage preserves the web sale; returning internet never replaces an active local sale',async t=>{
 const e=environment(t);await e.workspace.start();
 e.workspace.webView.webContents.draft={items:['Web sale'],confirming:true};
 e.online(false);await e.workspace.refresh();assert.equal(e.workspace.snapshot().mode,'web');assert.match(e.workspace.snapshot().message,/Sin conexión/);
 assert.equal(e.workspace.webView.webContents.loads.length,1);assert.equal(e.workspace.webView.webContents.draft.confirming,true);
 e.workspace.webView.webContents.emit('did-fail-load',{},-106,'offline','https://web.test/admin/pos',true);assert.equal(e.workspace.snapshot().mode,'web');
 e.workspace.useLocal();e.workspace.localView.webContents.draft={items:['Local sale'],cash:2000};
 e.online(true);await e.workspace.refresh();assert.equal(e.workspace.snapshot().mode,'local');assert.equal(e.workspace.localView.webContents.draft.cash,2000);
 await e.workspace.useWeb();assert.equal(e.workspace.snapshot().mode,'web');assert.equal(e.workspace.localView.webContents.loads.length,1);assert.equal(e.workspace.localView.webContents.draft.cash,2000);
 e.workspace.useLocal();e.workspace.print();assert.equal(e.workspace.localView.webContents.printed,true);
});
test('offline startup uses the local register and makes the full web available after recovery',async t=>{
 const e=environment(t,{online:false});await e.workspace.start();assert.equal(e.workspace.snapshot().mode,'local');assert.equal(e.workspace.webView.webContents.loads.length,0);
 e.online(true);await e.workspace.refresh();assert.equal(e.workspace.snapshot().mode,'local');await e.workspace.useWeb();assert.equal(e.workspace.snapshot().mode,'web');
});
test('pairing on a new installation keeps the local screen until the user selects the complete system',async t=>{
 const e=environment(t,{configured:false});await e.workspace.start();assert.equal(e.workspace.snapshot().webUrl,null);
 e.data.connection={url:'https://web.test',token:'secret'};e.data.snapshot={};await e.workspace.refresh();assert.equal(e.workspace.snapshot().mode,'local');await e.workspace.useWeb();assert.equal(e.workspace.snapshot().mode,'web');
});
test('pending local sales must synchronize before returning to the complete system',async t=>{
 const e=environment(t);e.data.sales.push({id:'sale-1',synced:false});e.service.sync=async()=>{};
 await e.workspace.start();assert.equal(e.workspace.snapshot().mode,'local');assert.match(e.workspace.snapshot().message,/sincroniza/);assert.equal(e.workspace.webView.webContents.loads.length,0);
 e.service.sync=async()=>{e.data.sales[0].synced=true;};await e.workspace.useWeb();assert.equal(e.workspace.snapshot().mode,'web');assert.equal(e.data.sales.length,1);
});
test('another register discovers its central web URL and uses the shared local register',async t=>{
 const e=environment(t,{configured:false,localURL:'http://192.168.1.10:18766'});await e.workspace.start();assert.equal(e.workspace.snapshot().mode,'web');assert.equal(e.workspace.localView.webContents.url,'http://192.168.1.10:18766');
 assert(e.calls.some(call=>call.url==='http://192.168.1.10:18766/api/setup'));
 e.online(false);await e.workspace.refresh();e.workspace.useLocal();assert.equal(e.workspace.activeView,e.workspace.localView);
});
test('a client waits for central pending sales, while the web remains usable with a cached address if its central is down',async t=>{
 const pending=environment(t,{configured:false,localURL:'http://192.168.1.10:18766',clientPending:1});await pending.workspace.start();assert.equal(pending.workspace.snapshot().mode,'local');
 const cached=environment(t,{configured:false,localURL:'http://192.168.1.10:18766',cachedWebURL:'https://web.test'});cached.central(false);await cached.workspace.start();assert.equal(cached.workspace.snapshot().mode,'web');assert.equal(cached.workspace.snapshot().centralReady,false);
});
test('web navigation and popups keep native access isolated and restrict permissions to the configured web origin',async t=>{
 const e=environment(t);await e.workspace.start();const contents=e.workspace.webView.webContents;
 const same=contents.openHandler({url:'https://web.test/admin/reportes'});assert.equal(same.action,'allow');assert.match(same.overrideBrowserWindowOptions.webPreferences.preload,/web-preload\.js$/);
 assert.equal(contents.openHandler({url:'about:blank'}).action,'allow');assert.equal(contents.openHandler({url:'blob:https://web.test/test'}).action,'allow');
 assert.equal(contents.openHandler({url:'https://outside.test/help'}).action,'deny');assert.deepEqual(e.fixture.external,['https://outside.test/help']);
 contents.openHandler({url:'file:///private'});assert.equal(e.fixture.external.length,1);
 let prevented=false;contents.emit('will-navigate',{preventDefault:()=>{prevented=true;}},'file:///private');assert.equal(prevented,true);
 let allowed;contents.permissionRequest(contents,'media',value=>{allowed=value;},{requestingUrl:'https://web.test/admin'});assert.equal(allowed,true);
 contents.permissionRequest(contents,'media',value=>{allowed=value;},{requestingUrl:'https://outside.test'});assert.equal(allowed,false);
 for(const permission of ['local-network','local-network-access','loopback-network','web-printing']){assert.equal(contents.permissionCheck(contents,permission,'https://web.test'),true);assert.equal(contents.permissionCheck(contents,permission,'https://outside.test'),false);}
 assert.throws(()=>webBase('http://web.test'));assert.throws(()=>webBase('https://user:pass@web.test'));assert.throws(()=>webBase('https://web.test/?token=secret'));
});
test('choosing the local register cancels a delayed switch to the web',async t=>{
 let release;const e=environment(t,{fetch:async()=>new Promise(resolve=>{release=()=>resolve(Response.json({ok:true,system:'fisitaap',desktop_protocol:1}));})});
 const switching=e.workspace.useWeb();await new Promise(resolve=>setImmediate(resolve));e.workspace.useLocal();release();await switching;assert.equal(e.workspace.snapshot().mode,'local');assert.equal(e.workspace.webView.webContents.loads.length,0);
});
test('failure of the first web page falls back locally without destroying either view',async t=>{
 const e=environment(t);e.workspace.webView.webContents.failLoad=true;await e.workspace.start();assert.equal(e.workspace.snapshot().mode,'local');assert.equal(e.workspace.localView.webContents.closed,undefined);assert.match(e.workspace.snapshot().message,/seguir usando Caja local/);
});
test('a synchronization error keeps pending operations and the local view available',async t=>{
 const e=environment(t);e.data.sales.push({id:'sale-1',synced:false});e.service.sync=async()=>{throw new Error('Disk unavailable');};
 await e.workspace.start();assert.equal(e.workspace.snapshot().mode,'local');assert.equal(e.data.sales[0].synced,false);assert.match(e.workspace.snapshot().message,/No se pudieron sincronizar/);
});
