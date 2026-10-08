(() => {
  'use strict';
  if(!window.structuredClone)window.structuredClone=value=>JSON.parse(JSON.stringify(value));
  const checked=raw=>{const value=JSON.parse(raw);if(!value.ok)throw Error(value.error||'No se pudo guardar la caja.');return value;};
  const disk=checked(OfflineStorage.read());let revision=disk.revision,current=disk.state,temp=null,ambiguousWrite=false;
  const fs={mkdirSync(){},existsSync:()=>current!==null,readFileSync:()=>current,
    openSync:()=>1,writeFileSync:(_fd,data)=>{temp=data;},fsyncSync(){},closeSync(){},
    renameSync(){try{const saved=checked(OfflineStorage.commit(temp,revision));current=temp;revision=saved.revision;temp=null;}catch(error){ambiguousWrite=true;throw error;}},
    copyFileSync(){checked(OfflineStorage.backup());},chmodSync(){}};
  const hex=bytes=>[...bytes].map(x=>x.toString(16).padStart(2,'0')).join('');
  const cryptoModule={randomBytes:n=>({toString:()=>hex(crypto.getRandomValues(new Uint8Array(n)))}),randomUUID:()=>{if(crypto.randomUUID)return crypto.randomUUID();const b=crypto.getRandomValues(new Uint8Array(16));b[6]=(b[6]&15)|64;b[8]=(b[8]&63)|128;const h=hex(b);return h.slice(0,8)+'-'+h.slice(8,12)+'-'+h.slice(12,16)+'-'+h.slice(16,20)+'-'+h.slice(20);},
    createHash:()=>({update(text){this.text=text;return this;},digest(){return OfflineStorage.digest(this.text);}})};
  let handler;
  const http={createServer:fn=>{handler=fn;return {once(){},listen(_p,_host,done){done();},address:()=>({port:18766}),close(){}};}};
  const modules={'node:http':http,'node:fs':fs,'node:path':{join:(...parts)=>parts.join('/'),basename:x=>x.split('/').pop()},'node:os':{networkInterfaces:()=>({})},'node:crypto':cryptoModule,bcryptjs:window.bcrypt};
  window.offlineRequire=name=>{if(!modules[name])throw Error('Módulo no disponible: '+name);return modules[name];};
  window.Buffer={byteLength:text=>new TextEncoder().encode(text).length};
  let cookie='',sequence=0;const waiting=new Map();
  FisitaapOfflineNetwork.onmessage=event=>{const response=JSON.parse(event.data),item=waiting.get(response.requestId);if(!item)return;clearTimeout(item.timer);waiting.delete(response.requestId);response.transport_error?item.reject(Error(response.transport_error)):item.resolve({ok:response.status>=200&&response.status<300,status:response.status,json:async()=>JSON.parse(response.body)});};
  window.offlineRemote=(url,init)=>new Promise((resolve,reject)=>{const requestId='net-'+(++sequence),timer=setTimeout(()=>{waiting.delete(requestId);reject(Error('El servidor tardó demasiado. Las ventas siguen guardadas.'));},65000);waiting.set(requestId,{resolve,reject,timer});FisitaapOfflineNetwork.postMessage(JSON.stringify({requestId,url,body:init.body,token:init.headers.Authorization||''}));});
  let serial=Promise.resolve();
  window.fetch=(route,init={})=>{
    if(typeof route!=='string'||!/^\/api\/[a-z/]+$/.test(route))return Promise.reject(Error('Ruta local no permitida.'));
    const run=()=>new Promise((resolve,reject)=>{
      ambiguousWrite=false;
      const headers={host:'127.0.0.1:18766',origin:'http://127.0.0.1:18766','content-type':'application/json',cookie,'x-csrf-token':init.headers?.['X-CSRF-Token']||''};
      const req={url:route,method:init.method||'GET',headers,socket:{remoteAddress:'127.0.0.1'},async *[Symbol.asyncIterator](){yield init.body||'{}';}};
      const res={writeHead(status,head){this.status=status;if(head['Set-Cookie'])cookie=head['Set-Cookie'].split(';')[0];},end(text){const out=JSON.parse(text);if(ambiguousWrite&&out.not_saved)out.not_saved=false;resolve({ok:this.status>=200&&this.status<300,status:this.status,json:async()=>out});}};
      Promise.resolve(handler(req,res)).catch(reject);
    });
    const result=serial.then(run,run);serial=result.catch(()=>{});return result;
  };
})();
