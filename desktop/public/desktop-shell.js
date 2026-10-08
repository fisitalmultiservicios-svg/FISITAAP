'use strict';
(async()=>{
  const api=window.fisitaapWindows,status=document.getElementById('workspaceStatus'),web=document.getElementById('webMode'),local=document.getElementById('localMode'),label=document.getElementById('modeLabel');
  if(!api?.workspaceState){status.textContent='Abre esta pantalla desde FISITAAP Escritorio.';web.disabled=true;local.disabled=true;return;}
  const render=value=>{
    status.textContent=value.message;status.className=value.available&&value.centralReady?'':'warning';
    web.disabled=!value.webUrl;web.setAttribute('aria-pressed',String(value.mode==='web'));local.setAttribute('aria-pressed',String(value.mode==='local'));label.textContent=value.mode==='web'?'Sistema web completo':'Caja local · Buscar y vender';
  };
  api.onWorkspaceStatus(render);
  web.onclick=async()=>{web.disabled=true;try{render(await api.useWeb());}catch(error){status.textContent=error.message;}finally{render(await api.workspaceState());}};
  local.onclick=async()=>{try{render(await api.useLocal());}catch(error){status.textContent=error.message;}};
  render(await api.workspaceState());
})();
