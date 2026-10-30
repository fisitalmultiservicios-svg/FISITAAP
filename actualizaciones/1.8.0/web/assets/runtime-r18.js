(() => {
 'use strict';
 const $=(s,r=document)=>r.querySelector(s),base=String(window.FISITAPP?.base||'/').replace(/\/$/,'');
 const key=()=>Array.from(crypto.getRandomValues(new Uint8Array(16)),v=>v.toString(16).padStart(2,'0')).join('');
 const hidden=(f,name,value)=>{const input=document.createElement('input');input.type='hidden';input.name=name;input.value=String(value);f.append(input);};
 const grid=$('[data-r14-pos]');
 if(grid){
  const main=grid.closest('[data-r15-page]');main?.classList.add('r18-sale-view');
  let frame=0;const fit=()=>{cancelAnimationFrame(frame);frame=requestAnimationFrame(()=>{const viewport=window.visualViewport?.height||innerHeight,top=grid.getBoundingClientRect().top,invoice=$('.ticket',grid);const fixed=invoice?[...invoice.children].filter(el=>!el.classList.contains('r3-sale-table-wrap')).reduce((sum,el)=>sum+el.getBoundingClientRect().height,0):0;const height=Math.max(390,Math.ceil(fixed+116),Math.floor(viewport-top-18));grid.style.setProperty('--r18-panel-height',height+'px');});};
  const observer=new ResizeObserver(fit);if(main)observer.observe(main);const invoice=$('.r3-cart-panel',grid);if(invoice)observer.observe(invoice);document.addEventListener('click',event=>{if(event.target.closest('[data-r15-panel]'))fit();});window.addEventListener('resize',fit);window.visualViewport?.addEventListener('resize',fit);document.addEventListener('op180:updated',fit);fit();
 }
 document.querySelectorAll('input[type=password]').forEach(el=>{if(el.getAttribute('minlength'))el.minLength=4;});
 const cash=$('[data-r18-cash-bar]');
 if(cash)cash.addEventListener('click',event=>{
  const link=event.target.closest('[data-r18-open-shift]');if(!link)return;event.preventDefault();let dialog=$('#r18OpenShift');
  if(!dialog){dialog=document.createElement('dialog');dialog.id='r18OpenShift';dialog.className='r3-dialog';const form=document.createElement('form');form.method='post';form.action=base+'/admin/turnos';hidden(form,'csrf',window.FISITAPP.csrf);hidden(form,'action','open');hidden(form,'opening_key',key());hidden(form,'return_to_r18',cash.dataset.returnTo||'admin/ventas');const title=document.createElement('h2');title.textContent='Abrir mi turno';const text=document.createElement('p');text.textContent='Este turno y sus cobros quedarán a tu nombre en esta caja.';const label=document.createElement('label');label.textContent='Fondo inicial de efectivo';const amount=document.createElement('input');amount.type='number';amount.className='input';amount.name='opening_cash';amount.min='0';amount.step='.01';amount.value='0';amount.required=true;label.append(amount);const footer=document.createElement('footer'),cancel=document.createElement('button'),submit=document.createElement('button');cancel.type='button';cancel.className='btn btn-light';cancel.textContent='Volver';cancel.onclick=()=>dialog.close();submit.className='btn primary';submit.textContent='Abrir turno';footer.append(cancel,submit);form.append(title,text,label,footer);dialog.append(form);document.body.append(dialog);}
  dialog.showModal();
 });
 let goingToTable=false;
 document.addEventListener('click',event=>{
  const el=event.target.closest('[data-r3-table]');if(!el||el.closest('[data-layout-editor],.is-ordering')||$('[data-layout-editor]'))return;const data=$('script[data-r3-table-data]');if(!data)return;let context;try{context=JSON.parse(data.textContent);}catch(_){return;}
  const table=context.tables.find(t=>Number(t.id)===Number(el.dataset.r3Table));if(!table||table.space_type==='bar')return;
  const accounts=context.accounts.filter(a=>Number(a.table_id)===Number(table.id)||(a.table_ids||[]).map(Number).includes(Number(table.id)));if(accounts.length>1)return;
  event.preventDefault();event.stopImmediatePropagation();if(goingToTable)return;goingToTable=true;
  const target=String(context.base).replace(/\/$/,'')+'/admin/ventas';
  if(accounts.length===1){location.assign(target+'?screen=order&ticket='+Number(accounts[0].id));return;}
  const form=document.createElement('form');form.method='post';form.action=target;for(const [n,v]of Object.entries({csrf:context.csrf,action:'table',table_id:table.id,open_key:key()}))hidden(form,n,v);document.body.append(form);form.submit();
 },true);
 // Authenticated employee selectors are scoped to the current business; no public global directory.
 const clock=$('.hr17-clock-dialog [name=login]');
 if(clock){fetch(base+'/api/staff-r18',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(async r=>{if(!r.ok)return null;return r.json();}).then(out=>{
  if(!out?.ok)return;const select=document.createElement('select');select.className='input';select.required=true;select.setAttribute('aria-label','Usuario que marca');select.add(new Option('Selecciona tu usuario',''));for(const u of out.users)select.add(new Option((u.username_r17||u.name)+(u.username_r17?' · '+u.name:''),u.email));clock.type='hidden';clock.required=false;clock.before(select);select.addEventListener('change',()=>{clock.value=select.value;clock.dispatchEvent(new Event('input',{bubbles:true}));clock.form.elements.password.focus();});
 }).catch(()=>{});}
 const cfgEl=$('[data-fc-config]');let cfg;try{cfg=cfgEl?JSON.parse(cfgEl.textContent):null;}catch(_){}
 const alertsBox=$('[data-r18-alerts]'),launch=$('.fc-launch');
 if(cfg?.owner_alerts_endpoint&&alertsBox){
  const endpoint=new URL(cfg.owner_alerts_endpoint,location.href);if(endpoint.origin!==location.origin)return;
  let busy=false;
  const draw=alerts=>{alertsBox.replaceChildren();alertsBox.hidden=!alerts.length;let badge=$('[data-r18-alert-count]',launch||document);if(!badge&&launch){badge=document.createElement('b');badge.dataset.r18AlertCount='';launch.append(badge);}if(badge){badge.textContent=String(alerts.length);badge.hidden=!alerts.length;}
   if(!alerts.length)return;const title=document.createElement('strong');title.textContent='Avisos para tu negocio';alertsBox.append(title);for(const a of alerts){const article=document.createElement('article'),head=document.createElement('b'),body=document.createElement('p'),link=document.createElement('a'),done=document.createElement('button');head.textContent=a.title;body.textContent=a.body;link.textContent='Revisar →';const url=new URL(a.url,location.href);if(url.origin===location.origin)link.href=url.href;done.type='button';done.textContent='Visto';done.addEventListener('click',async()=>{done.disabled=true;try{const r=await fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:new URLSearchParams({csrf:window.FISITAPP.csrf,id:a.id})});const out=await r.json();if(!r.ok||!out.ok)throw new Error(out.error||'No se guardó.');draw(out.alerts);}catch(e){done.textContent=e.message;done.disabled=false;}});article.append(head,body,link,done);alertsBox.append(article);}
  };
  const refresh=async()=>{if(busy||document.hidden)return;busy=true;try{const r=await fetch(endpoint,{credentials:'same-origin',headers:{Accept:'application/json'}});if(r.ok){const out=await r.json();if(out.ok)draw(out.alerts);}}catch(_){}finally{busy=false;}};refresh();setInterval(refresh,60000);document.addEventListener('visibilitychange',refresh);
 }
})();
