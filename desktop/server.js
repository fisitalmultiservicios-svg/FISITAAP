'use strict';
const http=require('node:http');
const fs=require('node:fs');
const path=require('node:path');
const os=require('node:os');
const crypto=require('node:crypto');
const bcrypt=require('bcryptjs');
const jsonClone=value=>structuredClone(value);
const integer=(value,min=0,max=99999999999)=>{if(!Number.isSafeInteger(value)||value<min||value>max)throw new Error('Revisa las cantidades y los importes.');return value;};
const uuid=value=>{if(!/^[a-f\d]{8}-[a-f\d]{4}-4[a-f\d]{3}-[89ab][a-f\d]{3}-[a-f\d]{12}$/.test(value||''))throw new Error('Identificador de operación no válido.');return value;};
const round=value=>Math.floor(value+0.5);
function quote(snapshot,items,at=Date.now(),discount=0){
  if(!Array.isArray(items)||!items.length||items.length>100)throw new Error('Agrega entre 1 y 100 líneas.');
  let net=0,tax=0;const stock={},lines=[];
  for(const item of items){
    const p=snapshot.products.find(p=>p.id===item.id);if(!p)throw new Error('Producto no disponible.');
    const quantity=integer(item.quantity,p.min,99999000);if((quantity-p.min)%p.step)throw new Error('Revisa el incremento de cantidad.');
    const options=[...new Set(item.options||[])].sort((a,b)=>a-b);const selected=[];let extras=0;
    for(const g of p.groups){let count=0;for(const o of g.options)if(options.includes(o.id)){count++;extras+=o.price;selected.push(o.id);}if(count<g.min||count>g.max)throw new Error('Revisa las opciones de '+g.name);}
    selected.sort((a,b)=>a-b);if(JSON.stringify(selected)!==JSON.stringify(options))throw new Error('Opción no válida.');
    const expired=p.promo_expires&&Date.parse(p.promo_expires)<at;const price=expired?(p.normal_price??p.price):p.price;const paid=(!expired&&p.bogo)?quantity-Math.floor(quantity/2000)*1000:quantity;
    const gross=round((price*paid+extras*quantity)/1000);
    const lineNet=snapshot.tenant.tax_included?round(gross/(1+p.rate/10000)):gross;
    const lineTax=snapshot.tenant.tax_included?gross-lineNet:round(lineNet*p.rate/10000);
    net+=lineNet;tax+=lineTax;
    lines.push({id:p.id,name:p.name,quantity,options,option_labels:p.groups.flatMap(g=>g.options.filter(o=>options.includes(o.id)).map(o=>g.name+': '+o.name)),note:String(item.note||'').slice(0,500),net:lineNet,tax:lineTax});
    if(p.components.length){for(const c of p.components)if(c.track)stock[c.id]=(stock[c.id]||0)+round(quantity*c.quantity/1000);}
    else if(p.track)stock[p.id]=(stock[p.id]||0)+quantity;
  }
  integer(discount,0,net);return {net,tax,discount,total:net+tax-discount,stock,lines};
}
class LocalStore {
  constructor(dir){
    this.dir=dir;fs.mkdirSync(dir,{recursive:true,mode:0o700});fs.mkdirSync(path.join(dir,'backups'),{recursive:true,mode:0o700});this.file=path.join(dir,'operaciones.json');
    this.data=fs.existsSync(this.file)?JSON.parse(fs.readFileSync(this.file,'utf8')):{version:1,connection:null,snapshot:null,stocks:{},sales:[],shifts:[],lastSync:null,syncMessage:'Conecta primero el equipo central.'};
    if(this.data.version!==1||!Array.isArray(this.data.sales)||!Array.isArray(this.data.shifts))throw new Error('El archivo de operaciones necesita revisión. Se conservó sin cambios.');
  }
  commit(next){
    const serialized=JSON.stringify(next),temp=this.file+'.nuevo';let fd;
    try{fd=fs.openSync(temp,'w',0o600);fs.writeFileSync(fd,serialized);fs.fsyncSync(fd);fs.closeSync(fd);fd=null;fs.renameSync(temp,this.file);try{const d=fs.openSync(this.dir,'r');fs.fsyncSync(d);fs.closeSync(d);}catch{}this.data=next;}
    finally{if(fd!==null&&fd!==undefined)fs.closeSync(fd);}
  }
  backup(){const target=path.join(this.dir,'backups','FISITAAP-'+new Date().toISOString().replace(/[:.]/g,'-')+'.json');fs.copyFileSync(this.file,target);fs.chmodSync(target,0o600);return target;}
}
async function createLocalServer(options){
  const store=new LocalStore(options.dataDir),codec=options.tokenCodec||{encode:v=>v,decode:v=>v};
  const sessions=new Map(),attempts=new Map();let busy=false,pairing=false,timer,port=options.port??18766;
  const interfaces=Object.values(os.networkInterfaces()).flat().filter(x=>x&&x.family==='IPv4'&&!x.internal).map(x=>x.address);
  const allowedHosts=new Set(['localhost','127.0.0.1','[::1]',...interfaces]);
  const loopback=req=>['127.0.0.1','::1','::ffff:127.0.0.1'].includes(req.socket.remoteAddress);
  const body=async req=>{let data='';for await(const chunk of req){data+=chunk;if(Buffer.byteLength(data)>2*1024*1024)throw new Error('Solicitud demasiado grande.');}return JSON.parse(data||'{}');};
  const send=(res,status,value,headers={})=>{res.writeHead(status,{'Content-Type':'application/json; charset=utf-8','Cache-Control':'no-store','X-Content-Type-Options':'nosniff',...headers});res.end(JSON.stringify(value));};
  const auth=(req,write=false)=>{
    const sid=String(req.headers.cookie||'').split(';').map(x=>x.trim()).find(x=>x.startsWith('fisitaap_local='))?.slice(15);
    const session=sessions.get(sid);if(!session||session.expires<Date.now())throw Object.assign(new Error('Ingresa a la caja para continuar.'),{status:401});
    const user=store.data.snapshot?.users.find(x=>x.id===session.user.id);
    if(!user||user.password_hash!==session.hash){sessions.delete(sid);throw Object.assign(new Error('El acceso local cambió. Vuelve a ingresar.'),{status:401});}
    if(write&&req.headers['x-csrf-token']!==session.csrf)throw Object.assign(new Error('Recarga la caja.'),{status:403});return session;
  };
  const webResponse=async(response,endpoint,action)=>{
    const invalid=()=>{
      const address=new URL(endpoint);
      const hint=address.pathname&&address.pathname!=='/'?' Si añadiste el nombre de tu tienda, usa '+address.origin+'.':'';
      return new Error('La dirección web devolvió una página en lugar de los datos de conexión. Copia la Dirección web que aparece en Administración → Windows y cajas sin internet.'+hint+' Comprueba que la actualización web esté instalada.');
    };
    let out;try{out=await response.json();}catch{throw invalid();}
    if(!out||typeof out!=='object'||Array.isArray(out)||typeof out.ok!=='boolean')throw invalid();
    if(!response.ok||!out.ok)throw new Error(typeof out.error==='string'?out.error.slice(0,500):'No se pudo conectar con la web.');
    if(action==='pair'||action==='snapshot'){
      const snap=out.snapshot;
      if(!snap||typeof snap.id!=='string'||!snap.id||!snap.tenant||!snap.branch||!['products','users','categories','customers'].every(key=>Array.isArray(snap[key]))||!snap.products.every(p=>p&&Array.isArray(p.components))||(action==='pair'&&(typeof out.token!=='string'||!out.token)))throw new Error('La web devolvió datos incompletos. Comprueba que la actualización web esté instalada y vuelve a conectar.');
    }
    return out;
  };
  const remote=async(action,data)=>{
    const c=store.data.connection;if(!c)throw new Error('Primero conecta el equipo central.');
    let response;try{response=await (options.fetch||fetch)(c.url+'/api/desktop/'+action,{method:'POST',headers:{'Content-Type':'application/json','Authorization':'Bearer '+codec.decode(c.token),'X-Fisitaap-Device':codec.decode(c.token)},body:JSON.stringify(data),signal:AbortSignal.timeout(60000)});}catch(error){if(error?.name==='TimeoutError')throw new Error('El servidor web tardó demasiado en responder. Intenta sincronizar de nuevo.');throw error;}
    return webResponse(response,c.url,action);
  };
  const sync=async()=>{
    if(busy||pairing||!store.data.connection)return;busy=true;
    try{
      const pending=store.data.sales.filter(s=>!s.synced).slice(0,100);
      const pendingShifts=new Set(pending.map(s=>s.shift_id));
      const shifts=store.data.shifts.filter(s=>!s.syncedClosed||pendingShifts.has(s.id)).sort((a,b)=>Number(pendingShifts.has(b.id))-Number(pendingShifts.has(a.id))).slice(0,100).map(s=>{
        const payload={id:s.id,user_id:s.user_id,snapshot_id:s.snapshot_id,register:s.register,opening_cash:s.opening_cash,opened_at:s.opened_at};
        if(s.closed_at&&!store.data.sales.some(p=>p.shift_id===s.id&&!p.synced&&!pending.includes(p))){payload.closed_at=s.closed_at;payload.closing_cash=s.closing_cash;}return payload;
      });
      const sent=pending.map(s=>s.payload);
      const out=await remote('sync',{sales:sent,shifts});
      let next=jsonClone(store.data);
      for(const ack of out.acknowledged||[]){const sale=next.sales.find(s=>s.id===ack.id);if(sale){sale.synced=true;sale.webSale=ack.sale_id;sale.conflicts=ack.conflicts||[];sale.error=null;}}
      for(const error of out.errors||[]){const sale=next.sales.find(s=>s.id===error.id);if(sale)sale.error=error.error;}
      for(const id of out.closed_shifts||[]){const shift=next.shifts.find(s=>s.id===id);if(shift)shift.syncedClosed=true;}
      next.lastSync=new Date().toISOString();next.syncMessage=out.errors?.length?'Hay operaciones pendientes que necesitan revisión.':'Conexión activa. Ventas recibidas por la web.';store.commit(next);
      // Refresh only after pending sales were accepted. Never overwrite local sales created during a request.
      if(!store.data.sales.some(s=>!s.synced)){
        const fresh=await remote('snapshot',{});next=jsonClone(store.data);const snap=fresh.snapshot;const stocks={};
        for(const p of snap.products){stocks[p.id]=p.stock;for(const c of p.components)stocks[c.id]=c.stock;}
        for(const sale of next.sales.filter(s=>!s.synced))for(const [id,qty]of Object.entries(sale.stock))stocks[id]=(stocks[id]||0)-qty;
        next.snapshot=snap;next.stocks=stocks;store.commit(next);
      }
    }catch(error){const next=jsonClone(store.data);next.syncMessage='Sin conexión con la web: '+error.message+'. Las ventas siguen guardadas aquí.';store.commit(next);}
    finally{busy=false;}
  };
  const handler=async(req,res)=>{
    let saleRequestId=null;try{
      const host=String(req.headers.host||'').split(':')[0];if(!allowedHosts.has(host))return send(res,403,{ok:false,error:'Dirección local no permitida.'});
      const base='http://'+req.headers.host;const parsed=new URL(req.url,base);const pathname=parsed.pathname;
      if(req.method==='POST'){
        if(req.headers.origin!==base||!String(req.headers['content-type']||'').startsWith('application/json'))return send(res,403,{ok:false,error:'Solicitud de otro sitio bloqueada.'});
        const data=await body(req);
        if(pathname==='/api/pair'){
          if(!loopback(req))return send(res,403,{ok:false,error:'Conecta el negocio desde el equipo central.'});
          if(pairing||busy)throw new Error('Hay una conexión o sincronización en curso. Espera a que termine.');
          if(store.data.sales.some(s=>!s.synced))throw new Error('Sincroniza las ventas pendientes antes de cambiar la conexión.');
          if(store.data.shifts.some(s=>!s.syncedClosed))throw new Error('Cierra y sincroniza los turnos locales antes de cambiar la conexión.');
          pairing=true;try{
          const u=new URL(data.url);if(u.protocol!=='https:'&&!(options.allowTestHttp&&u.protocol==='http:'))throw new Error('La web debe usar https://.');if(u.username||u.password||u.search||u.hash)throw new Error('Usa la dirección principal de la aplicación.');
          const endpoint=u.origin+u.pathname.replace(/\/$/,'');
          let r;try{r=await(options.fetch||fetch)(endpoint+'/api/desktop/pair',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({device_id:Number(data.device_id),code:String(data.code||'')}),signal:AbortSignal.timeout(60000)});}catch(error){if(error?.name==='TimeoutError')throw new Error('La web tardó demasiado en conectar. Se conservan los datos del equipo. Revisa la conexión antes de intentar otra vez.');throw error;}const out=await webResponse(r,endpoint,'pair');
          if(store.data.sales.some(s=>!s.synced)||store.data.shifts.some(s=>!s.syncedClosed))throw new Error('Se abrió un turno o se guardó una venta mientras conectabas. Se conserva la conexión anterior. Cierra y sincroniza primero.');
          const next=jsonClone(store.data);next.connection={url:endpoint,token:codec.encode(out.token)};next.snapshot=out.snapshot;next.stocks={};for(const p of out.snapshot.products){next.stocks[p.id]=p.stock;for(const c of p.components)next.stocks[c.id]=c.stock;}next.syncMessage='Equipo conectado. Ya puedes abrir un turno.';store.commit(next);return send(res,200,{ok:true});
          }finally{pairing=false;}
        }
        if(pathname==='/api/login'){
          const key=req.socket.remoteAddress;const times=(attempts.get(key)||[]).filter(t=>t>Date.now()-300000);if(times.length>=10)throw Object.assign(new Error('Espera 5 minutos antes de intentar otra vez.'),{status:429});times.push(Date.now());attempts.set(key,times);
          const user=store.data.snapshot?.users.find(u=>u.id===Number(data.user_id));if(!user||!bcrypt.compareSync(String(data.password||''),user.password_hash.replace(/^\$2y\$/,'$2b$')))throw new Error('Acceso local incorrecto.');
          const register=String(data.register||'').trim().slice(0,80);if(!register)throw new Error('Escribe el nombre de esta caja.');
          const sid=crypto.randomBytes(32).toString('hex'),csrf=crypto.randomBytes(32).toString('hex');sessions.set(sid,{user:{id:user.id,name:user.name},hash:user.password_hash,register,csrf,expires:Date.now()+12*60*60*1000});attempts.delete(key);
          return send(res,200,{ok:true,csrf},{'Set-Cookie':'fisitaap_local='+sid+'; HttpOnly; SameSite=Strict; Path=/'});
        }
        const session=auth(req,true);
        if(pathname==='/api/logout'){for(const [id,s]of sessions)if(s===session)sessions.delete(id);return send(res,200,{ok:true},{'Set-Cookie':'fisitaap_local=; Max-Age=0; HttpOnly; SameSite=Strict; Path=/'});}
        if(pathname==='/api/shift/open'){
          const next=jsonClone(store.data);if(next.shifts.some(s=>!s.closed_at&&(s.register===session.register||s.user_id===session.user.id)))throw new Error('Ya hay un turno abierto para esta caja o cajero.');
          next.shifts.push({id:crypto.randomUUID(),user_id:session.user.id,register:session.register,snapshot_id:next.snapshot.id,opening_cash:integer(data.opening_cash),opened_at:new Date().toISOString(),closed_at:null,syncedClosed:false});store.commit(next);return send(res,200,{ok:true});
        }
        if(pathname==='/api/shift/close'){
          const next=jsonClone(store.data),shift=next.shifts.find(s=>s.user_id===session.user.id&&s.register===session.register&&!s.closed_at);if(!shift)throw new Error('No tienes un turno abierto.');shift.closed_at=new Date().toISOString();shift.closing_cash=integer(data.closing_cash);shift.expected_cash=shift.opening_cash+next.sales.filter(s=>s.shift_id===shift.id).reduce((sum,s)=>sum+s.cashApplied,0);shift.difference=shift.closing_cash-shift.expected_cash;store.commit(next);return send(res,200,{ok:true,shift});
        }
        if(pathname==='/api/sales'){
          const id=uuid(data.id);saleRequestId=id;const next=jsonClone(store.data),old=next.sales.find(s=>s.id===id);
          const fingerprint=crypto.createHash('sha256').update(JSON.stringify({user:session.user.id,register:session.register,items:data.items,payments:data.payments,customer:data.customer||{},...(data.discount?{discount:data.discount}:{}),...(data.note?{note:String(data.note).slice(0,1000)}:{})})).digest('hex');
          if(old){if(old.fingerprint!==fingerprint)throw new Error('El identificador ya corresponde a otra venta.');return send(res,200,{ok:true,sale:{id:old.id,quote:old.quote,change:old.change,synced:old.synced}});}
          const shift=next.shifts.find(s=>s.user_id===session.user.id&&s.register===session.register&&!s.closed_at);if(!shift)throw new Error('Abre tu turno antes de cobrar.');
          const createdAt=new Date().toISOString();const calculated=quote(next.snapshot,data.items,Date.parse(createdAt),data.discount||0);if(data.expected_total!==undefined&&data.expected_total!==calculated.total)throw new Error('El precio o la promoción cambió. Actualiza la caja y revisa el total antes de cobrar.');for(const [pid,qty]of Object.entries(calculated.stock)){const p=next.snapshot.products.find(p=>p.id===Number(pid))||next.snapshot.products.flatMap(p=>p.components).find(p=>p.id===Number(pid));if((next.stocks[pid]||0)-qty<0&&!p?.allow_negative)throw new Error('Existencia insuficiente: '+(p?.name||pid));}
          const payments={cash:integer(data.payments?.cash||0),card:integer(data.payments?.card||0),sinpe:integer(data.payments?.sinpe||0)};const cashApplied=calculated.total-payments.card-payments.sinpe;if(cashApplied<0||payments.cash<cashApplied)throw new Error('Falta completar el pago o los montos superan el total.');
          const customer=data.customer||{};if(customer.id&&!next.snapshot.customers.some(c=>Number(c.id)===Number(customer.id)))throw new Error('Cliente no disponible.');if(!customer.id&&(customer.name||customer.phone)&&(!String(customer.name||'').trim()||!/^(506)?\d{8}$/.test(String(customer.phone||'').replace(/\D/g,''))))throw new Error('Escribe nombre y teléfono de 8 dígitos para el nuevo cliente.');
          const payload={id,snapshot_id:next.snapshot.id,user_id:session.user.id,register:session.register,shift_id:shift.id,created_at:createdAt,items:data.items,payments,customer,...(data.discount?{discount:data.discount}:{}),...(data.note?{note:String(data.note).slice(0,1000)}:{})};
          const sale={id,fingerprint,payload,user_id:session.user.id,register:session.register,shift_id:shift.id,created_at:payload.created_at,stock:calculated.stock,quote:calculated,change:payments.cash-cashApplied,cashApplied,synced:false};
          for(const [pid,qty]of Object.entries(calculated.stock))next.stocks[pid]=(next.stocks[pid]||0)-qty;next.sales.push(sale);store.commit(next);
          return send(res,200,{ok:true,sale:{id,quote:calculated,change:sale.change,synced:false}});
        }
        if(pathname==='/api/sync'){void sync();return send(res,200,{ok:true});}
        if(pathname==='/api/backup'){if(!loopback(req))throw new Error('Guarda el respaldo desde el equipo central.');const file=store.backup();return send(res,200,{ok:true,file:path.basename(file)});}
        throw Object.assign(new Error('Acción no disponible.'),{status:404});
      }
      if(req.method!=='GET')return send(res,405,{ok:false,error:'Método no disponible.'});
      if(pathname==='/api/setup'){return send(res,200,{ok:true,configured:!!store.data.snapshot,canPair:loopback(req),webUrl:store.data.connection?.url||null,pending:store.data.sales.filter(s=>!s.synced).length,users:store.data.snapshot?.users.map(u=>({id:u.id,name:u.name}))||[]});}
      if(pathname==='/api/state'){
        const s=auth(req);const snap=jsonClone(store.data.snapshot);delete snap.users;
        return send(res,200,{ok:true,snapshot:snap,stocks:store.data.stocks,user:s.user,register:s.register,csrf:s.csrf,shift:store.data.shifts.find(x=>x.user_id===s.user.id&&x.register===s.register&&!x.closed_at)||null,sales:store.data.sales.filter(x=>x.user_id===s.user.id&&x.register===s.register).slice(-100).reverse().map(x=>({id:x.id,created_at:x.created_at,total:x.quote.total,synced:x.synced,error:x.error,conflicts:x.conflicts,quote:x.quote,change:x.change})),pending:store.data.sales.filter(s=>!s.synced).length,lastSync:store.data.lastSync,syncMessage:store.data.syncMessage,addresses:interfaces.map(ip=>'http://'+ip+':'+port),canManage:loopback(req)});
      }
      if(pathname==='/conexion.fisitaap'){
        auth(req);if(!loopback(req))throw new Error('Descarga la conexión desde el equipo central.');const address=interfaces[0];if(!address)throw new Error('Conecta el equipo al router.');return send(res,200,{url:'http://'+address+':'+port,webUrl:store.data.connection?.url||null},{'Content-Disposition':'attachment; filename="Conexion-equipo-central.fisitaap"'});
      }
      const files={'/':'index.html','/app.js':'app.js','/style.css':'style.css','/print-settings.html':'print-settings.html','/print-settings.js':'print-settings.js','/desktop-shell.html':'desktop-shell.html','/desktop-shell.js':'desktop-shell.js','/desktop-shell.css':'desktop-shell.css'};const filename=files[pathname];if(!filename)return send(res,404,{ok:false,error:'Página no disponible.'});
      res.writeHead(200,{'Content-Type':filename.endsWith('.html')?'text/html; charset=utf-8':filename.endsWith('.js')?'text/javascript; charset=utf-8':'text/css; charset=utf-8','Cache-Control':'no-store','Content-Security-Policy':"default-src 'self'; script-src 'self'; style-src 'self'; connect-src 'self'; frame-ancestors 'none'",'X-Content-Type-Options':'nosniff'});res.end(fs.readFileSync(path.join(__dirname,'public',filename)));
    }catch(error){send(res,error.status||422,{ok:false,error:error.message,...(saleRequestId?{not_saved:!store.data.sales.some(s=>s.id===saleRequestId)}:{})});}
  };
  const server=http.createServer(handler);await new Promise((resolve,reject)=>{server.once('error',reject);server.listen(port,options.host||'0.0.0.0',resolve);});port=server.address().port;
  if(options.autoSync!==false){timer=setInterval(()=>void sync(),60000);timer.unref();void sync();}
  return {url:'http://127.0.0.1:'+port,port,store,sync,close:()=>{clearInterval(timer);server.close();},server};
}
module.exports={createLocalServer,LocalStore,quote};
