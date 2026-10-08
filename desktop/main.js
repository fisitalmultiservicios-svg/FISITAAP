'use strict';
const {app, BrowserWindow, dialog, safeStorage, Menu, ipcMain} = require('electron');
const path = require('node:path');
const fs = require('node:fs');
const {createLocalServer} = require('./server');
const crypto = require('node:crypto');
const {createPrintBridge,electronPrinter} = require('./print-bridge');
const {DesktopWorkspace,webBase} = require('./workspace');
let service,printService,workspace;
if(!app.requestSingleInstanceLock()) {
  app.quit();
} else {
app.on('second-instance', () => { const w=BrowserWindow.getAllWindows()[0]; if(w){w.restore();w.focus();} });
app.whenReady().then(async () => {
  const dir=app.getPath('userData');
  service=await createLocalServer({dataDir:dir, port:18766, tokenCodec:{
    encode: token => {if(!safeStorage.isEncryptionAvailable())throw new Error('Windows no permite proteger la conexión. Revisa tu sesión de Windows.');return safeStorage.encryptString(token).toString('base64');},
    decode: value => safeStorage.decryptString(Buffer.from(value,'base64'))
  }});
  const printFile=path.join(dir,'impresoras.json');
  let printConfig=fs.existsSync(printFile)?JSON.parse(fs.readFileSync(printFile,'utf8')):{origin:'',printer:{type:'browser',width:80,name:''}};
  if(!printConfig.encryptedToken){if(!safeStorage.isEncryptionAvailable())throw new Error('Windows no permite proteger el código de impresión.');printConfig.encryptedToken=safeStorage.encryptString(crypto.randomBytes(32).toString('hex')).toString('base64');fs.writeFileSync(printFile,JSON.stringify(printConfig),{mode:0o600});}
  const token=()=>safeStorage.decryptString(Buffer.from(printConfig.encryptedToken,'base64'));
  printService=await createPrintBridge({dataDir:dir,allowPortConflict:true,getConfig:()=>({...printConfig,token:token()}),print:electronPrinter(require('electron'))});
  const trustedLocal=event=>{const u=new URL(event.senderFrame.url);if(!event.senderFrame.parent&&localOrigins.has(u.origin))return u;throw new Error('Abre la configuración desde el menú FISITAAP.');};
  const settingsOnly=event=>{const u=trustedLocal(event);if(u.origin!==new URL(service.url).origin||u.pathname!=='/print-settings.html')throw new Error('Abre Configurar impresoras en este equipo.');};
  const localOrigins=new Set([new URL(service.url).origin]);
  ipcMain.handle('fisitaap:printers',async event=>{settingsOnly(event);return event.sender.getPrintersAsync();});
  ipcMain.handle('fisitaap:settings',event=>{settingsOnly(event);return {origin:printConfig.origin,printer:printConfig.printer,token:token(),bridge:printService.status()};});
  ipcMain.handle('fisitaap:retry-print-bridge',event=>{settingsOnly(event);return printService.retry();});
  ipcMain.handle('fisitaap:save-settings',(event,value)=>{settingsOnly(event);const u=new URL(value.origin);if(u.protocol!=='https:'||u.username||u.password)throw new Error('Usa la dirección https:// de FISITAAP.');const width=Number(value.printer?.width);if(![58,80].includes(width))throw new Error('Selecciona papel de 58 u 80 mm.');const name=String(value.printer?.name||'').slice(0,150);printConfig={...printConfig,origin:u.origin,printer:{name,width,type:name?'usb':'browser'}};fs.writeFileSync(printFile,JSON.stringify(printConfig),{mode:0o600});return {ok:true};});
  ipcMain.handle('fisitaap:print',(event,job)=>{
    const frame=event.senderFrame;
    const origin=new URL(frame.url).origin;
    const webOrigin=workspace?.state.webUrl?new URL(workspace.state.webUrl).origin:null;
    if(frame.parent || (!localOrigins.has(origin) && origin!==webOrigin))throw new Error('Sitio de impresión no autorizado.');
    return printService.print(job);
  });
  const modeFile=path.join(dir,'modo.json');
  const clientURL=value=>{const u=new URL(value);if(u.protocol!=='http:'||!/^\d{1,3}(\.\d{1,3}){3}$/.test(u.hostname)||u.port!=='18766'||u.username||u.password||u.hostname.split('.').some(x=>Number(x)>255)||! /^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/.test(u.hostname))throw new Error('El archivo debe señalar un equipo central de la red del local.');return u.origin;};
  let destination=service.url,cachedWebURL=null;
  if(fs.existsSync(modeFile)){try{const saved=JSON.parse(fs.readFileSync(modeFile,'utf8'));destination=clientURL(saved.url);if(saved.webUrl)cachedWebURL=webBase(saved.webUrl);}catch{fs.rmSync(modeFile,{force:true});destination=service.url;}}
  localOrigins.add(new URL(destination).origin);
  workspace=new DesktopWorkspace({electron:require('electron'),service,localURL:destination,cachedWebURL,onWebURL:url=>{
    if(workspace.localURL!==service.url)fs.writeFileSync(modeFile,JSON.stringify({url:workspace.localURL,webUrl:url}),{mode:0o600});
  }});
  const shellOnly=event=>{const u=trustedLocal(event);if(event.sender!==workspace.win.webContents||u.pathname!=='/desktop-shell.html')throw new Error('Usa los botones del programa FISITAAP.');};
  ipcMain.handle('fisitaap:workspace-state',event=>{shellOnly(event);return workspace.snapshot();});
  ipcMain.handle('fisitaap:use-web',event=>{shellOnly(event);return workspace.useWeb();});
  ipcMain.handle('fisitaap:use-local',event=>{shellOnly(event);return workspace.useLocal();});
  Menu.setApplicationMenu(Menu.buildFromTemplate([
    {label:'FISITAAP',submenu:[
      {label:'Sistema completo con internet',click:()=>void workspace.useWeb()},
      {label:'Caja local',click:()=>workspace.useLocal()},
      {type:'separator'},
      {label:'Usar este equipo como central',click:()=>{fs.rmSync(modeFile,{force:true});localOrigins.clear();localOrigins.add(new URL(service.url).origin);void workspace.setLocalURL(service.url);}},
      {label:'Conectar a otro equipo central',click:async()=>{
        const selected=await dialog.showOpenDialog({title:'Selecciona el archivo de conexión que descargaste del equipo central',properties:['openFile'],filters:[{name:'Conexión FISITAAP',extensions:['fisitaap']} ]});
        if(selected.canceled)return;
        try{const data=JSON.parse(fs.readFileSync(selected.filePaths[0],'utf8'));const url=clientURL(data.url),webUrl=data.webUrl?webBase(data.webUrl):null;fs.writeFileSync(modeFile,JSON.stringify({url,webUrl}),{mode:0o600});localOrigins.clear();localOrigins.add(new URL(service.url).origin);localOrigins.add(new URL(url).origin);await workspace.setLocalURL(url,webUrl);}
        catch(error){dialog.showErrorBox('No se pudo conectar',error.message);}
      }},
      {label:'Abrir carpeta de respaldos',click:()=>require('electron').shell.openPath(path.join(dir,'backups'))},
      {type:'separator'},{role:'quit',label:'Salir'}
    ]},
    {label:'Edición',submenu:[{role:'undo'},{role:'redo'},{type:'separator'},{role:'cut'},{role:'copy'},{role:'paste'}]},
    {label:'Vista',submenu:[{label:'Recargar',accelerator:'CmdOrCtrl+R',click:()=>workspace.activeView.webContents.reload()},{label:'Aumentar zoom',click:()=>workspace.activeView.webContents.setZoomLevel(workspace.activeView.webContents.getZoomLevel()+1)},{label:'Reducir zoom',click:()=>workspace.activeView.webContents.setZoomLevel(workspace.activeView.webContents.getZoomLevel()-1)},{label:'Restablecer zoom',click:()=>workspace.activeView.webContents.setZoomLevel(0)}]},
    {label:'Imprimir',submenu:[{label:'Configurar impresoras',click:()=>{const w=new BrowserWindow({width:850,height:700,webPreferences:{nodeIntegration:false,contextIsolation:true,sandbox:true,preload:path.join(__dirname,'preload.js')}});w.webContents.setWindowOpenHandler(()=>({action:'deny'}));w.webContents.on('will-navigate',(e,url)=>{if(url!==service.url+'/print-settings.html')e.preventDefault();});w.loadURL(service.url+'/print-settings.html');}},{label:'Imprimir comprobante visible',click:()=>workspace.print()}]}
  ]));
  await workspace.start();
}).catch(error=>{dialog.showErrorBox('FISITAAP no pudo abrir',error.message);app.quit();});
app.on('before-quit',()=>{workspace?.close();service?.close();printService?.close();});
app.on('window-all-closed',()=>app.quit());
}
