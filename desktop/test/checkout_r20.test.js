'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {normalizeJob,receiptHTML,electronPrinter,createPrintBridge}=require('../print-bridge');
test('carta selects the configured Windows driver and Letter dimensions instead of thermal microns',async()=>{
 const calls=[];let html='';class BrowserWindow{constructor(){this.webContents={setWindowOpenHandler(){},getPrintersAsync:async()=>[{name:'Carta oficina'}],print:(o,cb)=>{calls.push(o);cb(true);}};}async loadURL(v){html=decodeURIComponent(v);}destroy(){}}
 const job=normalizeJob({id:'carta',text:'Café ₡1.000 <script>',width:216,printer:{type:'usb',name:'Carta oficina'}});assert.equal(job.width,216);assert(receiptHTML(job).includes('@page{size:letter'));assert(receiptHTML(job).includes('&lt;script&gt;'));assert.equal((await electronPrinter({BrowserWindow})(job,{})).status,'sent_to_printer');assert.equal(calls[0].pageSize,'Letter');assert.equal(calls[0].deviceName,'Carta oficina');assert.equal(calls[0].silent,true);assert(html.includes('Café'));
 assert.throws(()=>normalizeJob({...job,printer:{type:'network',host:'192.168.1.2'}}),/carta/);assert.throws(()=>normalizeJob({...job,width:210,printer:{type:'usb'}}));
});
test('letter PDF uses Letter geometry in Electron without drawer',async t=>{
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'r20-letterpdf-'));t.after(()=>fs.rmSync(dir,{recursive:true,force:true}));let options;class BrowserWindow{constructor(){this.webContents={setWindowOpenHandler(){},printToPDF:async o=>{options=o;return Buffer.from('%PDF-fixture');}};}async loadURL(){}destroy(){}}
 const file=path.join(dir,'carta.pdf');const r=await electronPrinter({BrowserWindow})(normalizeJob({id:'pdf',text:'TOTAL CRC 1000',width:216,printer:{type:'browser'}}),{pdfFile:file});assert.equal(r.status,'pdf_created');assert.equal(options.pageSize,'Letter');assert(fs.readFileSync(file).toString().startsWith('%PDF'));
});
test('PDF intent keeps format despite local physical defaults; printer intent uses configured carta default once',async t=>{
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'r20-defaults-'));t.after(()=>fs.rmSync(dir,{recursive:true,force:true}));const sent=[];const service=await createPrintBridge({dataDir:dir,port:0,getConfig:()=>({printer:{type:'usb',name:'Carta oficina',width:216}}),print:async job=>{sent.push(job);return {status:job.printer.type==='browser'?'pdf_created':'sent_to_printer'};}});t.after(()=>service.close());
 const pdf={id:'explicit-receipt',text:'Receipt',pdf_only:true,printer:{type:'browser',width:58}};await service.print(pdf);const job={id:'default-receipt',text:'Receipt',printer:{type:'browser',width:80}};await service.print(job);await service.print(job);assert.equal(sent.length,2);assert.equal(sent[0].width,58);assert.equal(sent[0].printer.type,'browser');assert.equal(sent[1].width,216);assert.equal(sent[1].printer.name,'Carta oficina');
});
