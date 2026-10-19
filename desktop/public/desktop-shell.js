'use strict';
(async()=>{
  const api=window.fisitaapWindows,status=document.getElementById('workspaceStatus'),web=document.getElementById('webMode'),label=document.getElementById('modeLabel'),local=document.getElementById('localMode'),ready=document.getElementById('offlineReady');
  if(!api?.workspaceState){status.textContent='Abre esta pantalla desde FISITAAP Escritorio.';web.disabled=true;return;}
  const render=value=>{
    local.hidden=value.mode==='local'||!value.primary;local.disabled=!value.configured;ready.textContent=value.primary?(value.offlineReady?'✓ Caja local preparada':'Caja local: falta preparar acceso'):'Equipo secundario';
    status.textContent=value.message;status.className=value.available&&value.centralReady?'':'warning';
    web.disabled=!value.webUrl;web.setAttribute('aria-pressed',String(value.mode==='web'));web.hidden=value.mode==='web'||!value.configured;web.textContent='Volver al sistema completo';label.textContent=value.mode==='web'?'Sistema web completo':value.configured?'Venta sin internet':'Conectar equipo';
  };
  api.onWorkspaceStatus(render);local.onclick=async()=>render(await api.useLocal());
  web.onclick=async()=>{web.disabled=true;try{render(await api.useWeb());}catch(error){status.textContent=error.message;}finally{render(await api.workspaceState());}};

  render(await api.workspaceState());
})();
