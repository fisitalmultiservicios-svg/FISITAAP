(()=>{
  'use strict';
  const context=window.FISITAAP180;
  if(!context)return;
  const money=value=>'₡'+Number(value).toLocaleString('es-CR',{minimumFractionDigits:2,maximumFractionDigits:2});
  for(const select of document.querySelectorAll('[data-searchable-select]')){
    const search=document.createElement('input');search.type='search';search.className='input';search.placeholder='Buscar en esta lista…';search.setAttribute('aria-label','Buscar productos para asignar');select.before(search);
    search.addEventListener('input',()=>{const term=search.value.toLocaleLowerCase('es');for(const option of select.options)option.hidden=!option.selected&&!option.text.toLocaleLowerCase('es').includes(term);});
  }
  const combos=document.querySelector('[data-combo180-lines]');
  if(combos){
    let index=combos.querySelectorAll('[data-combo180-line]').length;
    const refresh=()=>{let cost=0;for(const row of combos.children){const select=row.querySelector('select'),qty=row.querySelector('input[type=number]');cost+=Number(select.selectedOptions[0]?.dataset.cost||0)*Number(qty.value||0);}document.querySelector('[data-combo180-cost]').textContent=money(cost);};
    combos.addEventListener('input',refresh);combos.addEventListener('change',refresh);
    document.querySelector('[data-combo180-add]').addEventListener('click',()=>{const row=combos.firstElementChild.cloneNode(true);for(const el of row.querySelectorAll('[name]'))el.name=el.name.replace(/component_lines\[\d+\]/,'component_lines['+(index)+']');row.querySelector('select').value='0';row.querySelector('input').value='1';combos.append(row);index++;refresh();});
    combos.addEventListener('click',event=>{if(event.target.closest('[data-combo180-remove]')){const row=event.target.closest('[data-combo180-line]');if(combos.children.length>1)row.remove();else{row.querySelector('select').value='0';row.querySelector('input').value='1';}refresh();}});refresh();
  }
  const purchases=document.querySelector('[data-purchase180-lines]');
  const editing=new URL(location.href).searchParams.get('edit');
  if(location.pathname.endsWith('/admin/categorias')&&/^\d+$/.test(editing||'')){
    const form=document.querySelector('.admin-card form[method="post"]');if(form){const link=document.createElement('a');link.className='btn btn-light';link.textContent='Extras y opcionales de esta categoría';link.href=context.base.replace(/\/$/,'')+'/admin/categoria-extras?id='+editing;form.before(link);}
  }
  if(location.pathname.endsWith('/admin/producto-extras')){
    const id=new URL(location.href).searchParams.get('id'),nav=document.querySelector('.qa180-tabs');if(nav&&/^\d+$/.test(id||'')){const link=document.createElement('a');link.className='btn btn-light';link.textContent='Variantes: talla, color, modelo';link.href=context.base.replace(/\/$/,'')+'/admin/opciones?product='+id;nav.append(link);}
  }
  document.querySelector('[data-add-purchase180-line]')?.addEventListener('click',()=>{
    const rows=[...purchases.querySelectorAll('tr')].filter(row=>row.querySelector('select[name^="line["]'));
    const source=rows[0];if(!source)return;const row=source.cloneNode(true);const indices=[...purchases.querySelectorAll('[name]')].map(el=>Number(el.name.match(/^line\[(\d+)\]/)?.[1]??-1));const index=Math.max(-1,...indices)+1;
    for(const el of row.querySelectorAll('[name]'))el.name=el.name.replace(/^line\[\d+\]/,'line['+index+']');row.querySelector('select').value='0';for(const input of row.querySelectorAll('input'))input.value=input.name.includes('quantity')?'1':'0';purchases.append(row);
  });
  for(const form of document.querySelectorAll('[data-cash-counter]')){
    const refresh=()=>{
      let cents=0;
      for(const input of form.querySelectorAll('[data-denomination]'))cents+=Math.max(0,Math.trunc(Number(input.value)||0))*Number(input.dataset.denomination)*100;
      cents+=Math.round((Number(form.elements.other_cash?.value)||0)*100);
      form.querySelector('[data-counted]').textContent=money(cents/100);
      form.querySelector('[data-difference]').textContent=money((cents-Number(form.dataset.expected))/100);
    };
    form.addEventListener('input',refresh);refresh();
  }
  document.querySelector('[data-printers-native]')?.addEventListener('click',async()=>{try{const getter=window.fisitaapWindows?.printerInfo||window.fisitaapAndroid?.printerInfo;if(!getter)throw new Error('Abre FISITAAP Windows o Android para buscar impresoras.');const result=await getter(),printers=Array.isArray(result)?result:result.printers||[],list=document.querySelector('#qa180NativePrinters');list.replaceChildren();for(const p of printers){const option=document.createElement('option');option.value=p.name;list.append(option);}document.querySelector('[data-native-status]').textContent=printers.length+' impresoras encontradas en este equipo. Guarda la selección y prueba cada punto.';}catch(error){document.querySelector('[data-native-status]').textContent=error.message;}});
  for(const button of document.querySelectorAll('[data-print-test]'))button.addEventListener('click',async()=>{button.disabled=true;try{const response=await fetch(context.base.replace(/\/$/,'')+'/api/native/print-test',{method:'POST',headers:{Accept:'application/json'},body:new URLSearchParams({csrf:window.FISITAPP.csrf,point:button.dataset.printTest})}),out=await response.json();if(!response.ok||!out.ok)throw new Error(out.error||'No se guardó la prueba.');const flush=window.fisitaapWindows?.flushPrint||window.fisitaapAndroid?.flushPrint;if(flush)await flush();document.querySelector('[data-native-status]').textContent='Prueba guardada. Revisa la impresora configurada y los últimos envíos.';}catch(error){document.querySelector('[data-native-status]').textContent=error.message;}finally{button.disabled=false;}});
  const trackPayment=window.fisitaapWindows?.trackWebPayment||window.fisitaapAndroid?.trackWebPayment;
  if(trackPayment)document.addEventListener('submit',event=>{const form=event.target;if(!form.matches('[data-r3-collect]')||form.dataset.nativeTracked==='yes')return;event.preventDefault();event.stopImmediatePropagation();if(form.dataset.nativeTracking==='yes')return;form.dataset.nativeTracking='yes';trackPayment({key:form.elements.payment_key.value,user_id:context.user_id}).then(()=>{form.dataset.nativeTracked='yes';form.requestSubmit();}).catch(error=>{form.querySelector('[data-payment-message]').textContent=error.message;}).finally(()=>{delete form.dataset.nativeTracking;});},true);
  const flushPrint=window.fisitaapWindows?.flushPrint||window.fisitaapAndroid?.flushPrint;if(flushPrint){flushPrint().catch(()=>{});setInterval(()=>flushPrint().catch(()=>{}),15000);}
  const authorize=window.fisitaapWindows?.authorize||window.fisitaapAndroid?.authorize;
  if(authorize&&!context.user_id)authorize({logout:true}).catch(()=>{});
  if(authorize&&context.nonce&&context.user_id){
    const connect=async()=>{
      try{
        const result=await authorize({nonce:context.nonce,user_id:context.user_id});
        if(!result?.ok)throw new Error(result?.error||'Este equipo necesita una caja autorizada.');
        const body=new URLSearchParams({csrf:window.FISITAPP.csrf,proof:result.proof});
        const response=await fetch(context.base.replace(/\/$/,'')+'/api/native/bind',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body});
        if(response.redirected||!(response.headers.get('content-type')||'').includes('application/json'))throw new Error('Vuelve a iniciar sesión para autorizar esta caja.');
        const bound=await response.json();if(!response.ok||!bound.ok){if(bound.redirect){const target=new URL(bound.redirect,location.href);if(target.origin===location.origin)location.assign(target.href);}throw new Error(bound.error||'No se confirmó la autorización de caja.');}
        await authorize({nonce:context.nonce,user_id:context.user_id,confirm:true});
        if(!context.native&&bound.device_id)location.reload();
      }catch(error){
        for(const el of document.querySelectorAll('[data-native-status]'))el.textContent=error.message;
      }
    };
    connect();setInterval(connect,5*60*1000);
  }
})();
