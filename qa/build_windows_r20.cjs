'use strict';
// Solid LZMA preserves the installer payload while keeping the executable small
// enough for a normal GitHub download. Slow CI runners need more than the
// upstream builder's fixed four-minute NSIS timeout. This affects build time only.
const path=require('node:path'),{spawn}=require('node:child_process');
const util=require('../desktop/node_modules/builder-util/out/util');
const original=util.spawnAndWriteWithOutput;
util.spawnAndWriteWithOutput=(command,args,data,options)=>{
 if(!path.basename(command).toLowerCase().includes('makensis'))return original(command,args,data,options);
 return new Promise((resolve,reject)=>{
  const child=spawn(command,args,{...options,stdio:['pipe','pipe','pipe']});let stdout='',stderr='',expired=false;
  const timer=setTimeout(()=>{expired=true;child.kill();},10*60*1000);
  child.stdout.on('data',s=>stdout+=s);child.stderr.on('data',s=>stderr+=s);
  child.on('error',e=>{clearTimeout(timer);reject(e)});
  child.on('close',code=>{clearTimeout(timer);if(!code&&!expired)resolve({stdout,stderr});else reject(new Error(expired?'NSIS exceeded ten minutes':`NSIS failed (${code}): ${stderr}\n${stdout}`))});
  child.stdin.on('error',e=>{if(e.code!=='EPIPE')reject(e)});child.stdin.end(data);
 });
};
process.chdir(path.resolve(__dirname,'../desktop'));
require('../desktop/node_modules/electron-builder/out/cli/cli');
