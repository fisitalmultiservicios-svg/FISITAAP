'use strict';
const fs=require('node:fs'),path=require('node:path');
exports.default=async context=>{
 const files=[],dirs=[];
 const walk=(base,relative='')=>{for(const entry of fs.readdirSync(base,{withFileTypes:true})){const name=relative?relative+'/'+entry.name:entry.name;if(entry.isDirectory()){dirs.push(name);walk(path.join(base,entry.name),name);}else files.push(name);}};
 walk(context.appOutDir);const literal=p=>p.replaceAll('$','$$').replaceAll('"','$\\"').replaceAll('/','\\');
 const lines=files.map(name=>'Delete "$INSTDIR\\'+literal(name)+'"');lines.push('Delete "$INSTDIR\\Desinstalar.exe"');for(const dir of dirs.sort((a,b)=>b.length-a.length))lines.push('RMDir "$INSTDIR\\'+literal(dir)+'"');lines.push('RMDir "$INSTDIR"');fs.writeFileSync(path.join(context.packager.projectDir,'dist','uninstall-files.nsh'),lines.join('\n')+'\n');
};
