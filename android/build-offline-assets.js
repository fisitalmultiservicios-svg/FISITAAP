'use strict';
// Build outputs: share the tested Windows sales engine and UI, not a second implementation.
const fs=require('node:fs'),path=require('node:path');
const root=path.join(__dirname,'app/src/main/assets/offline');fs.mkdirSync(root,{recursive:true});
const server=fs.readFileSync(path.join(__dirname,'../desktop/server.js'),'utf8');
fs.writeFileSync(path.join(root,'engine.js'),'(function(require,module,__dirname){\n'+server+'\nwindow.FISITAAP_ENGINE=module.exports;\n})(window.offlineRequire,{exports:{}},"/offline");\n');
for(const file of ['app.js','style.css'])fs.copyFileSync(path.join(__dirname,'../desktop/public',file),path.join(root,file));
fs.copyFileSync(path.join(__dirname,'../desktop/node_modules/bcryptjs/umd/index.js'),path.join(root,'bcrypt.js'));
fs.copyFileSync(path.join(__dirname,'../desktop/node_modules/bcryptjs/LICENSE'),path.join(root,'BCRYPT-LICENSE.txt'));
console.log('Offline assets built from the same sales engine and UI as Windows');
