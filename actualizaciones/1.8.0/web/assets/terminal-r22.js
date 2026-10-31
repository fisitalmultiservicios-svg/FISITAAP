(() => {
 'use strict';
 if(!document.body.classList.contains('r15-app'))return;
 const $=(s,r=document)=>r.querySelector(s),main=$('[data-r15-page]'),grid=$('[data-r14-pos]'),floor=$('[data-r3-floor]:not([data-layout-editor])');
 if(!main)return;
 if(grid||floor){
  document.body.classList.add('r22-terminal');main.classList.add(grid?'r22-pos':'r22-floor');
  const heading=$('.page-heading',main),cash=$('.r19-cash-bar',main),bar=document.createElement('div');bar.className='r22-workspace-heading';
  if(heading){heading.before(bar);bar.append(heading);}if(cash)bar.append(cash);
  if(grid){
   main.parentElement.classList.add('r22-fixed-viewport');
   const tools=$('.r19-sale-tools',main);if(tools)tools.style.removeProperty('position');
   // R18 writes a content-derived fixed height. The R22 flex frame owns its size.
   main.style.removeProperty('--r18-panel-height');
   for(const [selector,label] of [['.product-grid','Productos de carga rápida'],['.r3-sale-table-wrap','Productos de la cuenta'],['.quick-categories','Categorías']]){
    const region=$(selector,grid);if(region){region.setAttribute('tabindex','0');region.setAttribute('aria-label',label);}
   }
  }
 }
 // Keep the payment header/actions outside the only scrolling part of the modal.
 const payment=$('[data-payment-modal]'),form=payment?.querySelector('[data-r3-collect]');
 if(form){
  payment.classList.add('r22-payment');const header=$(':scope > header',form),footer=$(':scope > footer',form),body=document.createElement('div');body.className='r22-payment-body';
  for(const child of [...form.childNodes])if(child!==header&&child!==footer)body.append(child);
  if(header)form.append(header);form.append(body);if(footer)form.append(footer);
  const title=$('h2',header||form);if(title){title.id='r22PaymentTitle';payment.setAttribute('aria-labelledby',title.id);}
 }
 const native=window.fisitaapWindows,buttons=[...document.querySelectorAll('[data-r15-full]')];let full=false;
 const render=value=>{full=!!value;for(const b of buttons){b.setAttribute('aria-pressed',String(full));b.setAttribute('aria-label',full?'Salir de pantalla completa':'Pantalla completa');b.title=full?'Salir de pantalla completa':'Pantalla completa';}};
 if(native?.windowState&&native?.setFullscreen){
  native.windowState().then(s=>render(s.fullscreen)).catch(()=>{});
  native.onWindowState?.(s=>render(s.fullscreen));
 }
 for(const button of buttons)button.addEventListener('click',async()=>{
  button.disabled=true;
  try{
   if(native?.setFullscreen){const state=await native.setFullscreen(!full);render(state.fullscreen);}
   else if(document.fullscreenElement)await document.exitFullscreen();
   else await document.documentElement.requestFullscreen({navigationUI:'hide'});
  }catch(_){button.title='En navegador usa F11 para mantener pantalla completa al cambiar de página';}
  finally{button.disabled=false;}
 });
 if(!native?.setFullscreen)document.addEventListener('fullscreenchange',()=>render(!!document.fullscreenElement));
})();
