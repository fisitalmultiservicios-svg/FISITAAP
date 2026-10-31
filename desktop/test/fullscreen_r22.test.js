'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {DesktopWorkspace}=require('../workspace'),{electronFixture}=require('./electron-fixture');
function setup(t,displayState={}){
 const fixture=electronFixture(),saved=[],service={url:'http://127.0.0.1:18766',store:{data:{sales:[]}}};
 const w=new DesktopWorkspace({electron:fixture.electron,service,cachedWebURL:'https://web.test',monitorMs:0,displayState,onDisplayState:s=>saved.push({...s})});t.after(()=>w.close());
 const event=(contents,url)=>({sender:contents,senderFrame:{url,parent:null}});
 return {w,saved,fixture,event,web:event(w.webView.webContents,'https://web.test/admin/ventas')};
}
test('native full screen survives real view navigation, reload and local/web switches until explicitly disabled',async t=>{
 const {w,web}=setup(t);assert.equal(w.windowControl(web).fullscreen,false);w.windowControl(web,true);
 await w.webView.webContents.loadURL('https://web.test/admin/ventas?screen=order&ticket=1');w.webView.webContents.reload();w.useLocal();w.show('web');
 assert.equal(w.windowState().fullscreen,true);assert.equal(w.win.fullscreen,true);w.windowControl(web,false);assert.equal(w.windowState().fullscreen,false);
 assert.equal(w.webView.bounds.y,48);assert.equal(w.webView.bounds.height,902);
});
test('saved full screen is restored on startup and deliberate native exit persists the disabled preference',t=>{
 const {w,saved}=setup(t,{fullscreen:true});assert.equal(w.win.options.fullscreen,true);assert.equal(w.windowState().fullscreen,true);
 w.win.emit('leave-full-screen');assert.deepEqual(saved.at(-1),{fullscreen:false});assert.equal(w.windowState().fullscreen,false);
 assert.equal(w.webView.webContents.sent.at(-1).name,'fisitaap:window-state');
});
test('window controls reject foreign origins, subframes, popups and non-boolean input',t=>{
 const {w,web,event}=setup(t);
 assert.throws(()=>w.windowControl({...web,senderFrame:{url:'https://outside.test',parent:null}},true),/principal/);
 assert.throws(()=>w.windowControl({...web,senderFrame:{...web.senderFrame,parent:{}}},true),/principal/);
 assert.throws(()=>w.windowControl(event({},'https://web.test/admin'),true),/principal/);
 assert.throws(()=>w.windowControl(web,'true'),/inválido/);assert.equal(w.windowState().fullscreen,false);
 const shell=event(w.win.webContents,'http://127.0.0.1:18766/desktop-shell.html');assert.equal(w.windowControl(shell,true).fullscreen,true);
 const local=event(w.localView.webContents,'http://127.0.0.1:18766/');assert.equal(w.windowControl(local,false).fullscreen,false);
});
