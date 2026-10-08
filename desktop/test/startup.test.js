'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),http=require('node:http'),vm=require('node:vm');
const {EventEmitter}=require('node:events'),{createRequire}=require('node:module');
const {electronFixture}=require('./electron-fixture');
const mainPath=path.join(__dirname,'..','main.js'),source=fs.readFileSync(mainPath,'utf8'),realRequire=createRequire(mainPath);

function launch({dataDir,printPort,lock=true,localError}) {
 const app=new EventEmitter(),fixture=electronFixture(),{windows,errors,handlers}=fixture,localServices=[];
 let completion=Promise.resolve(),readyCalls=0,quitCalls=0;
 app.requestSingleInstanceLock=()=>lock;
 app.quit=()=>{quitCalls++;app.emit('before-quit');};
 app.getPath=()=>dataDir;
 app.whenReady=()=>{readyCalls++;return {then:handler=>({catch:failed=>{completion=Promise.resolve().then(handler).catch(failed);return completion;}})};};
 const electron={app,...fixture.electron};
 const requireMock=name=>{
  if(name==='electron')return electron;
  if(name==='./server')return {createLocalServer:async options=>{if(localError)throw localError;const service=await realRequire('./server').createLocalServer({...options,port:0});localServices.push(service);return service;}};
  if(name==='./print-bridge')return {...realRequire(name),createPrintBridge:options=>realRequire(name).createPrintBridge({...options,port:printPort})};
  return realRequire(name);
 };
 vm.runInNewContext(source,{require:requireMock,__dirname:path.dirname(mainPath),Buffer,URL},{filename:mainPath});
 return {completion,app,windows,errors,handlers,localServices,views:fixture.views,get readyCalls(){return readyCalls;},get quitCalls(){return quitCalls;}};
}

test('Windows startup opens the real local POS when the web print bridge port is occupied',async t=>{
 const blocker=http.createServer((req,res)=>res.end('Unrelated service'));
 await new Promise(r=>blocker.listen(0,'127.0.0.1',r));t.after(()=>{if(blocker.listening)blocker.close();});
 const dataDir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-startup-'));t.after(()=>fs.rmSync(dataDir,{recursive:true,force:true}));
 const saved={origin:'https://web.test',printer:{name:'',width:58,type:'browser'},encryptedToken:Buffer.from('protected:'+'c'.repeat(64)).toString('base64')};
 const settingsFile=path.join(dataDir,'impresoras.json');fs.writeFileSync(settingsFile,JSON.stringify(saved));
 const running=launch({dataDir,printPort:blocker.address().port});t.after(()=>running.app.emit('before-quit'));
 await running.completion;
 assert.equal(running.errors.length,0);assert.equal(running.quitCalls,0);assert.equal(running.windows.length,1);
 const url=running.localServices[0].url;assert.equal((await fetch(running.windows[0].url)).status,200);assert.equal(running.views[0].webContents.url,url);
 const settingsEvent={senderFrame:{url:url+'/print-settings.html',parent:null}};
 const config=running.handlers.get('fisitaap:settings')(settingsEvent);
 assert.equal(config.bridge.available,false);assert.equal(config.bridge.code,'EADDRINUSE');assert.equal(config.printer.width,58);
 assert.equal(fs.readFileSync(settingsFile,'utf8'),JSON.stringify(saved));
 const posEvent={senderFrame:{url,parent:null}};
 const shellEvent={sender:running.windows[0].webContents,senderFrame:{url:url+'/desktop-shell.html',parent:null}};
 assert.equal(running.handlers.get('fisitaap:workspace-state')(shellEvent).mode,'local');
 assert.throws(()=>running.handlers.get('fisitaap:workspace-state')(posEvent),/botones/);
 assert.throws(()=>running.handlers.get('fisitaap:workspace-state')({sender:running.windows[0].webContents,senderFrame:{url:'https://web.test/desktop-shell.html',parent:null}}),/configuración/);
 const result=await running.handlers.get('fisitaap:print')(posEvent,{id:'startup-test-sale',text:'Comprobante local'});
 assert.throws(()=>running.handlers.get('fisitaap:print')({senderFrame:{url:'https://attacker.test/admin/recibo',parent:null}},{}),/no autorizado/);
 assert.throws(()=>running.handlers.get('fisitaap:print')({senderFrame:{url,parent:{}}},{}),/no autorizado/);
 assert.equal(result.status,'pdf_created');
 assert.throws(()=>running.handlers.get('fisitaap:retry-print-bridge')(posEvent),/Configurar impresoras/);
 assert.throws(()=>running.handlers.get('fisitaap:retry-print-bridge')({senderFrame:{url:'https://attacker.test/print-settings.html',parent:null}}),/configuración/);
 await new Promise(r=>blocker.close(r));
 assert.equal((await running.handlers.get('fisitaap:retry-print-bridge')(settingsEvent)).available,true);
 running.app.emit('second-instance');assert.equal(running.windows[0].focused,true);
});
test('a second Windows instance quits without starting any server',async()=>{
 const running=launch({lock:false});await running.completion;
 assert.equal(running.quitCalls,1);assert.equal(running.readyCalls,0);assert.equal(running.localServices.length,0);assert.equal(running.windows.length,0);assert.equal(running.errors.length,0);
});
test('failure of the central sales service still stops startup with an explanation',async()=>{
 const running=launch({localError:new Error('El equipo central no pudo abrir sus datos.')});await running.completion;
 assert.equal(running.quitCalls,1);assert.equal(running.windows.length,0);assert.equal(running.errors[0].message,'El equipo central no pudo abrir sus datos.');
});
