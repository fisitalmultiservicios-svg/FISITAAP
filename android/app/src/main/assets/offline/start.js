(() => {
  'use strict';
  FISITAAP_ENGINE.createLocalServer({dataDir:'/offline',autoSync:false,fetch:offlineRemote}).then(service=>{
    window.offlineService=service;
    const app=document.createElement('script');app.src='app.js';app.onload=()=>{const mobile=document.createElement('script');mobile.src='mobile.js';document.body.append(mobile);};document.body.append(app);
    const sync=()=>service.sync();setInterval(sync,60000);sync();
  }).catch(error=>{document.querySelector('#root').textContent=error.message+' No borres los datos ni desinstales la aplicación.';});
})();
