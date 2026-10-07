'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {DesktopWorkspace}=require('../desktop/workspace');
const {electronFixture}=require('../desktop/test/electron-fixture');

function setup(t,request){
  const fixture=electronFixture(),service={url:'http://127.0.0.1:18766',store:{data:{connection:{url:'https://web.test',token:'must-stay-private'},snapshot:{},sales:[]}},sync:async()=>{}};
  const calls=[];
  const workspace=new DesktopWorkspace({electron:fixture.electron,service,fetch:async(url,init)=>{calls.push({url,init});return request(url,init);},monitorMs:0});
  t.after(()=>workspace.close());return {workspace,calls};
}
test('new JSON 200 probe opens the full system without tokens, cookies or POST',async t=>{
  const {workspace,calls}=setup(t,async()=>Response.json({ok:true,system:'fisitaap',desktop_protocol:1}));
  await workspace.start();assert.equal(workspace.snapshot().mode,'web');assert.equal(calls.length,1);
  assert(calls[0].url.endsWith('/api/desktop/status'));assert.equal(calls[0].init.method,'GET');assert.equal(calls[0].init.cache,'no-store');
  assert(!JSON.stringify(calls).includes('must-stay-private'));assert.equal(workspace.webView.webContents.url,'https://web.test/owner-login');
});
test('older web remains compatible through its POST-only pair endpoint',async t=>{
  const {workspace,calls}=setup(t,async url=>url.endsWith('/status')?new Response('<html>404</html>',{status:404}):Response.json({ok:false,error:'Usa POST.'},{status:405}));
  await workspace.start();assert.equal(workspace.snapshot().mode,'web');assert.equal(calls.length,2);assert(calls[1].url.endsWith('/pair'));
});
test('a hosting HTML error page never counts as confirmed connectivity',async t=>{
  const {workspace}=setup(t,async()=>new Response('<html>hosting page with private diagnostic</html>',{status:405}));
  await workspace.start();assert.equal(workspace.snapshot().available,false);assert.equal(workspace.snapshot().mode,'local');assert(!workspace.snapshot().message.includes('private diagnostic'));
});
test('disabled web integration remains unavailable and does not bypass status with legacy probe',async t=>{
  const {workspace,calls}=setup(t,async()=>Response.json({ok:false,error:'Inactive'},{status:503}));
  await workspace.start();assert.equal(workspace.snapshot().mode,'local');assert.equal(calls.length,1);assert.match(workspace.snapshot().message,/no está activa/);
});
test('unrelated JSON or a login page cannot be mistaken for the configured system',async t=>{
  for(const response of [()=>Response.json({ok:true}),()=>new Response('<html>login</html>',{status:200}),()=>Response.json({ok:true,system:'other',desktop_protocol:1})]){
    const {workspace}=setup(t,async()=>response());await workspace.start();assert.equal(workspace.snapshot().available,false);assert.equal(workspace.snapshot().mode,'local');
  }
});
test('a healthy reply after the previous seven-second deadline is still accepted',async t=>{
  const {workspace}=setup(t,async(_url,init)=>{
    await new Promise((resolve,reject)=>{const timer=setTimeout(resolve,8000);init.signal.addEventListener('abort',()=>{clearTimeout(timer);reject(init.signal.reason);},{once:true});});
    return Response.json({ok:true,system:'fisitaap',desktop_protocol:1});
  });
  await workspace.start();assert.equal(workspace.snapshot().mode,'web');
});
test('timeouts explain the delayed server and preserve the local register',async t=>{
  const {workspace}=setup(t,async()=>{throw new DOMException('timeout','TimeoutError');});
  await workspace.start();assert.equal(workspace.snapshot().mode,'local');assert.match(workspace.snapshot().message,/tardó demasiado/);assert.equal(workspace.service.store.data.connection.token,'must-stay-private');
});
