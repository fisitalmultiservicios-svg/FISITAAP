(()=>{
 'use strict';
 const terminal=document.querySelector('.r3-terminal'),main=terminal?.querySelector('.r3-main');
 if(!terminal||!main||!document.body.classList.contains('r14-app'))return;
 terminal.classList.add('r14-terminal');
 const grid=main.querySelector('[data-r14-pos]'),floor=main.querySelector('[data-r3-floor]:not([data-layout-editor])');
 if(grid)terminal.classList.add('r14-pos-terminal');
 if(floor||main.querySelector('[data-basic-tables]'))terminal.classList.add('r14-floor-terminal');
 if(!grid&&!floor&&!main.querySelector('[data-basic-tables]'))return;
 const tools=document.createElement('div');tools.className='r14-tools';
 tools.innerHTML='<span>'+(floor?'Plano de mesas':'Tu espacio de trabajo')+'</span><button type="button" data-r14-fit>Ajustar</button><button type="button" data-r14-zoom="out" aria-label="'+(floor?'Alejar plano':'Reducir texto')+'">−</button><output aria-label="Nivel de '+(floor?'zoom':'texto')+'">100 %</output><button type="button" data-r14-zoom="in" aria-label="'+(floor?'Acercar plano':'Ampliar texto')+'">+</button><button type="button" data-r14-full aria-label="Pantalla completa">⛶</button>';
 main.prepend(tools);
 let scale=1;
 function applyScale(){
  tools.querySelector('output').textContent=Math.round(scale*100)+' %';
  if(floor){const svg=floor.querySelector('svg');svg.style.width=scale*100+'%';svg.style.height=scale*100+'%';}
  else terminal.style.setProperty('--r14-text-scale',scale);
 }
 tools.addEventListener('click',async event=>{
  const b=event.target.closest('button');if(!b)return;
  if(b.hasAttribute('data-r14-full')){try{if(document.fullscreenElement)await document.exitFullscreen();else if(terminal.requestFullscreen)await terminal.requestFullscreen();else tools.querySelector('output').textContent='Usa pantalla completa del navegador';}catch(_){tools.querySelector('output').textContent='No disponible';}return;}
  scale=b.hasAttribute('data-r14-fit')?1:Math.max(.9,Math.min(floor?2:1.2,Math.round((scale+(b.dataset.r14Zoom==='in'?.1:-.1))*10)/10));applyScale();
 });
 if(grid){
  const key='fisitaap-r14-panel:'+grid.dataset.r14Ticket;
  let panel='invoice';try{const saved=sessionStorage.getItem(key);if(['invoice','quick'].includes(saved))panel=saved;}catch(_){}
  const tabs=document.createElement('nav');tabs.className='r14-pos-tabs';tabs.setAttribute('aria-label','Área de Venta rápida');
  tabs.innerHTML='<button type="button" data-r14-panel-button="invoice" aria-controls="r14Invoice">Facturación</button><button type="button" data-r14-panel-button="quick" aria-controls="r14Quick">Productos</button>';
  grid.querySelector('.r3-cart-panel').id='r14Invoice';grid.querySelector('.r3-quick').id='r14Quick';grid.before(tabs);
  function show(value){panel=value;grid.dataset.r14Panel=value;for(const b of tabs.children)b.setAttribute('aria-selected',String(b.dataset.r14PanelButton===value));try{sessionStorage.setItem(key,value);}catch(_){}}
  tabs.addEventListener('click',event=>{const b=event.target.closest('[data-r14-panel-button]');if(b)show(b.dataset.r14PanelButton);});show(panel);
  // Search targets billing. Keep the target visible when a keyboard shortcut focuses it.
  grid.addEventListener('focusin',event=>{if(event.target.closest('.r3-cart-panel')&&panel!=='invoice')show('invoice');});
 }
 if(floor){
  // Saved furniture/sector coordinates are left intact; keyboard activates the same action as touch.
  function decorateTables(){for(const t of floor.querySelectorAll('[data-r3-table]')){t.setAttribute('role','button');t.setAttribute('tabindex','0');t.setAttribute('aria-label',t.querySelector('text')?.textContent?'Mesa '+t.querySelector('text').textContent:'Abrir mesa');}}
  decorateTables();document.addEventListener('op180:updated',decorateTables);
  floor.addEventListener('keydown',event=>{const t=event.target.closest('[data-r3-table]');if(t&&['Enter',' '].includes(event.key)){event.preventDefault();t.dispatchEvent(new MouseEvent('click',{bubbles:true}));}});
 }
})();
