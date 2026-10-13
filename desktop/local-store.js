'use strict';
const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const clone=value=>JSON.parse(JSON.stringify(value));
function atomic(file,text){
 const temp=file+'.'+crypto.randomBytes(8).toString('hex')+'.nuevo';let fd;
 try{fd=fs.openSync(temp,'wx',0o600);fs.writeFileSync(fd,text);fs.fsyncSync(fd);fs.closeSync(fd);fd=undefined;fs.renameSync(temp,file);try{const directory=fs.openSync(path.dirname(file),'r');try{fs.fsyncSync(directory);}finally{fs.closeSync(directory);}}catch{}}
 finally{if(fd!==undefined)fs.closeSync(fd);fs.rmSync(temp,{force:true});}
}
function split(snapshot){
 const core=clone(snapshot),overlay={};for(const key of ['id','generated_at','cash_state','offline_policy'])if(Object.hasOwn(core,key)){overlay[key]=core[key];delete core[key];}
 const stocks={};for(const product of core.products||[]){const values={};if(Object.hasOwn(product,'stock')){values.stock=product.stock;delete product.stock;}values.components={};for(const item of product.components||[])if(Object.hasOwn(item,'stock')){values.components[item.id]=item.stock;delete item.stock;}stocks[product.id]=values;}
 const text=JSON.stringify(core),hash=crypto.createHash('sha256').update(text).digest('hex');return {text,hash,overlay,stocks};
}
class LocalStore {
 constructor(dir){
  this.dir=dir;for(const part of ['', 'backups','catalogos'])fs.mkdirSync(path.join(dir,part),{recursive:true,mode:0o700});this.file=path.join(dir,'operaciones.json');
  const raw=fs.existsSync(this.file)?JSON.parse(fs.readFileSync(this.file,'utf8')):{version:1,connection:null,snapshot:null,stocks:{},sales:[],shifts:[],lastSync:null,syncMessage:'Conecta primero el equipo central.'};
  this.data=this.hydrate(raw);if(this.data.version!==1||!Array.isArray(this.data.sales)||!Array.isArray(this.data.shifts))throw new Error('El archivo de operaciones necesita revisión. Se conservó sin cambios.');
  if(fs.existsSync(this.file)&&!raw.storage180){const original=path.join(dir,'backups','antes-almacenamiento-180.json');if(!fs.existsSync(original))atomic(original,JSON.stringify(this.data));}
 }
 hydrate(raw){
  if(!raw.storage180)return raw;const ref=raw.storage180;if(ref.version!==1||!/^[a-f0-9]{64}$/.test(ref.catalog))throw new Error('El catálogo local necesita revisión. Se conservaron los archivos.');
  const text=fs.readFileSync(path.join(this.dir,'catalogos',ref.catalog+'.json'),'utf8');if(crypto.createHash('sha256').update(text).digest('hex')!==ref.catalog)throw new Error('El catálogo local no pasó su comprobación. Se conservaron los archivos.');
  const data=clone(raw),snapshot={...JSON.parse(text),...data.snapshot};for(const product of snapshot.products||[]){const stocks=ref.stocks[product.id];if(!stocks)throw new Error('Faltan las existencias del catálogo. Se conservaron los archivos.');if(Object.hasOwn(stocks,'stock'))product.stock=stocks.stock;for(const item of product.components||[])if(Object.hasOwn(stocks.components,item.id))item.stock=stocks.components[item.id];}
  data.snapshot=snapshot;delete data.storage180;return data;
 }
 commit(next){
  const raw={...next};delete raw.storage180;
  if(next.snapshot){const catalog=split(next.snapshot),file=path.join(this.dir,'catalogos',catalog.hash+'.json');if(!fs.existsSync(file))atomic(file,catalog.text);raw.snapshot=catalog.overlay;raw.storage180={version:1,catalog:catalog.hash,stocks:catalog.stocks};}
  atomic(this.file,JSON.stringify(raw));this.data=next;
 }
 backup(){const target=path.join(this.dir,'backups','FISITAAP-'+new Date().toISOString().replace(/[:.]/g,'-')+'-'+crypto.randomBytes(3).toString('hex')+'.json');atomic(target,JSON.stringify(this.data));return target;}
}
module.exports={LocalStore};
