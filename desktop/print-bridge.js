'use strict';
const http=require('node:http');
const fs=require('node:fs');
const path=require('node:path');
const crypto=require('node:crypto');
const net=require('node:net');

const esc=value=>String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function normalizeJob(raw,defaults={}) {
  if(!raw||typeof raw!=='object'||typeof raw.id!=='string'||!raw.id||raw.id.length>240||typeof raw.text!=='string'||Buffer.byteLength(raw.text)>180000)throw new Error('Trabajo de impresión inválido.');
  const p=raw.printer||{},width=Number(p.width||raw.width||defaults.width||80);
  if(![58,80].includes(width))throw new Error('El papel debe ser de 58 u 80 mm.');
  const type=p.type||defaults.type||'browser';if(!['browser','usb','bluetooth','network'].includes(type))throw new Error('Tipo de impresora no disponible.');
  return {id:raw.id,document:String(raw.document||'receipt').slice(0,30),text:raw.text.replace(/\0/g,''),width,printer:{type,name:String(p.name||defaults.name||'').slice(0,150),host:String(p.host||'').slice(0,150),port:Number(p.port||9100)}};
}
function receiptHTML(job) {
  return '<!doctype html><html lang="es"><meta charset="utf-8"><style>@page{size:'+job.width+'mm auto;margin:3mm}body{margin:0;width:'+(job.width-6)+'mm;font:11px "Consolas",monospace;color:#000}pre{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.35;margin:0}</style><body><pre>'+esc(job.text)+'</pre></body></html>';
}
function save(file,value) {
  const temp=file+'.'+crypto.randomBytes(8).toString('hex')+'.tmp';const fd=fs.openSync(temp,'wx',0o600);
  try{fs.writeFileSync(fd,JSON.stringify(value));fs.fsyncSync(fd);}finally{fs.closeSync(fd);}
  try{fs.renameSync(temp,file);}finally{fs.rmSync(temp,{force:true});}
}
function networkPrint(job) {
  const host=job.printer.host,port=job.printer.port;
  if(!net.isIPv4(host)||!(/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/.test(host))||!Number.isInteger(port)||port<1||port>65535)throw new Error('Usa la IP privada de la impresora del local y su puerto.');
  const bytes=Buffer.concat([Buffer.from([0x1b,0x40,0x1b,0x74,0x10]),Buffer.from(job.text.replace(/₡/g,'CRC '),'latin1'),Buffer.from('\n\n\n'),Buffer.from([0x1d,0x56,0x42,0x00])]);
  return new Promise((resolve,reject)=>{const socket=net.createConnection({host,port});socket.setTimeout(7000);socket.once('error',reject);socket.once('timeout',()=>socket.destroy(new Error('La impresora no respondió. Revisa su conexión.')));socket.once('connect',()=>socket.end(bytes));socket.once('close',hadError=>{if(!hadError)resolve({status:'sent_to_printer'});});});
}
function electronPrinter(electron) {
  return async (job,{pdfFile})=>{
    if(job.printer.type==='network'&&!job.printer.name)return networkPrint(job);
    const win=new electron.BrowserWindow({show:false,width:420,height:900,webPreferences:{sandbox:true,contextIsolation:true,nodeIntegration:false}});
    try{
      win.webContents.setWindowOpenHandler(()=>({action:'deny'}));
      await win.loadURL('data:text/html;charset=utf-8,'+encodeURIComponent(receiptHTML(job)));
      if(job.printer.type==='browser') {
        // Electron PDF dimensions are inches; webContents.print uses microns instead.
        const heightMM=Math.max(150,Math.min(3000,job.text.split('\n').length*5));
        const pdf=await win.webContents.printToPDF({printBackground:true,pageSize:{width:job.width/25.4,height:heightMM/25.4},margins:{top:0,right:0,bottom:0,left:0}});
        fs.writeFileSync(pdfFile,pdf,{mode:0o600});return {status:'pdf_created'};
      }
      const printers=await win.webContents.getPrintersAsync();if(!job.printer.name||!printers.some(p=>p.name===job.printer.name))throw new Error('La impresora no aparece en Windows. Instala su controlador y selecciona su nombre exacto.');
      await new Promise((resolve,reject)=>win.webContents.print({silent:true,deviceName:job.printer.name,printBackground:true,margins:{marginType:'none'},pageSize:{width:job.width*1000,height:300000}},(ok,error)=>ok?resolve():reject(new Error(error||'Windows no aceptó el trabajo de impresión.'))));
      return {status:'sent_to_printer'};
    }finally{win.destroy();}
  };
}
async function createPrintBridge(options) {
  const dir=path.join(options.dataDir,'impresion');fs.mkdirSync(dir,{recursive:true,mode:0o700});
  let queue=Promise.resolve();
  const config=()=>options.getConfig?.()||{};
  async function processJob(raw) {
    if(raw?.printer?.type==='browser' && config().printer?.name)raw={...raw,printer:{...raw.printer,...config().printer}};
    const job=normalizeJob(raw,config().printer||{}),key=crypto.createHash('sha256').update(job.id).digest('hex'),hash=crypto.createHash('sha256').update(JSON.stringify(job)).digest('hex'),file=path.join(dir,key+'.json'),pdfFile=path.join(dir,key+'.pdf');
    if(fs.existsSync(file)) {
      const previous=JSON.parse(fs.readFileSync(file,'utf8'));if(previous.hash!==hash)throw new Error('El identificador corresponde a otro contenido. Usa Reimprimir para generar una copia nueva.');
      if(previous.result)return {...previous.result,duplicate:true};
      throw new Error('El envío anterior no quedó confirmado. Revisa la impresora antes de usar Reimprimir.');
    }
    save(file,{hash,status:'sending',created_at:new Date().toISOString()});
    const result=await options.print(job,{pdfFile});
    if(!result||!['pdf_created','sent_to_printer'].includes(result.status))throw new Error('La impresora no confirmó el envío.');
    const output={ok:true,status:result.status,...(result.status==='pdf_created'?{pdf_url:'/pdf/'+key+'.pdf'}:{})};save(file,{hash,status:'completed',result:output,created_at:new Date().toISOString()});return output;
  }
  const enqueue=raw=>{const pending=queue.then(()=>processJob(raw));queue=pending.catch(()=>{});return pending;};
  const server=http.createServer(async(req,res)=>{
    const origin=String(req.headers.origin||''),cfg=config(),allowed=Boolean(cfg.origin&&origin===cfg.origin);
    const headers={'Content-Type':'application/json; charset=utf-8','Cache-Control':'no-store','X-Content-Type-Options':'nosniff',...(allowed?{'Access-Control-Allow-Origin':origin,'Vary':'Origin','Access-Control-Allow-Methods':'POST, GET, OPTIONS','Access-Control-Allow-Headers':'Content-Type, x-fisitaap-token','Access-Control-Allow-Private-Network':'true'}:{})};
    const send=(status,value)=>{res.writeHead(status,headers);res.end(JSON.stringify(value));};
    try {
      if(!['127.0.0.1','::1','::ffff:127.0.0.1'].includes(req.socket.remoteAddress))return send(403,{ok:false,error:'La impresión requiere este equipo.'});
      if(!/^(127\.0\.0\.1|localhost)(:\d+)?$/.test(req.headers.host||''))return send(403,{ok:false,error:'Host no autorizado.'});
      if(req.method==='OPTIONS'){if(!allowed)return send(403,{ok:false,error:'Sitio no autorizado.'});res.writeHead(204,headers);return res.end();}
      const supplied=String(req.headers['x-fisitaap-token']||'');
      if(!cfg.token||supplied.length!==cfg.token.length||!crypto.timingSafeEqual(Buffer.from(supplied),Buffer.from(cfg.token))||(origin&&!allowed))return send(403,{ok:false,error:'Código o sitio de impresión no autorizado.'});
      const pathname=new URL(req.url,'http://127.0.0.1').pathname;
      if(pathname==='/health'&&req.method==='GET')return send(200,{ok:true,service:'FISITAAP Print',version:require('./package.json').version});
      if(pathname.startsWith('/pdf/')&&req.method==='GET') {
        const key=pathname.slice(5);if(!/^[a-f0-9]{64}\.pdf$/.test(key))return send(404,{ok:false});
        const file=path.join(dir,key);if(!fs.existsSync(file))return send(404,{ok:false});res.writeHead(200,{...headers,'Content-Type':'application/pdf','Content-Disposition':'inline; filename="Comprobante-FISITAAP.pdf"'});return res.end(fs.readFileSync(file));
      }
      if(pathname!=='/print'||req.method!=='POST')return send(404,{ok:false,error:'Ruta no disponible.'});
      let text='';for await(const chunk of req){text+=chunk;if(Buffer.byteLength(text)>240000)throw new Error('Trabajo demasiado grande.');}
      send(200,await enqueue(JSON.parse(text)));
    }catch(error){send(422,{ok:false,error:error.message});}
  });
  // Local printing does not depend on the optional web bridge owning its port.
  // Keep the same durable queue when reconnecting; never displace another process.
  const requestedPort=options.port??18765;
  let pending=null,lastError=null,closed=false;
  const status=()=>({available:server.listening,port:server.address()?.port??requestedPort,code:server.listening?null:lastError?.code??null});
  const listen=()=>{
    if(closed)return Promise.reject(new Error('El servicio de impresión está cerrado.'));
    if(server.listening)return Promise.resolve(status());
    if(pending)return pending;
    pending=new Promise((resolve,reject)=>{
      const cleanup=()=>{server.removeListener('error',failed);server.removeListener('listening',ready);};
      const failed=error=>{cleanup();lastError=error;reject(error);};
      const ready=()=>{cleanup();lastError=null;if(closed)server.close();resolve(status());};
      server.once('error',failed);server.once('listening',ready);
      try{server.listen(requestedPort,'127.0.0.1');}catch(error){failed(error);}
    }).catch(error=>{
      if(options.allowPortConflict&&error.code==='EADDRINUSE')return status();
      throw error;
    }).finally(()=>{pending=null;});
    return pending;
  };
  await listen();
  return {server,get port(){return status().port;},status,retry:listen,print:enqueue,close:()=>{closed=true;if(server.listening)server.close();}};
}
module.exports={createPrintBridge,normalizeJob,receiptHTML,electronPrinter};
