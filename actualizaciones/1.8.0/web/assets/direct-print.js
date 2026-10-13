(() => {
  'use strict';
  // Existing Windows and Android native bridges; no new installer required.
  const native = () => Boolean(window.fisitaapWindows || window.__fisitaapAndroidInstalled);
  let busy = false;
  try{const saved=sessionStorage.getItem('fisitaap-direct-print-result');if(saved){sessionStorage.removeItem('fisitaap-direct-print-result');const box=notice(saved);const close=document.createElement('button');close.type='button';close.textContent='Cerrar aviso';close.onclick=()=>box.remove();box.append(close);}}catch{}
  function notice(text, error = false) {
    let box = document.querySelector('.direct-print-notice');
    if (!box) { box = document.createElement('section'); box.className = 'direct-print-notice'; box.setAttribute('role','status'); document.body.append(box); }
    box.replaceChildren();
    const line = document.createElement('p'); line.textContent = text; if(error)line.dataset.error='1'; box.append(line);
    return box;
  }
  function link(box, text, href) {
    const target = new URL(href, location.href);
    if(target.origin !== location.origin)return;
    const a=document.createElement('a');a.textContent=text;a.href=target.href;box.append(a);
  }
  async function send(jobs, continuation, returnToWork = false) {
    busy=true;
    const failures=[];
    let duplicates=0;
    try {
      for(const job of jobs) {
        notice('Enviando a la impresora configurada…');
        try {
          let result;
          if(window.fisitaapWindows)result=await window.fisitaapWindows.print(job);
          else {
            // Android intercepts this exact URL and dispatches to its native printer.
            const r=await fetch('http://127.0.0.1:18765/print',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(job)});
            result=await r.json();
          }
          if(!result.ok || result.status!=='sent_to_printer')throw Error(result.error||'El envío no quedó confirmado.');
          if(result.duplicate)duplicates++;
        } catch(error) { failures.push(job); }
      }
      const box=notice(failures.length?'No se confirmó la impresión. Revisa la impresora y el papel antes de reintentar. La operación no se volverá a cobrar.':(duplicates===jobs.length?'Este comprobante ya había sido enviado.':'Enviado a la impresora configurada.'),failures.length>0);
      if(failures.length) {
        const retry=document.createElement('button');retry.type='button';retry.textContent='Reintentar impresión';retry.onclick=()=>{if(!busy)send(failures,continuation,returnToWork);};box.append(retry);
      }
      else if(!returnToWork) {
        const copy=document.createElement('button');copy.type='button';copy.textContent='Imprimir otra copia';
        copy.onclick=()=>{if(busy||!confirm('¿Imprimir otra copia? Revisa primero el papel.'))return;const suffix='-copy-'+Array.from(crypto.getRandomValues(new Uint8Array(16)),v=>v.toString(16).padStart(2,'0')).join('');send(jobs.map(job=>({...job,id:String(job.id).slice(0,140)+suffix})),continuation);};box.append(copy);
      }
      link(box,'Continuar',continuation);
      const close=document.createElement('button');close.type='button';close.textContent='Cerrar aviso';close.onclick=()=>box.remove();box.append(close);
      if(!failures.length && returnToWork) {
        try{sessionStorage.setItem('fisitaap-direct-print-result','Enviado a la impresora configurada.');}catch{}
        location.assign(continuation);
      }
    } finally { busy=false; }
  }
  async function load(href, options, form) {
    busy=true;notice('Preparando impresión…');
    try {
      const target=new URL(href,location.href);
      if(target.origin!==location.origin)throw Error('Dirección de impresión inválida.');
      const response=await fetch(target.href,{credentials:'same-origin',...options});
      const doc=new DOMParser().parseFromString(await response.text(),'text/html');
      if(!response.ok)throw Error('El servidor no confirmó la operación (HTTP '+response.status+').');
      const sources=[...doc.querySelectorAll('.print-result-card [data-print-payload],.print-result-card #fisitaapPrintPayload')];
      if(!sources.length)throw Error(doc.querySelector('.alert-error')?.textContent.trim()||'No se recibió un comprobante. Revisa la operación antes de volver a cobrar o enviar.');
      const isPayment=form?.matches('[data-collect],[data-r3-collect],.p148-payment');
      const jobs=sources.filter(node=>!isPayment||node.closest('.print-result-card').querySelector('[data-print-job]')?.dataset.auto==='1').map(node=>JSON.parse(node.textContent));
      const destination=isPayment?([...doc.querySelectorAll('a')].find(a=>a.textContent.trim()==='Continuar')?.getAttribute('href')||location.href):location.href;
      if(form)form.dataset.printSaved='1';
      // The payment dialog is modal: close it before showing the compact result.
      if(isPayment)form.closest('dialog[open]')?.close();
      if(!jobs.length){try{sessionStorage.setItem('fisitaap-direct-print-result','Venta registrada. Impresión automática desactivada.');}catch{}location.assign(destination);return;}
      await send(jobs,destination,Boolean(form));
    } catch(error) {
      const box=notice(error.message+' Revisa el estado antes de repetir la operación.',true);
      link(box,'Revisar operación',location.href);
      // A transport failure may follow a committed sale. Never retry the financial POST.
    } finally { busy=false; }
  }
  document.addEventListener('click',event=>{
    const a=event.target.closest?.('a[href]');
    if(!native()||!a||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||a.hasAttribute('download'))return;
    const url=new URL(a.href,location.href);
    if(url.origin!==location.origin||!url.pathname.endsWith('/admin/recibo'))return;
    event.preventDefault();event.stopImmediatePropagation();if(!busy)load(url.href);
  },true);
  document.addEventListener('submit',event=>{
    const form=event.target;
    if(!native()||!(form instanceof HTMLFormElement)||form.method.toLowerCase()!=='post')return;
    const action=event.submitter?.name==='action'?event.submitter.value:form.elements.namedItem('action')?.value;
    if(!['send_kitchen','prebill'].includes(action)&&!form.matches('[data-collect],[data-r3-collect],.p148-payment'))return;
    event.preventDefault();event.stopImmediatePropagation();
    if(busy||form.dataset.printSaved||form.dataset.printPending)return;
    const body=new FormData(form);
    if(event.submitter?.name)body.set(event.submitter.name,event.submitter.value);
    form.dataset.printPending='1';
    form.querySelectorAll('button[type=submit],button:not([type])').forEach(button=>button.disabled=true);
    // An input named "action" shadows HTMLFormElement.action in these POS forms.
    load(form.getAttribute('action')||location.href,{method:'POST',body},form);
  },true);
})();
