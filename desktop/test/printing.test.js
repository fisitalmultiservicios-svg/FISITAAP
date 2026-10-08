'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {createPrintBridge,normalizeJob,receiptHTML}=require('../print-bridge');
test('web browser profile uses native default while explicit kitchen routing is preserved',async t=>{
 const dataDir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-native-route-'));
 t.after(()=>fs.rmSync(dataDir,{recursive:true,force:true}));const jobs=[];
 const service=await createPrintBridge({dataDir,port:0,getConfig:()=>({printer:{type:'usb',name:'Caja Windows',width:58}}),print:async job=>{jobs.push(job);return {status:'sent_to_printer'};}});
 t.after(()=>service.close());
 const receipt={id:'native-default',text:'Receipt',printer:{type:'browser',width:80}};
 await service.print(receipt);await service.print(receipt);
 await service.print({id:'native-kitchen',text:'Kitchen',printer:{type:'network',host:'192.168.1.50',width:80}});
 assert.equal(jobs.length,2);assert.equal(jobs[0].printer.name,'Caja Windows');assert.equal(jobs[0].width,58);
 assert.equal(jobs[1].printer.type,'network');assert.equal(jobs[1].printer.host,'192.168.1.50');
});
test('printer jobs validate paper, escape text and retain routing',()=>{
 assert.throws(()=>normalizeJob({id:'1',text:'Test',width:72}));
 const job=normalizeJob({id:'1',text:'<script>attack()</script>',printer:{type:'network',host:'192.168.1.70',width:58}});
 assert.equal(job.printer.host,'192.168.1.70');assert(receiptHTML(job).includes('&lt;script&gt;'));assert(!receiptHTML(job).includes('<script>'));
});
test('authenticated loopback bridge sends once, survives restart and does not expose PDFs cross-site',async()=>{
 const dataDir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-print-')),cfg={token:'a'.repeat(64),origin:'https://web.test'};let prints=0;
 const print=async(job,{pdfFile})=>{prints++;fs.writeFileSync(pdfFile,'%PDF-1.7 fixture');return {status:'pdf_created'};};let service=await createPrintBridge({dataDir,port:0,getConfig:()=>cfg,print});
 const send=async(job,token=cfg.token,origin=cfg.origin)=>{const r=await fetch('http://127.0.0.1:'+service.port+'/print',{method:'POST',headers:{Origin:origin,'x-fisitaap-token':token,'Content-Type':'application/json'},body:JSON.stringify(job)});return {status:r.status,data:await r.json()};};
 const close=()=>new Promise(r=>service.server.close(r));
 try{
  const job={id:'sale-123',text:'Venta 123\nTOTAL CRC 1.243,01'};
  assert.equal((await send(job,'bad')).status,403);assert.equal((await send(job,cfg.token,'https://attacker.test')).status,403);
  const both=await Promise.all([send(job),send(job)]);assert.equal(prints,1);assert(both.every(r=>r.status===200));assert(both.some(r=>r.data.duplicate));
  const changed=await send({...job,text:'Changed'});assert.equal(changed.status,422);assert.equal(prints,1);
  await close();service=await createPrintBridge({dataDir,port:0,getConfig:()=>cfg,print});assert.equal((await send(job)).data.duplicate,true);assert.equal(prints,1);
  const url='http://127.0.0.1:'+service.port+both[0].data.pdf_url;assert.equal((await fetch(url)).status,403);const pdf=await fetch(url,{headers:{Origin:cfg.origin,'x-fisitaap-token':cfg.token}});assert.equal(pdf.status,200);assert((await pdf.text()).startsWith('%PDF'));
 }finally{await close();fs.rmSync(dataDir,{recursive:true,force:true});}
});
test('uncertain printer delivery requires deliberate reprint instead of sending twice',async()=>{
 const dataDir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-uncertain-'));let attempts=0;const s=await createPrintBridge({dataDir,port:0,print:async()=>{attempts++;throw new Error('Printer unavailable');}});
 try{await assert.rejects(s.print({id:'one',text:'Test'}));await assert.rejects(s.print({id:'one',text:'Test'}),/anterior no quedó confirmado/);assert.equal(attempts,1);}finally{await new Promise(r=>s.server.close(r));fs.rmSync(dataDir,{recursive:true,force:true});}
});
test('a busy optional web port preserves local printing and reconnects without repeating jobs',async t=>{
 const http=require('node:http'),blocker=http.createServer((req,res)=>{res.setHeader('Connection','close');res.end('Other application');});
 await new Promise(r=>blocker.listen(0,'127.0.0.1',r));
 t.after(()=>{if(blocker.listening)blocker.close();});
 const port=blocker.address().port,dataDir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-busy-print-'));
 t.after(()=>fs.rmSync(dataDir,{recursive:true,force:true}));
 const cfg={token:'b'.repeat(64),origin:'https://web.test'};let prints=0;
 const service=await createPrintBridge({dataDir,port,allowPortConflict:true,getConfig:()=>cfg,print:async(job,{pdfFile})=>{prints++;fs.writeFileSync(pdfFile,'%PDF-1.7 test');return {status:'pdf_created'};}});
 t.after(()=>service.close());
 assert.deepEqual(service.status(),{available:false,port,code:'EADDRINUSE'});
 assert.equal(await(await fetch('http://127.0.0.1:'+port)).text(),'Other application');
 const job={id:'offline-sale',text:'Venta sin internet'};
 const local=await service.print(job);assert.equal(local.status,'pdf_created');assert.equal(prints,1);
 const busy=await Promise.all([service.retry(),service.retry()]);assert(busy.every(s=>s.code==='EADDRINUSE'));
 assert.equal(await(await fetch('http://127.0.0.1:'+port)).text(),'Other application');
 await new Promise(r=>blocker.close(r));
 const recovered=await Promise.all([service.retry(),service.retry()]);assert(recovered.every(s=>s.available));
 const url='http://127.0.0.1:'+port;
 assert.equal((await fetch(url+'/health')).status,403);
 const headers={'x-fisitaap-token':cfg.token,Origin:cfg.origin,'Content-Type':'application/json'};
 const response=await fetch(url+'/print',{method:'POST',headers,body:JSON.stringify(job)});
 assert.equal(response.status,200);assert.equal((await response.json()).duplicate,true);assert.equal(prints,1);
 assert.equal((await fetch(url+local.pdf_url,{headers})).status,200);
});
test('only explicitly optional address conflicts are tolerated',async t=>{
 const net=require('node:net'),blocker=net.createServer();await new Promise(r=>blocker.listen(0,'127.0.0.1',r));
 t.after(()=>blocker.close());
 const dataDir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-strict-print-'));t.after(()=>fs.rmSync(dataDir,{recursive:true,force:true}));
 await assert.rejects(createPrintBridge({dataDir,port:blocker.address().port,print:async()=>{}}),{code:'EADDRINUSE'});
 await assert.rejects(createPrintBridge({dataDir,port:-1,allowPortConflict:true,print:async()=>{}}),{code:'ERR_SOCKET_BAD_PORT'});
});
