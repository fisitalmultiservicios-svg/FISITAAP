'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict');
const {electronPrinter,normalizeJob}=require('../desktop/print-bridge');
function driver(printers,success=true){
 const calls=[];let destroyed=false;
 class BrowserWindow{
  constructor(){this.webContents={setWindowOpenHandler(){},getPrintersAsync:async()=>printers,print:(options,callback)=>{calls.push(options);callback(success,success?'':'Driver failed');}};}
  async loadURL(url){assert(url.startsWith('data:text/html'));}
  destroy(){destroyed=true;}
 }
 return{electron:{BrowserWindow},calls,destroyed:()=>destroyed};
}
test('QA Windows native printing selects the installed driver silently with 58 mm paper',async()=>{
 const mock=driver([{name:'Thermal 58',displayName:'My thermal printer'}]);
 const job=normalizeJob({id:'one',text:'Receipt',printer:{type:'usb',name:'Thermal 58',width:58}});
 const result=await electronPrinter(mock.electron)(job,{});
 assert.equal(result.status,'sent_to_printer');assert.equal(mock.calls.length,1);
 assert.equal(mock.calls[0].deviceName,'Thermal 58');assert.equal(mock.calls[0].silent,true);assert.equal(mock.calls[0].pageSize.width,58000);assert(mock.destroyed());
});
test('QA an absent Windows printer fails before sending and closes the hidden view',async()=>{
 const mock=driver([{name:'Different Printer'}]);
 await assert.rejects(()=>electronPrinter(mock.electron)(normalizeJob({id:'two',text:'Receipt',printer:{type:'usb',name:'Missing'}}),{}),/controlador/);
 assert.equal(mock.calls.length,0);assert(mock.destroyed());
});
test('QA a Windows driver failure cannot be reported as successful printing',async()=>{
 const mock=driver([{name:'Thermal 80'}],false);
 await assert.rejects(()=>electronPrinter(mock.electron)(normalizeJob({id:'three',text:'Receipt',printer:{type:'usb',name:'Thermal 80',width:80}}),{}),/Driver failed/);
 assert.equal(mock.calls[0].pageSize.width,80000);assert(mock.destroyed());
});
