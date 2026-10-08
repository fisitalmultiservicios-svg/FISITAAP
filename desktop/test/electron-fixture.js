'use strict';
const {EventEmitter}=require('node:events');
function electronFixture(){
 const windows=[],views=[],external=[],errors=[],handlers=new Map();let menu;
 class Contents extends EventEmitter {
  constructor(options={}){super();this.options=options;this.loads=[];this.url='';this.sent=[];this.session={setPermissionRequestHandler:handler=>this.permissionRequest=handler,setPermissionCheckHandler:handler=>this.permissionCheck=handler};}
  setWindowOpenHandler(handler){this.openHandler=handler;}
  async loadURL(url){this.loads.push(url);if(this.failLoad)throw new Error('Network unavailable');this.url=url;this.emit('did-navigate',{},url,200);}
  getURL(){return this.url;}
  send(name,value){this.sent.push({name,value});}
  close(){this.closed=true;}
  async printToPDF(){return Buffer.from('%PDF-1.7 fixture');}
  async getPrintersAsync(){return [];}
  print(){this.printed=true;}
  reload(){this.reloaded=true;}
  getZoomLevel(){return this.zoom||0;}
  setZoomLevel(value){this.zoom=value;}
 }
 class BrowserWindow extends EventEmitter {
  constructor(options={}){super();this.options=options;this.webContents=new Contents(options.webPreferences);this.children=[];this.contentView={addChildView:view=>this.children.push(view),removeChildView:view=>{this.children=this.children.filter(v=>v!==view);}};windows.push(this);}
  loadURL(url){this.url=url;return this.webContents.loadURL(url);}
  setTitle(value){this.title=value;}
  getContentSize(){return [1360,950];}
  close(){this.emit('closed');}
  destroy(){this.destroyed=true;}
  restore(){this.restored=true;}
  focus(){this.focused=true;}
  static getAllWindows(){return windows.filter(w=>!w.destroyed);}
 }
 class WebContentsView {
  constructor(options={}){this.options=options;this.webContents=new Contents(options.webPreferences);views.push(this);}
  setBounds(value){this.bounds=value;}
 }
 return {windows,views,external,errors,handlers,get menu(){return menu;},electron:{BrowserWindow,WebContentsView,shell:{openExternal:async url=>external.push(url),openPath:async()=>{}},dialog:{showErrorBox:(title,message)=>errors.push({title,message})},Menu:{buildFromTemplate:value=>value,setApplicationMenu:value=>{menu=value;}},ipcMain:{handle:(name,handler)=>handlers.set(name,handler)},safeStorage:{isEncryptionAvailable:()=>true,encryptString:value=>Buffer.from('protected:'+value),decryptString:value=>value.toString().slice(10)}}};
}
module.exports={electronFixture};
