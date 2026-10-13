(() => {
  'use strict';
  const data=JSON.parse(document.querySelector('#performance-data').textContent);
  const button=document.querySelector('#measure'),results=document.querySelector('#results'),summary=document.querySelector('#summary');
  button.onclick=async()=>{
    button.disabled=true;results.replaceChildren();
    const lines=['Arranque: '+data.boot_ms+' ms','Base de datos: '+data.db_ms+' ms'];
    try {
      for(const item of data.routes) {
        const row=document.createElement('tr'),name=document.createElement('td'),value=document.createElement('td');name.textContent=item.label;value.textContent='Midiendo…';row.append(name,value);results.append(row);
        const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),45000);
        const start=performance.now();
        try {
          const target=new URL(item.url,location.href);
          if(target.origin!==location.origin)throw Error('La dirección configurada apunta a otro dominio.');
          const response=await fetch(target.href,{credentials:'same-origin',cache:'no-store',headers:{'X-Fisitaap-Performance':'1'},signal:controller.signal});
          const first=performance.now()-start;
          const bytes=(await response.arrayBuffer()).byteLength;
          if(new URL(response.url).pathname.includes('login'))throw Error('La sesión terminó; inicia sesión nuevamente.');
          value.textContent='HTTP '+response.status+' · respuesta '+Math.round(first)+' ms · total '+Math.round(performance.now()-start)+' ms · '+Math.round(bytes/1024)+' KB';
          const timing=response.headers.get('Server-Timing');if(timing)value.textContent+=' · '+timing;
        } catch(error){value.textContent=error.name==='AbortError'?'Más de 45 segundos':error.message;}
        finally{clearTimeout(timer);}
        lines.push(item.label+': '+value.textContent);summary.textContent=lines.join('\n');
      }
    } finally {button.disabled=false;}
  };
})();
