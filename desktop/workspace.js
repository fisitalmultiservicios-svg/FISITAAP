'use strict';

const BAR_HEIGHT=86;
const WEB_PERMISSIONS=['media','geolocation','notifications','clipboard-sanitized-write','local-network','local-network-access','loopback-network','web-printing','fullscreen'];
function webBase(value) {
  const url=new URL(value);
  if(url.protocol!=='https:'||url.username||url.password||url.search||url.hash)throw new Error('Usa la dirección https:// de tu aplicación web.');
  return url.origin+url.pathname.replace(/\/+$/,'');
}
class DesktopWorkspace {
  constructor({electron,service,localURL=service.url,cachedWebURL=null,onWebURL=()=>{},fetch:request=fetch,monitorMs=10000}) {
    this.electron=electron;this.service=service;this.localURL=localURL;this.request=request;this.onWebURL=onWebURL;
    this.state={mode:'local',available:false,configured:false,centralReady:true,pending:0,webUrl:cachedWebURL?webBase(cachedWebURL):null,message:'Comprobando la conexión…'};
    this.closed=false;this.refreshing=null;this.webLoaded=false;this.webFailed=false;this.notice='';this.connectionError='';this.action=0;this.generation=0;
    const prefs={nodeIntegration:false,contextIsolation:true,sandbox:true};
    this.win=new electron.BrowserWindow({width:1360,height:950,minWidth:900,minHeight:700,webPreferences:{...prefs,preload:require('node:path').join(__dirname,'preload.js')}});
    this.localView=new electron.WebContentsView({webPreferences:{...prefs,preload:require('node:path').join(__dirname,'preload.js')}});
    // The live web has a persistent session and a restricted printing-only preload.
    this.webView=new electron.WebContentsView({webPreferences:{...prefs,partition:'persist:fisitaap-web',preload:require('node:path').join(__dirname,'web-preload.js')}});
    this.win.webContents.setWindowOpenHandler(()=>({action:'deny'}));
    this.win.webContents.on('will-navigate',(event,url)=>{if(url!==service.url+'/desktop-shell.html')event.preventDefault();});
    this.win.webContents.session.setPermissionRequestHandler((_web,_permission,callback)=>callback(false));
    this.localView.webContents.setWindowOpenHandler(()=>({action:'deny'}));
    this.localView.webContents.on('will-navigate',(event,url)=>{if(new URL(url).origin!==new URL(this.localURL).origin)event.preventDefault();});
    this.localView.webContents.on('will-redirect',(event,url)=>{if(new URL(url).origin!==new URL(this.localURL).origin)event.preventDefault();});
    const webAllowed=url=>{try{return !!this.state.webUrl&&new URL(url).origin===new URL(this.state.webUrl).origin;}catch{return false;}};
    const external=url=>{try{if(new URL(url).protocol==='https:')Promise.resolve(electron.shell.openExternal(url)).catch(()=>{});}catch{}};
    const protect=contents=>{
      const guard=(event,url)=>{if(!webAllowed(url)){event.preventDefault();external(url);}};
      contents.on('will-navigate',guard);contents.on('will-redirect',guard);
      contents.setWindowOpenHandler(({url})=>{
        if(url==='about:blank'||webAllowed(url))return {action:'allow',overrideBrowserWindowOptions:{webPreferences:{...prefs,partition:'persist:fisitaap-web',preload:require('node:path').join(__dirname,'web-preload.js')}}};
        external(url);return {action:'deny'};
      });
      contents.on('did-create-window',window=>protect(window.webContents));
    };
    protect(this.webView.webContents);
    this.webView.webContents.session.setPermissionRequestHandler((_contents,permission,callback,details)=>{
      callback(webAllowed(details?.requestingUrl)&&WEB_PERMISSIONS.includes(permission));
    });
    this.webView.webContents.session.setPermissionCheckHandler((_contents,permission,origin)=>webAllowed(origin)&&WEB_PERMISSIONS.includes(permission));
    this.webView.webContents.on('did-navigate',(_event,url,status)=>{
      if(!webAllowed(url)||this.closed)return;
      if(status<400){this.webLoaded=true;this.webFailed=false;}
      else{this.webFailed=true;this.notice='La web respondió con un error. Puedes usar Caja local y volver a intentar Sistema completo.';if(!this.webLoaded)this.show('local');else this.publish();}
    });
    this.webView.webContents.on('did-fail-load',(_event,code,_description,_url,mainFrame)=>{
      if(code===-3||mainFrame===false||this.closed)return;
      this.webFailed=true;this.state.available=false;
      this.notice='La web no pudo abrir. Puedes usar Caja local. Si estabas cobrando en la web, revisa esa venta antes de volver a cobrar.';
      if(!this.webLoaded)this.show('local');else this.publish();
    });
    this.win.on('resize',()=>this.resize());
    this.win.on('closed',()=>this.close());
    this.show('local');
    Promise.resolve(this.win.loadURL(service.url+'/desktop-shell.html')).catch(()=>{});
    this.localLoad=Promise.resolve(this.localView.webContents.loadURL(localURL)).catch(()=>{
      this.notice='No se pudo abrir la caja local. Revisa que el equipo central y el router estén encendidos.';this.publish();
    });
    if(monitorMs>0){this.timer=setInterval(()=>void this.refresh(),monitorMs);this.timer.unref();}
  }
  snapshot(){return {...this.state};}
  publish() {
    if(this.closed)return;
    if(this.notice)this.state.message=this.notice;
    else if(!this.state.webUrl)this.state.message='Conecta primero tu negocio en la caja local.';
    else if(!this.state.centralReady)this.state.message='El equipo central no responde. Revisa el equipo y la red del local.';
    else if(!this.state.available)this.state.message=(this.connectionError||'Sin conexión con la web.')+(this.state.mode==='web'?' Revisa el cobro web sin confirmar. Solo el principal puede vender localmente.':this.state.primary?' Usa Caja local: este es el equipo principal.':'Solo el principal puede vender sin internet; esta caja usa la web con conexión.');
    else if(this.state.pending>0)this.state.message='Hay '+this.state.pending+' venta(s) local(es) pendientes de sincronizar.';
    else if(this.webFailed)this.state.message='La web vuelve a estar disponible. Pulsa Sistema completo para abrirla de nuevo.';
    else this.state.message=this.state.mode==='web'?'Sistema web completo. Usa tu cuenta y tus permisos habituales.':'La web está disponible. Puedes volver a Sistema completo cuando termines tu venta local.';
    this.win.setTitle('FISITAAP · '+(this.state.mode==='web'?'Sistema completo':'Caja local'));
    this.win.webContents.send('fisitaap:workspace-status',this.snapshot());
  }
  resize() {
    if(this.closed)return;
    const [width,height]=this.win.getContentSize(),bounds={x:0,y:BAR_HEIGHT,width,height:Math.max(1,height-BAR_HEIGHT)};
    this.localView.setBounds(bounds);this.webView.setBounds(bounds);
  }
  show(mode) {
    if(this.closed)return;
    const view=mode==='web'?this.webView:this.localView;
    if(this.activeView!==view){if(this.activeView)this.win.contentView.removeChildView(this.activeView);this.win.contentView.addChildView(view);this.activeView=view;}
    this.state.mode=mode;this.resize();this.publish();
  }
  async setup() {
    if(this.localURL===this.service.url)return {configured:!!this.service.store.data.snapshot,primary:this.service.isPrincipal?.()===true,webUrl:this.service.store.data.connection?.url||null,pending:this.service.store.data.sales.filter(s=>!s.synced).length};
    const response=await this.request(this.localURL+'/api/setup',{signal:AbortSignal.timeout(5000)}),out=await response.json();
    if(!response.ok||!out.ok)throw new Error('El equipo central no responde.');
    return out;
  }
  refresh() {
    if(this.closed)return Promise.resolve(this.snapshot());
    if(this.refreshing)return this.refreshing;
    this.refreshing=(async()=>{
      const generation=this.generation,wasAvailable=this.state.available;
      try{
        const info=await this.setup();if(generation!==this.generation)return this.snapshot();this.state.primary=this.localURL===this.service.url&&info.primary===true;this.state.centralReady=true;this.state.configured=!!info.configured;this.state.pending=Number.isSafeInteger(info.pending)?info.pending:0;
        if(info.webUrl){const url=webBase(info.webUrl);if(url!==this.state.webUrl){this.state.webUrl=url;this.webLoaded=false;this.webFailed=false;this.onWebURL(url);}}
      }catch{this.state.centralReady=false;}
      if(this.state.webUrl){
        try{
          const init={method:'GET',headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(12000)};
          const response=await this.request(this.state.webUrl+'/api/desktop/status',init);
          let out;try{out=await response.json();}catch{out=null;}
          let available=response.ok&&out?.ok===true&&out.system==='fisitaap'&&out.desktop_protocol===1;
          // Existing web releases expose only the POST-only pair endpoint.
          if(!available&&[404,405].includes(response.status)){
            const legacy=await this.request(this.state.webUrl+'/api/desktop/pair',{...init,signal:AbortSignal.timeout(12000)});
            let old;try{old=await legacy.json();}catch{old=null;}
            available=legacy.status===405&&old?.ok===false;
          }
          if(generation!==this.generation)return this.snapshot();
          this.state.available=available;
          this.connectionError=available?'':response.status===503?'La actualización de cajas no está activa en la web.':'La web no confirmó la conexión. Revisa la Dirección web en Administración → Windows y cajas sin internet.';
        }catch(error){if(generation!==this.generation)return this.snapshot();this.state.available=false;this.connectionError=error?.name==='TimeoutError'?'La web tardó demasiado en responder.':'Sin conexión con la web. Comprueba internet y la dirección configurada.';}
      }else this.state.available=false;
      if(generation!==this.generation)return this.snapshot();
      if(!wasAvailable&&this.state.available)this.notice='';
      this.publish();return this.snapshot();
    })().finally(()=>{this.refreshing=null;});
    return this.refreshing;
  }
  async start() {
    const action=this.action;
    await this.localLoad;await this.refresh();
    if(this.closed||action!==this.action)return;
    if(this.state.webUrl&&this.state.available)await this.useWeb(true);
  }
  async useWeb(alreadyChecked=false) {
    const action=++this.action;
    this.notice='';if(!alreadyChecked)await this.refresh();
    if(this.closed||action!==this.action)return this.snapshot();
    if(!this.state.webUrl){this.notice='Conecta tu negocio desde la caja local para abrir el sistema completo.';this.publish();return this.snapshot();}
    if(!this.state.available){this.notice='La web no está disponible. Sigue trabajando en Caja local.';this.publish();return this.snapshot();}
    if(this.state.pending>0){
      if(this.localURL===this.service.url){try{await this.service.sync();await this.refresh();}catch{this.notice='No se pudieron sincronizar las ventas. Conserva la caja local abierta y vuelve a intentar.';this.publish();return this.snapshot();}}
      if(this.closed||action!==this.action)return this.snapshot();
      if(this.state.pending>0){this.notice='Primero sincroniza las ventas pendientes desde Caja local. Después puedes volver al sistema completo.';this.publish();return this.snapshot();}
    }
    this.show('web');
    if(!this.webLoaded||this.webFailed){
      try{await this.webView.webContents.loadURL(this.state.webUrl+'/owner-login');}
      catch{if(this.closed||action!==this.action)return this.snapshot();this.webFailed=true;this.notice='No se pudo abrir la web. Puedes seguir usando Caja local.';if(!this.webLoaded)this.show('local');else this.publish();}
    }
    return this.snapshot();
  }
  useLocal(){this.action++;this.notice='';this.show('local');return this.snapshot();}
  async setLocalURL(url,cachedWebURL=null){
    this.generation++;
    this.localURL=url;this.state.configured=false;this.state.pending=0;this.notice='';
    this.state.webUrl=cachedWebURL?webBase(cachedWebURL):null;this.webLoaded=false;this.webFailed=false;
    this.useLocal();
    try{await this.localView.webContents.loadURL(url);}catch{this.notice='No se pudo abrir la caja local. Revisa que el equipo central esté encendido.';}
    await this.refresh();await this.refresh();
  }
  async print(){
    const contents=this.activeView?.webContents;
    if(!contents)return;
    if(typeof contents.executeJavaScript==='function'){
      const receipt=await contents.executeJavaScript("Boolean(document.querySelector('[data-bridge-print],#receiptDialog[open] #printReceipt'))");
      if(receipt){await contents.executeJavaScript("(()=>{const local=document.querySelector('#receiptDialog[open] #printReceipt');if(local)local.click();else window.print();})()");return;}
    }
    contents.print({printBackground:true});
  }
  close(){
    if(this.closed)return;this.closed=true;clearInterval(this.timer);
    this.localView.webContents.close();this.webView.webContents.close();
  }
}
module.exports={DesktopWorkspace,webBase};
