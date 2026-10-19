(()=>{
  'use strict';
  const context=window.OP180;if(!context)return;
  const base=context.base.replace(/\/$/,''),csrf=()=>window.FISITAPP?.csrf||context.csrf;
  const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const money=value=>'₡'+Number(value).toLocaleString('es-CR',{minimumFractionDigits:2,maximumFractionDigits:2});
  async function api(path,data){const r=await fetch(base+'/'+path,{method:data?'POST':'GET',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json',...(data instanceof FormData?{}:data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data instanceof FormData?data:data?new URLSearchParams({csrf:csrf(),...data}):undefined,signal:AbortSignal.timeout(12000)});const out=await r.json();if(!r.ok||!out.ok)throw new Error(out.error||'No se completó la solicitud.');return out;}
  const status=document.createElement('span');status.className='op180-live-status';status.setAttribute('role','status');status.textContent='Actualización automática · 2 s';
  (document.querySelector('.admin-top .admin-actions')||document.querySelector('.driver-top')||document.querySelector('.r3-topbar'))?.append(status);
  let version=null,busy=false,changed=false,typingAt=0,stopped=false;
  document.addEventListener('input',()=>typingAt=Date.now());
  function markRegions(doc){
    for(const [selector,key] of [['.driver-app','driver'],['.account-layout>section','customer-account'],['.r3-sale-table-wrap','pos-lines'],['.r3-sale-total','pos-totals'],['.r3-waiting','waiting'],['[data-basic-tables]','tables-cards'],['[data-layout-tables]','tables-svg'],['script[data-r3-table-data]','table-data'],['script[data-r3-layout-data]','layout-data'],['script[data-r3-items]','pos-items']]){
      const region=doc.querySelector(selector);if(region&&!region.hasAttribute('data-live-region'))region.dataset.liveRegion=key;
    }
  }
  markRegions(document);
  function editing(region){return region.contains(document.activeElement)&&document.activeElement?.matches('input,textarea,select')||Date.now()-typingAt<2500||region.querySelector('details[open] form')||document.querySelector('.r3-dialog[open],.r3-payment-modal[open]');}
  async function refreshRegions(){
    if(!document.querySelector('[data-live-region]'))return;
    const r=await fetch(location.href,{credentials:'same-origin',cache:'no-store',headers:{'X-FISITAAP-Live':'1'},signal:AbortSignal.timeout(12000)});
    if(!r.ok||r.redirected)return;const doc=new DOMParser().parseFromString(await r.text(),'text/html');markRegions(doc);let deferred=false;
    for(const old of document.querySelectorAll('[data-live-region]')){
      if((old.dataset.liveRegion.startsWith('tables-')||['layout-data','table-data'].includes(old.dataset.liveRegion))&&document.querySelector('[data-layout-editor],[data-basic-tables].is-ordering')){deferred=true;continue;}
      const fresh=[...doc.querySelectorAll('[data-live-region]')].find(e=>e.dataset.liveRegion===old.dataset.liveRegion);if(!fresh)continue;
      if(editing(old)){deferred=true;continue;}
      const open=[...old.querySelectorAll('details[open]')].map(e=>e.id).filter(Boolean);old.replaceChildren(...fresh.childNodes);
      for(const id of open)old.querySelector('#'+CSS.escape(id))?.setAttribute('open','');
    }
    if(!deferred&&document.querySelector('.r3-sale-table-wrap')){const revision=doc.querySelector('.r3-sale-actions input[name=revision]')?.value;if(revision)for(const input of document.querySelectorAll('input[name=revision]'))input.value=revision;}
    changed=deferred;document.dispatchEvent(new Event('op180:updated'));
  }
  async function poll(){
    if(stopped||busy||document.hidden||!['master','tenant_admin','manager','editor','cashier','kitchen','customer','driver'].includes(context.role)&&!location.pathname.includes('/pedido/seguimiento'))return;
    busy=true;
    try{
      const params=new URL(location.href).searchParams;const tracking=location.pathname.endsWith('/pedido/seguimiento')?'&tracking_id='+encodeURIComponent(params.get('id')||'')+'&tracking_token='+encodeURIComponent(params.get('token')||''):'';
      const out=await api('api/live180?scope='+encodeURIComponent(location.pathname+location.search)+tracking);
      for(const badge of document.querySelectorAll('[data-incoming-count]'))badge.textContent=out.incoming;
      if(version!==out.version){changed=true;version=out.version;}
      if(changed)await refreshRegions();
      if(incoming.open&&!incomingBusy&&version!==incoming.dataset.version){await loadIncoming();incoming.dataset.version=version;}
      status.textContent=changed?'Cambios disponibles · se aplican al terminar de escribir':'Actualizado · cada 2 s';status.classList.remove('offline');
    }catch(_){status.textContent='Sin actualización · reintentando';status.classList.add('offline');}
    finally{busy=false;}
  }
  const timer=setInterval(poll,2000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll();});window.addEventListener('online',poll);window.addEventListener('pagehide',()=>{stopped=true;clearInterval(timer);});
  document.addEventListener('click',event=>{const button=event.target.closest('[data-quantity-step]');if(!button)return;const form=button.closest('form'),input=form.elements.quantity,step=Number(input.step)||1;input.value=String(Math.max(0,Math.round((Number(input.value)+Number(button.dataset.quantityStep)*step)*1000)/1000));form.requestSubmit();});
  document.addEventListener('submit',event=>{
    const form=event.target;if(!(form instanceof HTMLFormElement))return;
    const action=event.submitter?.name==='action'?event.submitter.value:form.elements.action?.value;
    if(action!=='void'||form.elements.cancel_reason?.value)return;
    const reason=window.prompt('Justificación de la anulación (mínimo 8 caracteres):');
    if(!reason||reason.trim().length<8){event.preventDefault();event.stopImmediatePropagation();return;}
    const input=document.createElement('input');input.type='hidden';input.name='cancel_reason';input.value=reason.trim();form.append(input);
  },true);
  document.addEventListener('click',event=>{
    const close=event.target.closest('[data-dialog-close]');if(close)close.closest('dialog')?.close();
    const cancel=event.target.closest('[data-order-cancel]');if(cancel){const modal=document.querySelector('#op180Cancel');if(!modal)return;const form=modal.querySelector('form');form.reset();form.elements.id.value=cancel.dataset.orderCancel;form.elements.revision.value=cancel.dataset.revision;modal.showModal();}
  });
  const incoming=document.createElement('dialog');incoming.id='op180Incoming';incoming.className='qa180-dispatch-dialog';incoming.innerHTML='<header><h2>Pedidos web por aceptar</h2><button class="btn btn-light" type="button" data-dialog-close>Cerrar</button></header><p data-incoming-message role="status"></p><div data-incoming-list></div>';document.body.append(incoming);
  let incomingBusy=false;
  if(context.staff&&/\/admin\/(ventas|mesas|cobro)(\/|$)/.test(location.pathname)){
    const button=document.createElement('button');button.type='button';button.className='btn op180-incoming-button';button.dataset.incomingOpen='';button.innerHTML='▣ Pedidos web <b data-incoming-count>0</b>';
    (document.querySelector('.r3-topbar')||document.querySelector('.p148-toolbar')||document.querySelector('.admin-top .admin-actions')||document.querySelector('.admin-main'))?.append(button);
  }
  async function loadIncoming(){
    const out=await api('api/incoming180');incoming.querySelector('[data-incoming-list]').innerHTML=out.orders.map(o=>'<article class="op180-incoming-order"><h3>#'+esc(o.order_number)+' · '+esc(o.customer_name)+'</h3><p>'+money(o.total)+' · '+esc(o.payment_method)+'</p>'+(o.proof_url?'<a target="_blank" rel="noopener" href="'+esc(o.proof_url)+'">Ver comprobante SINPE</a>':'')+'<p>'+(out.can_accept?'<button class="btn" type="button" data-accept-order="'+Number(o.id)+'">Aceptar e imprimir comanda</button>':'Tu rol permite consultar; un encargado debe aceptar.')+'</p></article>').join('')||'<p>No hay pedidos web pendientes.</p>';
  }
  document.addEventListener('click',async event=>{
    const open=event.target.closest('[data-incoming-open]');if(open){incoming.showModal();try{await loadIncoming();}catch(e){incoming.querySelector('[data-incoming-message]').textContent=e.message;}}
    const button=event.target.closest('[data-accept-order]');if(!button||button.disabled)return;button.disabled=true;incomingBusy=true;
    try{const out=await api('api/incoming180',{order_id:button.dataset.acceptOrder});incoming.querySelector('[data-incoming-message]').textContent=out.message;await loadIncoming();await (window.fisitaapWindows?.flushPrint||window.fisitaapAndroid?.flushPrint)?.();changed=true;await poll();}
    catch(e){incoming.querySelector('[data-incoming-message]').textContent=e.message;button.disabled=false;}finally{incomingBusy=false;}
  });
  const checkout=document.querySelector('#checkoutForm');
  if(checkout){
    checkout.elements.address_reference?.closest('.form-group')?.remove();
    const maps=document.createElement('div');maps.className='form-group';maps.innerHTML='<label>Ubicación de Google Maps</label><input class="input" type="url" name="maps_url" placeholder="Pega el enlace de Compartir → Copiar enlace"><div class="op180-map-actions"><button class="btn btn-light" type="button" data-current-location>Usar mi ubicación actual</button><button class="btn btn-light" type="button" data-search-map>Buscar en Google Maps</button></div><small data-map-message role="status"></small>';
    checkout.elements.address?.closest('.form-group')?.after(maps);
    maps.querySelector('[data-current-location]').onclick=()=>{
      const message=maps.querySelector('[data-map-message]');if(!navigator.geolocation){message.textContent='Puedes buscar tu ubicación en Maps y pegar el enlace.';return;}
      message.textContent='Solicitando ubicación. Permite el acceso cuando el navegador lo indique.';
      navigator.geolocation.getCurrentPosition(position=>{checkout.elements.maps_url.value='https://www.google.com/maps?q='+position.coords.latitude.toFixed(7)+','+position.coords.longitude.toFixed(7);message.textContent='Ubicación cargada. Completa la dirección escrita para facilitar la entrega.';},()=>{message.textContent='No se obtuvo la ubicación. Puedes buscarla en Maps o pegar el enlace.';},{enableHighAccuracy:true,timeout:15000,maximumAge:30000});
    };
    maps.querySelector('[data-search-map]').onclick=()=>window.open('https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(checkout.elements.address.value||'mi ubicación'),'_blank','noopener,noreferrer');
    let addressSelect=checkout.querySelector('[data-saved-address]');if(!addressSelect){const group=document.createElement('div');group.className='form-group';group.innerHTML='<label>Direcciones guardadas para este teléfono</label><select class="input" data-saved-address name="address_id"><option value="">Escribir una dirección nueva</option></select>';maps.before(group);addressSelect=group.querySelector('select');}addressSelect.name='address_id';
    let addresses=[];let addressTimer;
    const phone=checkout.elements.guest_phone;
    const lookup=async()=>{const value=phone?.value||'';if(value.replace(/\D/g,'').length<8)return;try{const out=await api('api/delivery-addresses180',{tenant:checkout.elements.tenant.value,phone:value});addresses=out.addresses;addressSelect.innerHTML='<option value="">Escribir una dirección nueva</option>'+addresses.map(a=>'<option value="'+Number(a.id)+'">'+esc(a.label)+(a.is_default?' · Principal':'')+' · '+esc(a.address)+'</option>').join('');}catch(_){}};
    window.op180RefreshAddresses=lookup;
    phone?.addEventListener('input',()=>{clearTimeout(addressTimer);addressTimer=setTimeout(lookup,550);});phone?.addEventListener('blur',lookup);if(phone?.value)lookup();
    addressSelect.onchange=()=>{const a=addresses.find(a=>String(a.id)===addressSelect.value);if(a){checkout.elements.address.value=a.address;checkout.elements.maps_url.value=a.maps_url180||'';}};
    const proof=document.createElement('div');proof.className='form-group';proof.hidden=true;proof.innerHTML='<label>Comprobante de pago SINPE <span aria-hidden="true">*</span></label><input class="input" type="file" name="sinpe_proof" accept="image/jpeg,image/png,image/webp"><input type="hidden" name="proof_token"><small>Adjunta la imagen para poder enviar el pedido. JPG, PNG o WebP, máximo 6 MB.</small>';checkout.elements.payment.closest('.form-group').after(proof);
    const payment=()=>{const sinpe=/sinpe/i.test(checkout.elements.payment.value);proof.hidden=!sinpe;proof.querySelector('[type=file]').required=sinpe;};checkout.elements.payment.addEventListener('change',payment);payment();
    window.op180PrepareCheckout=async()=>{
      if(!/sinpe/i.test(checkout.elements.payment.value))return;
      const file=proof.querySelector('[type=file]').files[0];if(!file)throw new Error('Adjunta el comprobante SINPE.');if(file.size>6*1024*1024)throw new Error('El comprobante debe ocupar hasta 6 MB.');
      if(!checkout.elements.proof_token.value){const data=new FormData();data.set('csrf',csrf());data.set('tenant',checkout.elements.tenant.value);data.set('checkout_key',checkout.elements.checkout_key.value);data.set('proof',file);const out=await api('api/order-proof180',data);checkout.elements.proof_token.value=out.token;}
    };
    proof.querySelector('[type=file]').addEventListener('change',()=>checkout.elements.proof_token.value='');
  }
  window.op180ShowConfirmation=out=>{
    if(out.demo)return false;let dialog=document.querySelector('#op180Confirmation');if(!dialog){dialog=document.createElement('dialog');dialog.id='op180Confirmation';dialog.className='qa180-dispatch-dialog';document.body.append(dialog);}
    dialog.innerHTML='<h2>Tu pedido fue enviado</h2><p>El negocio lo recibirá para aceptar y preparar.</p>'+(out.delivery_pin?'<div class="op180-pin"><small>PIN PARA RECIBIR TU PEDIDO</small><strong>'+esc(out.delivery_pin)+'</strong><p>Compártelo con el motorizado al recibir el pedido.</p></div>':'')+'<p><a class="btn" href="'+esc(out.tracking_url)+'">Ver estado y conservar mi PIN</a></p>'+(out.whatsapp?'<p><a class="btn btn-light" target="_blank" rel="noopener noreferrer" href="'+esc(out.whatsapp)+'">Compartir pedido por WhatsApp</a></p>':'')+'<button class="btn btn-light" type="button" data-dialog-close>Seguir comprando</button>';
    window.op180RefreshAddresses?.();
    if(checkout){checkout.elements.proof_token.value='';checkout.querySelector('[type=file]').value='';}dialog.showModal();return true;
  };
  poll();
})();
