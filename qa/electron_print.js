'use strict';
// Real Electron printing on a virtual display. This does not substitute for a Windows driver or paper test.
const electron=require('electron'),fs=require('node:fs'),path=require('node:path'),os=require('node:os');
electron.app.on('window-all-closed',()=>{});
const {electronPrinter,normalizeJob}=require('../desktop/print-bridge');
const dir=fs.mkdtempSync(path.join(os.tmpdir(),'fisitaap-print-native-'));
electron.app.setPath('userData',dir);
const out=process.env.FISITAAP_QA_OUT||'/workspace/fisitaap-qa/2026-10-07';
electron.app.whenReady().then(async()=>{
 const send=electronPrinter(electron);
 for(const width of [58,80]){
  const file=path.join(out,'comprobante-'+width+'.pdf');
  const result=await send(normalizeJob({id:'native-'+width,width,text:'FISITAAP\nCafé y piña\nTotal: ₡1.234,56\nTexto <script>literal</script>\n'+Array.from({length:20},(_,i)=>'Artículo '+i+' — ₡500,00').join('\n'),printer:{type:'browser'}}),{pdfFile:file});
  if(result.status!=='pdf_created'||fs.statSync(file).size<500)throw new Error('PDF not generated');
  console.log('PASS actual Electron PDF '+width+' mm generated');
 }
 electron.app.quit();fs.rmSync(dir,{recursive:true,force:true});
}).catch(error=>{console.error(error.message);electron.app.exit(1);fs.rmSync(dir,{recursive:true,force:true});});
