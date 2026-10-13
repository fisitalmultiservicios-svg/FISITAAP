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
// Live register metadata is committed with the journal, independently of catalog prices.
function adoptCashState(next,state){
  if(!state||state.version!==180||state.tenant_id!==next.snapshot?.tenant.id||state.branch_id!==next.snapshot?.branch.id||!Array.isArray(state.shifts)||!Array.isArray(state.acknowledged))throw new Error('La web devolvió una caja incompatible. Se conservaron las operaciones.');
  const previous=next.snapshot.cash_state;
  if(previous?.device_id&&previous.device_id!==state.device_id)throw new Error('La autorización pertenece a otro equipo.');
  for(const ack of state.acknowledged){const sale=next.sales.find(x=>x.id===ack.id);if(sale){sale.synced=true;sale.webSale=integer(ack.sale_id,1);sale.error=null;}}
  for(const remote of state.shifts){
    uuid(remote.id);integer(remote.opening_cash);integer(remote.confirmed_max);integer(remote.server_cash_net,-99999999999);integer(remote.user_id,1);
    let shift=next.shifts.find(x=>x.id===remote.id);
    if(!shift){shift={...remote,snapshot_id:next.snapshot.id,closed_at:null,syncedClosed:false,adopted:true};next.shifts.push(shift);}
    else if(shift.user_id!==remote.user_id||shift.opening_cash!==remote.opening_cash)throw new Error('El turno cambió de identidad. Se conservó para revisión.');
    Object.assign(shift,{server_id:remote.server_id,server_cash_net:remote.server_cash_net,confirmed_max:remote.confirmed_max,pending_accounts:remote.pending_accounts||0,server_closed:false});
  }
  for(const shift of next.shifts)if(shift.server_id&&!shift.closed_at&&!state.shifts.some(x=>x.id===shift.id)){
    if(next.sales.some(x=>x.shift_id===shift.id&&!x.synced))shift.server_closed=true;
    else{shift.closed_at=new Date().toISOString();shift.syncedClosed=true;shift.closedExternally=true;}
  }
  next.snapshot.cash_state=state;next.authorityCheckedAt=new Date().toISOString();
}
function expectedCash(state,shift){
  return shift.opening_cash+(shift.server_cash_net||0)+state.sales.filter(x=>x.shift_id===shift.id&&!(x.webSale&&x.webSale<=(shift.confirmed_max||0))).reduce((sum,x)=>sum+x.cashApplied,0);
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
  const nativeSecret=crypto.randomBytes(32).toString('hex');let nativeCookie='';
  const sessions=new Map(),attempts=new Map();let lastSyncAttempt=0,busy=false,pairing=false,syncFlight=null,authorityTail=Promise.resolve(),authorityBusy=false,printBusy=false,printHandler=options.print,timer,port=options.port??18766;
  const interfaces=Object.values(os.networkInterfaces()).flat().filter(x=>x&&x.family==='IPv4'&&!x.internal).map(x=>x.address);
  const allowedHosts=new Set(['localhost','127.0.0.1','[::1]',...interfaces]);
  const loopback=req=>['127.0.0.1','::1','::ffff:127.0.0.1'].includes(req.socket.remoteAddress);
  const primaryAllowed=()=>[179,180].includes(store.data.snapshot?.offline_policy?.version) && store.data.snapshot.offline_policy.allowed===true && store.data.snapshot.offline_policy.generation>0 && !store.data.releasePending && store.data.snapshot.offline_policy.generation>(store.data.releaseBlockedGeneration??-1);
  const requirePrimary=req=>{if(!loopback(req)||!primaryAllowed())throw Object.assign(new Error('Solo el equipo principal autorizado puede vender en Caja local. Las cajas secundarias usan la web con internet.'),{status:403});};
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
    if(!response.ok||!out.ok)throw Object.assign(new Error(typeof out.error==='string'?out.error.slice(0,500):'No se pudo conectar con la web.'),{status:response.status});
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
  const runSync=async(settings={})=>{
    if(busy||authorityBusy||pairing||!store.data.connection)return;busy=true;
    try{
      const uncertain=(store.data.webPayments||[]).filter(x=>settings.resolveWeb===true||Date.parse(x.created_at)<Date.now()-90000);if(uncertain.length){const checked=await remote('payment-status',{payments:uncertain.slice(0,100)});const updated=jsonClone(store.data);updated.webPayments=updated.webPayments.filter(x=>!checked.payments.some(y=>y.key===x.key));store.commit(updated);}
      const pending=store.data.sales.filter(s=>!s.synced).slice(0,100);
      const pendingShifts=new Set(pending.map(s=>s.shift_id));
      const shifts=store.data.shifts.filter(s=>!s.syncedClosed||pendingShifts.has(s.id)).sort((a,b)=>Number(pendingShifts.has(b.id))-Number(pendingShifts.has(a.id))).slice(0,100).map(s=>{
        const payload={id:s.id,user_id:s.user_id,snapshot_id:s.snapshot_id,register:s.register,opening_cash:s.opening_cash,opened_at:s.opened_at};
        if(s.closed_at&&!store.data.sales.some(p=>p.shift_id===s.id&&!p.synced&&!pending.includes(p))){payload.closed_at=s.closed_at;payload.closing_cash=s.closing_cash;if(s.denominations){payload.denominations=s.denominations;payload.other_cash=s.other_cash||0;}}return payload;
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
        next.snapshot=snap;next.stocks=stocks;if(snap.cash_state)adoptCashState(next,snap.cash_state);store.commit(next);
      }
    }catch(error){const next=jsonClone(store.data);if([401,403].includes(error.status)&&next.snapshot?.offline_policy)next.snapshot.offline_policy.allowed=false;next.syncMessage='Sin conexión con la web: '+error.message+'. Las ventas siguen guardadas aquí.';store.commit(next);}
    finally{busy=false;}
  };
  const sync=(settings={})=>{if(syncFlight)return syncFlight;syncFlight=runSync(settings).finally(()=>{syncFlight=null;});return syncFlight;};
  const flushPrint=async()=>{
    if(printBusy||!printHandler||!store.data.connection)return {ok:true,waiting:true};printBusy=true;
    try{
      const connection=store.data.connection,same=()=>store.data.connection?.url===connection.url&&store.data.connection?.token===connection.token;
      for(let n=0;n<10;n++){
        let ack=store.data.printAcks?.[0];
        if(!ack){const out=await remote('print-claim',{});if(!same())throw new Error('La conexión de impresión cambió.');if(!out.job)break;
          let result;try{result=await printHandler(out.job.payload);}catch(error){result={status:'uncertain',error:error.message};}
          ack={id:out.job.id,claim:out.job.claim,status:result.status,error:result.error||''};const next=jsonClone(store.data);next.printAcks=[...(next.printAcks||[]),ack];store.commit(next);
        }
        await remote('print-ack',ack);if(!same())throw new Error('La conexión de impresión cambió.');const next=jsonClone(store.data);next.printAcks=next.printAcks.filter(x=>x.id!==ack.id||x.claim!==ack.claim);next.printMessage=ack.status==='uncertain'?'Revisa la impresora antes de reimprimir una copia.':'Impresión enviada al equipo configurado.';store.commit(next);
      }
      return {ok:true,message:store.data.printMessage||'Sin impresiones pendientes.'};
    }catch(error){const next=jsonClone(store.data);next.printMessage=error.message;store.commit(next);return {ok:false,error:error.message};}finally{printBusy=false;}
  };
  const handler=async(req,res)=>{
    let saleRequestId=null;try{
      const host=String(req.headers.host||'').split(':')[0];if(!allowedHosts.has(host))return send(res,403,{ok:false,error:'Dirección local no permitida.'});
      const base='http://'+req.headers.host;const parsed=new URL(req.url,base);const pathname=parsed.pathname;
      if(options.requireNative&&((pathname.startsWith('/api/')&&!(pathname==='/api/setup'&&req.method==='GET'))||pathname==='/conexion.fisitaap')){
        const supplied=String(req.headers['x-fisitaap-local-app']||'');if(supplied.length!==nativeSecret.length||!crypto.timingSafeEqual(Buffer.from(supplied),Buffer.from(nativeSecret)))return send(res,403,{ok:false,error:'La caja local se opera únicamente desde FISITAAP Windows autorizado.'});
      }
      if(req.method==='POST'){
        if(req.headers.origin!==base||!String(req.headers['content-type']||'').startsWith('application/json'))return send(res,403,{ok:false,error:'Solicitud de otro sitio bloqueada.'});
        const data=await body(req);
        if(pathname==='/api/pair'){
          if(!loopback(req))return send(res,403,{ok:false,error:'Conecta el negocio desde el equipo central.'});
          if(pairing||busy)throw new Error('Hay una conexión o sincronización en curso. Espera a que termine.');
          if(store.data.connection&&primaryAllowed())throw new Error('Libera primero la función principal antes de cambiar la conexión.');
          if(printBusy||store.data.printAcks?.length)throw new Error('Espera y sincroniza los resultados de impresión antes de cambiar de negocio.');
          if(store.data.sales.some(s=>!s.synced))throw new Error('Sincroniza las ventas pendientes antes de cambiar la conexión.');
          if(store.data.shifts.some(s=>!s.syncedClosed))throw new Error('Cierra y sincroniza los turnos locales antes de cambiar la conexión.');
          pairing=true;try{
          const u=new URL(data.url);if(u.protocol!=='https:'&&!(options.allowTestHttp&&u.protocol==='http:'))throw new Error('La web debe usar https://.');if(u.username||u.password||u.search||u.hash)throw new Error('Usa la dirección principal de la aplicación.');
          const endpoint=u.origin+u.pathname.replace(/\/$/,'');
          let r;try{r=await(options.fetch||fetch)(endpoint+'/api/desktop/pair',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({device_id:Number(data.device_id),code:String(data.code||'')}),signal:AbortSignal.timeout(60000)});}catch(error){if(error?.name==='TimeoutError')throw new Error('La web tardó demasiado en conectar. Se conservan los datos del equipo. Revisa la conexión antes de intentar otra vez.');throw error;}const out=await webResponse(r,endpoint,'pair');
          if(store.data.sales.some(s=>!s.synced)||store.data.shifts.some(s=>!s.syncedClosed))throw new Error('Se abrió un turno o se guardó una venta mientras conectabas. Se conserva la conexión anterior. Cierra y sincroniza primero.');
          const next=jsonClone(store.data);next.connection={url:endpoint,token:codec.encode(out.token)};next.snapshot=out.snapshot;next.stocks={};for(const p of out.snapshot.products){next.stocks[p.id]=p.stock;for(const c of p.components)next.stocks[c.id]=c.stock;}next.syncMessage='Equipo conectado. Ya puedes abrir un turno.';if(out.snapshot.cash_state)adoptCashState(next,out.snapshot.cash_state);store.commit(next);return send(res,200,{ok:true});
          }finally{pairing=false;}
        }
        if(pathname==='/api/resume'){
          requirePrimary(req);const user=store.data.snapshot?.users.find(x=>x.id===store.data.authorizedWebUser);
          if(!user||user.password_hash!==store.data.authorizedWebHash||Date.parse(store.data.authorizedWebAt||'')<Date.now()-12*60*60*1000||!store.data.authorizedWebAt)throw Object.assign(new Error('Inicia sesión con tu usuario autorizado.'),{status:401});
          const register=store.data.snapshot.cash_state?.register?.name;if(!register)throw new Error('La caja no está asignada.');
          const sid=crypto.randomBytes(32).toString('hex'),csrf=crypto.randomBytes(32).toString('hex');sessions.set(sid,{user:{id:user.id,name:user.name},hash:user.password_hash,register,csrf,expires:Date.now()+12*60*60*1000});
          return send(res,200,{ok:true,csrf},{'Set-Cookie':'fisitaap_local='+sid+'; HttpOnly; SameSite=Strict; Path=/'});
        }
        if(pathname==='/api/login'){
          if(!loopback(req))throw Object.assign(new Error('Esta caja es secundaria. Usa Sistema completo con internet; la caja local pertenece solo al principal.'),{status:403});
          const key=req.socket.remoteAddress;const times=(attempts.get(key)||[]).filter(t=>t>Date.now()-300000);if(times.length>=10)throw Object.assign(new Error('Espera 5 minutos antes de intentar otra vez.'),{status:429});times.push(Date.now());attempts.set(key,times);
          const user=store.data.snapshot?.users.find(u=>u.id===Number(data.user_id));if(!user||!bcrypt.compareSync(String(data.password||''),user.password_hash.replace(/^\$2y\$/,'$2b$')))throw new Error('Acceso local incorrecto.');
          const register=store.data.snapshot.offline_policy?.version===180?String(store.data.snapshot.cash_state?.register?.name||''):String(data.register||'').trim().slice(0,80);if(!register)throw new Error('Escribe el nombre de esta caja.');
          const sid=crypto.randomBytes(32).toString('hex'),csrf=crypto.randomBytes(32).toString('hex');sessions.set(sid,{user:{id:user.id,name:user.name},hash:user.password_hash,register,csrf,expires:Date.now()+12*60*60*1000});attempts.delete(key);
          return send(res,200,{ok:true,csrf},{'Set-Cookie':'fisitaap_local='+sid+'; HttpOnly; SameSite=Strict; Path=/'});
        }
        const session=auth(req,true);
        if(pathname==='/api/primary/release'){
          if(!loopback(req))throw new Error('Libera la función desde el equipo principal.');
          if(busy||pairing)throw new Error('Espera a que termine la sincronización.');
          if(store.data.sales.some(s=>!s.synced)||store.data.shifts.some(s=>!s.syncedClosed))throw new Error('Cierra y sincroniza todos los turnos y ventas antes de liberar el equipo.');
          busy=true;
          try {
            const next=jsonClone(store.data);next.releasePending=true;next.releaseBlockedGeneration=next.snapshot?.offline_policy?.generation??0;store.commit(next);
            await remote('release',{});
            const done=jsonClone(store.data);done.releasePending=false;if(done.snapshot.offline_policy)done.snapshot.offline_policy.allowed=false;store.commit(done);
            return send(res,200,{ok:true});
          }finally{busy=false;}
        }
        if(pathname==='/api/logout'){const next=jsonClone(store.data);next.authorizedWebUser=null;next.authorizedWebAt=null;store.commit(next);for(const [id,s]of sessions)if(s===session)sessions.delete(id);return send(res,200,{ok:true},{'Set-Cookie':'fisitaap_local=; Max-Age=0; HttpOnly; SameSite=Strict; Path=/'});}
        if(pathname==='/api/shift/open'){
          requirePrimary(req);
          const next=jsonClone(store.data);if(next.shifts.some(s=>!s.closed_at&&(next.snapshot.offline_policy?.version===180||s.register===session.register||s.user_id===session.user.id)))throw new Error('Ya hay un turno abierto para esta caja o cajero.');
          next.shifts.push({id:crypto.randomUUID(),user_id:session.user.id,register:session.register,snapshot_id:next.snapshot.id,opening_cash:integer(data.opening_cash),opened_at:new Date().toISOString(),closed_at:null,syncedClosed:false});store.commit(next);return send(res,200,{ok:true});
        }
        if(pathname==='/api/shift/close'){
          const next=jsonClone(store.data);if(next.webPayments?.length)throw new Error('Hay un cobro web sin confirmar. Recupera la conexión y sincroniza antes de cerrar.');const shift=next.shifts.find(s=>s.user_id===session.user.id&&s.register===session.register&&!s.closed_at);if(!shift)throw new Error('No tienes un turno abierto.');shift.closed_at=new Date().toISOString();shift.closing_cash=integer(data.closing_cash);if(shift.server_closed)throw new Error('El turno se cerró en la web. Sincroniza las operaciones para revisión.');if(shift.pending_accounts)throw new Error('Cierra las cuentas abiertas en la web antes de cerrar el turno.');shift.expected_cash=expectedCash(next,shift);if(data.denominations){const coins=[5,10,25,50,100,500,1000,2000,5000,10000,20000];let counted=integer(data.other_cash||0);for(const value of coins)counted+=value*100*integer(data.denominations[value]||0,0,100000);if(counted!==shift.closing_cash)throw new Error('El conteo de billetes y monedas no coincide.');shift.denominations=data.denominations;shift.other_cash=data.other_cash||0;}shift.difference=shift.closing_cash-shift.expected_cash;store.commit(next);return send(res,200,{ok:true,shift});
        }
        if(pathname==='/api/sales'){
          const id=uuid(data.id);saleRequestId=id;const next=jsonClone(store.data),old=next.sales.find(s=>s.id===id);
          const fingerprint=crypto.createHash('sha256').update(JSON.stringify({user:session.user.id,register:session.register,items:data.items,payments:data.payments,customer:data.customer||{},...(data.discount?{discount:data.discount}:{}),...(data.note?{note:String(data.note).slice(0,1000)}:{})})).digest('hex');
          if(old){if(old.fingerprint!==fingerprint)throw new Error('El identificador ya corresponde a otra venta.');return send(res,200,{ok:true,sale:{id:old.id,quote:old.quote,change:old.change,synced:old.synced}});}
          requirePrimary(req);
          const shift=next.shifts.find(s=>s.user_id===session.user.id&&s.register===session.register&&!s.closed_at);if(!shift)throw new Error('Abre tu turno antes de cobrar.');if(shift.server_closed)throw new Error('El turno fue cerrado en la web. Sincroniza para revisar las ventas.');
          const createdAt=new Date().toISOString();const calculated=quote(next.snapshot,data.items,Date.parse(createdAt),data.discount||0);if(data.expected_total!==undefined&&data.expected_total!==calculated.total)throw new Error('El precio o la promoción cambió. Actualiza la caja y revisa el total antes de cobrar.');for(const [pid,qty]of Object.entries(calculated.stock)){const p=next.snapshot.products.find(p=>p.id===Number(pid))||next.snapshot.products.flatMap(p=>p.components).find(p=>p.id===Number(pid));if((next.stocks[pid]||0)-qty<0&&!p?.allow_negative)throw new Error('Existencia insuficiente: '+(p?.name||pid));}
          const payments={cash:integer(data.payments?.cash||0),card:integer(data.payments?.card||0),sinpe:integer(data.payments?.sinpe||0)};const cashApplied=calculated.total-payments.card-payments.sinpe;if(cashApplied<0||payments.cash<cashApplied)throw new Error('Falta completar el pago o los montos superan el total.');
          const customer=data.customer||{};if(customer.id&&!next.snapshot.customers.some(c=>Number(c.id)===Number(customer.id)))throw new Error('Cliente no disponible.');if(!customer.id&&(customer.name||customer.phone)&&(!String(customer.name||'').trim()||!/^(506)?\d{8}$/.test(String(customer.phone||'').replace(/\D/g,''))))throw new Error('Escribe nombre y teléfono de 8 dígitos para el nuevo cliente.');
          const payload={id,snapshot_id:next.snapshot.id,user_id:session.user.id,register:session.register,shift_id:shift.id,created_at:createdAt,items:data.items,payments,customer,...(data.discount?{discount:data.discount}:{}),...(data.note?{note:String(data.note).slice(0,1000)}:{})};
          const sale={id,fingerprint,payload,user_id:session.user.id,register:session.register,shift_id:shift.id,created_at:payload.created_at,stock:calculated.stock,quote:calculated,change:payments.cash-cashApplied,cashApplied,synced:false};
          for(const [pid,qty]of Object.entries(calculated.stock))next.stocks[pid]=(next.stocks[pid]||0)-qty;next.sales.push(sale);store.commit(next);
          return send(res,200,{ok:true,sale:{id,quote:calculated,change:sale.change,synced:false}});
        }
        if(pathname==='/api/sync'){void sync({resolveWeb:true});return send(res,200,{ok:true});}
        if(pathname==='/api/backup'){if(!loopback(req))throw new Error('Guarda el respaldo desde el equipo central.');const file=store.backup();return send(res,200,{ok:true,file:path.basename(file)});}
        throw Object.assign(new Error('Acción no disponible.'),{status:404});
      }
      if(req.method!=='GET')return send(res,405,{ok:false,error:'Método no disponible.'});
      if(pathname==='/api/setup'){return send(res,200,{ok:true,configured:!!store.data.snapshot,canPair:loopback(req),primary:primaryAllowed()&&loopback(req),secondary:!loopback(req),webUrl:store.data.connection?.url||null,pending:store.data.sales.filter(s=>!s.synced).length,register:store.data.snapshot?.cash_state?.register?.name||null,users:store.data.snapshot?.users.map(u=>({id:u.id,name:u.name}))||[]});}
      if(pathname==='/api/state'){
        const s=auth(req);const snap=jsonClone(store.data.snapshot);delete snap.users;
        if(snap.offline_policy)snap.offline_policy.allowed=primaryAllowed()&&loopback(req);
        return send(res,200,{ok:true,snapshot:snap,stocks:store.data.stocks,user:s.user,register:s.register,csrf:s.csrf,shift:(()=>{const shift=store.data.shifts.find(x=>x.user_id===s.user.id&&x.register===s.register&&!x.closed_at);return shift?{...shift,expected_cash:expectedCash(store.data,shift)}:null;})(),sales:store.data.sales.filter(x=>x.user_id===s.user.id&&x.register===s.register).slice(-100).reverse().map(x=>({id:x.id,created_at:x.created_at,total:x.quote.total,synced:x.synced,error:x.error,conflicts:x.conflicts,quote:x.quote,change:x.change})),pending:store.data.sales.filter(s=>!s.synced).length,webPayments:(store.data.webPayments||[]).length,lastSync:store.data.lastSync,syncMessage:store.data.syncMessage,addresses:interfaces.map(ip=>'http://'+ip+':'+port),canManage:loopback(req)});
      }
      if(pathname==='/conexion.fisitaap'){
        auth(req);if(!loopback(req))throw new Error('Descarga la conexión desde el equipo central.');const address=interfaces[0];if(!address)throw new Error('Conecta el equipo al router.');return send(res,200,{url:'http://'+address+':'+port,webUrl:store.data.connection?.url||null},{'Content-Disposition':'attachment; filename="Conexion-equipo-central.fisitaap"'});
      }
      const files={'/':'index.html','/app.js':'app.js','/style.css':'style.css','/print-settings.html':'print-settings.html','/print-settings.js':'print-settings.js','/desktop-shell.html':'desktop-shell.html','/desktop-shell.js':'desktop-shell.js','/desktop-shell.css':'desktop-shell.css'};const filename=files[pathname];if(!filename)return send(res,404,{ok:false,error:'Página no disponible.'});
      res.writeHead(200,{'Content-Type':filename.endsWith('.html')?'text/html; charset=utf-8':filename.endsWith('.js')?'text/javascript; charset=utf-8':'text/css; charset=utf-8','Cache-Control':'no-store','Content-Security-Policy':"default-src 'self'; script-src 'self'; style-src 'self'; connect-src 'self'; frame-ancestors 'none'",'X-Content-Type-Options':'nosniff'});res.end(fs.readFileSync(path.join(__dirname,'public',filename)));
    }catch(error){send(res,error.status||422,{ok:false,error:error.message,...(saleRequestId?{not_saved:!store.data.sales.some(s=>s.id===saleRequestId)}:{})});}
  };
  const server=http.createServer(handler);await new Promise((resolve,reject)=>{server.once('error',reject);server.listen(port,options.host||'0.0.0.0',resolve);});port=server.address().port;
  if(options.autoSync!==false){timer=setInterval(()=>{if(Date.now()-lastSyncAttempt>=60000){lastSyncAttempt=Date.now();void sync();}void flushPrint();},10000);timer.unref();void sync();}
  const trackWebPayment=data=>{
    if(!data||!/^[a-f0-9]{32}$/.test(data.key||'')||data.user_id!==store.data.authorizedWebUser)throw new Error('Confirma primero el acceso de este cajero en la web.');const next=jsonClone(store.data);next.webPayments=next.webPayments||[];if(!next.webPayments.some(x=>x.key===data.key))next.webPayments.push({key:data.key,user_id:data.user_id,created_at:new Date().toISOString()});store.commit(next);return {ok:true};
  };
  const authorize=data=>{
    if(data?.logout===true){const next=jsonClone(store.data);next.authorizedWebUser=null;next.authorizedWebAt=null;store.commit(next);sessions.clear();nativeCookie='';return Promise.resolve({ok:true});}
    if(!data||!/^[a-f0-9]{64}$/.test(data.nonce||'')||!Number.isSafeInteger(data.user_id)||data.user_id<1)throw new Error('Autorización de caja inválida.');
    const run=async()=>{
      if(syncFlight)await syncFlight;authorityBusy=true;
      try{
        const connection=store.data.connection,previousUser=store.data.authorizedWebUser;
        const pending=store.data.sales.filter(x=>!x.synced||!(x.webSale&&x.webSale<=(store.data.shifts.find(s=>s.id===x.shift_id)?.confirmed_max||0))).map(x=>x.id).slice(-1000);
        const out=await remote('web-session',{nonce:data.nonce,user_id:data.user_id,...(data.confirm===true?{confirm:true}:{}),...(pending.length?{pending}:{})});
        if(store.data.connection?.url!==connection?.url||store.data.connection?.token!==connection?.token)throw new Error('La conexión cambió mientras se comprobaba la caja.');
        if(out.cash_state){const next=jsonClone(store.data);adoptCashState(next,out.cash_state);if(out.offline_policy)next.snapshot.offline_policy=out.offline_policy;if(out.bound){next.authorizedWebUser=out.cash?data.user_id:null;next.authorizedWebAt=new Date().toISOString();next.authorizedWebHash=next.snapshot.users.find(x=>x.id===data.user_id)?.password_hash||null;}store.commit(next);}
        if(previousUser!==data.user_id){sessions.clear();nativeCookie='';}
        return {ok:out.ok,proof:out.proof,cash:out.cash};
      }finally{authorityBusy=false;}
    };
    const result=authorityTail.then(run,run);authorityTail=result.catch(()=>{});return result;
  };
  const localRequest=async value=>{
    const route=String(value?.path||''),method=value?.data===undefined?'GET':'POST';
    if(!/^\/api\/[a-z/-]+$/.test(route))throw new Error('Ruta de caja no disponible.');
    const base='http://127.0.0.1:'+port;
    const response=await fetch(base+route,{method,headers:{'x-fisitaap-local-app':nativeSecret,Cookie:nativeCookie,...(method==='POST'?{Origin:base,'Content-Type':'application/json','X-CSRF-Token':String(value.csrf||'')}: {})},body:method==='POST'?JSON.stringify(value.data):undefined,signal:AbortSignal.timeout(65000)});
    const cookie=response.headers.get('set-cookie');if(cookie)nativeCookie=cookie.split(';')[0];
    return {status:response.status,body:await response.json()};
  };
  return {trackWebPayment,flushPrint,setPrinter:handler=>{printHandler=handler;},url:'http://127.0.0.1:'+port,port,store,sync,authorize,localRequest,isPrincipal:primaryAllowed,close:()=>{clearInterval(timer);server.close();},server};
}
module.exports={createLocalServer,LocalStore,quote,adoptCashState,expectedCash};
