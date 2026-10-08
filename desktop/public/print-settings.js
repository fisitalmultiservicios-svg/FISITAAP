'use strict';
(async()=>{
  const form=document.getElementById('printSettingsForm'),status=document.getElementById('printStatus'),bridgeStatus=document.getElementById('bridgeStatus'),retry=document.getElementById('retryBridge');
  if(!window.fisitaapWindows){status.textContent='Abre esta pantalla desde el menú Impresión del programa Windows.';return;}
  try{
    const settings=await window.fisitaapWindows.settings(),printers=await window.fisitaapWindows.printers();
    const showBridge=value=>{
      bridgeStatus.textContent=value.available?'Conexión para imprimir desde la web disponible.':'La conexión para imprimir desde la web está ocupada por otra aplicación. Puedes usar la caja y su impresión local. Cuando cierres la otra aplicación, pulsa Reintentar conexión de impresión.';
      retry.hidden=value.available;
    };
    showBridge(settings.bridge);
    retry.onclick=async()=>{retry.disabled=true;try{showBridge(await window.fisitaapWindows.retryPrintBridge());}catch(error){bridgeStatus.textContent=error.message;}finally{retry.disabled=false;}};
    form.elements.origin.value=settings.origin||'';form.elements.token.value=settings.token;form.elements.width.value=String(settings.printer?.width||80);
    for(const printer of printers){const o=document.createElement('option');o.value=printer.name;o.textContent=printer.displayName||printer.name;form.elements.printer.append(o);}
    form.elements.printer.value=settings.printer?.name||'';
    form.onsubmit=async event=>{event.preventDefault();try{const data=Object.fromEntries(new FormData(form));await window.fisitaapWindows.saveSettings({origin:data.origin,printer:{name:data.printer,width:Number(data.width),type:data.printer?'usb':'browser'}});status.textContent='Configuración guardada.';showBridge((await window.fisitaapWindows.settings()).bridge);}catch(error){status.textContent=error.message;}};
  }catch(error){status.textContent=error.message;}
})();
