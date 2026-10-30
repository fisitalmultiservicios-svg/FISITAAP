(() => {
 'use strict';
 const $=(s,r=document)=>r.querySelector(s),all=(s,r=document)=>[...r.querySelectorAll(s)],base=String(window.FISITAPP?.base||'/').replace(/\/$/,'');
 const inputField=(form,name,value)=>{let e=form.querySelector('[name="'+name+'"]');if(!e){e=document.createElement('input');e.type='hidden';e.name=name;form.append(e);}e.value=value;};
 // Connected login directories are authenticated by the installed device, never by a public store ID.
 if(location.pathname.endsWith('/owner-login')){
  const bridge=window.fisitaapWindows||window.fisitaapAndroid,nonce=window.FISITAAP180?.directory_nonce_r19;
  if(bridge?.authorize&&nonce)(async()=>{try{
   let cached;try{cached=await bridge.loginUsers?.();}catch(_){}const proof=await bridge.authorize({nonce,user_id:Number(cached?.users?.[0]?.id)||1});
   const response=await fetch(base+'/api/staff-login-r19',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:new URLSearchParams({csrf:window.FISITAPP.csrf,proof:proof.proof})});const out=await response.json();if(!response.ok||!out.ok)return;
   const email=$('form [name=email]');if(!email)return;let select=$('#nativeLoginUser');if(!select){select=document.createElement('select');select.id='nativeLoginUser';select.className='input';email.before(select);}const selected=email.value;select.replaceChildren(new Option('Elige tu usuario · '+out.business,''));for(const u of out.users)select.add(new Option((u.username_r17||u.name)+(u.username_r17?' · '+u.name:''),u.email));select.add(new Option('Otro acceso','other'));select.required=true;select.setAttribute('aria-label','Usuario del negocio');select.dataset.r19Live='yes';email.type='hidden';email.required=false;
   const old=select.cloneNode(true);select.replaceWith(old);select=old;select.value=out.users.some(u=>u.email===selected)?selected:'';select.addEventListener('change',()=>{email.type=select.value==='other'?'text':'hidden';email.value=select.value==='other'?'':select.value;email.required=select.value==='other';(select.value==='other'?email:email.form.elements.password).focus();});
  }catch(_){/* The safe cached selector remains usable if the connection is unavailable. */}})();
 }
 if(!document.body.classList.contains('r15-app'))return;
 const main=$('[data-r15-page]'),grid=$('[data-r14-pos]'),table=grid?.hasAttribute('data-r19-table'),search=grid?$('.r3-search [name=q]',grid):null;
 // All sale actions stay visible above the workspace, including on a phone.
 all('[data-r15-more]').forEach(el=>el.remove());
 if(main){
  let tools=$('.r3-sale-actions',main);if(!tools){tools=document.createElement('nav');tools.className='r3-sale-actions';}tools.classList.add('r19-sale-tools');tools.setAttribute('aria-label','Acciones de venta');
  for(const detail of all('details.r15-floor-actions',main)){const nav=$('.r3-pos-nav',detail);if(nav)tools.append(nav);detail.remove();}
  const accounts=$('.r15-open-accounts',tools);if(accounts){if(table)accounts.remove();else{accounts.className='r19-open-accounts';grid?.before(accounts);}}
  if(grid){const hold=$('.ticket-actions>.hstack form',grid);if(hold)tools.append(hold);grid.before(tools);}
  else if(tools.children.length){const cash=$('.r19-cash-bar',main);(cash||$('.page-heading',main))?.after(tools);}
  if(tools.children.length){new ResizeObserver(()=>main.style.setProperty('--r19-tools-height',tools.getBoundingClientRect().height+'px')).observe(tools);}
 }
 const restoreKey=grid?'r19-scroll:'+grid.dataset.r14Ticket:null;
 if(grid){
  main.classList.add('r19-sale-view');const launcher=$('.fc-launch');if(launcher){launcher.classList.add('r19-inline-chat');launcher.setAttribute('aria-label','Abrir FISIChat');$('.page-heading .toolbar',main)?.append(launcher);}if(table){main.classList.add('r19-table-view');const back=$('.r18-back-tables',main);if(back){back.classList.remove('r18-back-tables');$('.r19-sale-tools',main)?.prepend(back);}$('.pos-mobile-tabs',main)?.remove();$('.ticket-head form',grid)?.remove();
   const jump=document.createElement('nav');jump.className='r19-table-jump';jump.setAttribute('aria-label','Atender la mesa');for(const [selector,label] of [['.pos-products','Productos'],['.ticket','Cuenta · '+($('.ticket-summary .total strong',grid)?.textContent||'')]]){const b=document.createElement('button');b.type='button';b.textContent=label;b.className='btn';b.onclick=()=>$(selector,grid)?.scrollIntoView({block:'start',behavior:'smooth'});jump.append(b);}main.append(jump);
  }
  document.addEventListener('submit',event=>{
   const f=event.target;if(!(f instanceof HTMLFormElement)||f.method.toLowerCase()!=='post')return;const target=new URL(f.getAttribute('action')||location.href,location.href),same=target.pathname===location.pathname&&target.searchParams.get('ticket')===grid.dataset.r14Ticket;if(!same&&!f.elements.start_product)return;
   inputField(f,'return_query_r19',search?.value||'');const category=new URL(location.href).searchParams.get('category');if(category)inputField(f,'return_category_r19',category);
   const viewport=$('#r15Viewport');try{sessionStorage.setItem(grid.dataset.r14Ticket.startsWith('new-')?'r19-new-scroll':restoreKey,JSON.stringify({user:window.FISITAAP180?.user_id,top:viewport?.scrollTop||0,products:$('.pos-products',grid)?.scrollTop||0,query:search?.value||''}));}catch(_){}
  },true);
  let saved;try{saved=JSON.parse(sessionStorage.getItem(restoreKey)||sessionStorage.getItem('r19-new-scroll')||'null');sessionStorage.removeItem(restoreKey);sessionStorage.removeItem('r19-new-scroll');if(saved?.user!==window.FISITAAP180?.user_id)saved=null;}catch(_){}
  if(saved)requestAnimationFrame(()=>{const viewport=$('#r15Viewport');if(viewport)viewport.scrollTop=saved.top;const products=$('.pos-products',grid);if(products)products.scrollTop=saved.products;});
  const canFocus=()=>!$('dialog[open]')&&!$('[data-fc]:not([hidden])')&&!$('[data-admin-menu].admin-menu-open');
  const focusSearch=()=>{if(!search||table||!canFocus())return;const active=document.activeElement;if(active&&active!==document.body&&active!==search&&active.matches('input,select,textarea,[contenteditable=true]'))return;if(innerWidth<=650)$('[data-r15-panel=products]')?.click();search.focus({preventScroll:true});search.select();};
  if(!table)setTimeout(focusSearch,80);
  document.addEventListener('keydown',e=>{if(e.ctrlKey||e.metaKey||e.altKey||e.isComposing||e.key.length!==1||!canFocus())return;const active=document.activeElement;if(active?.matches('input,select,textarea,[contenteditable=true]'))return;focusSearch();});
  document.addEventListener('click',e=>{if(!e.target.closest('button,a,input,select,textarea,form,dialog,[data-fc],[data-admin-menu]'))focusSearch();});
  all('dialog').forEach(dialog=>dialog.addEventListener('close',()=>setTimeout(focusSearch,20)));
 }
 const status=$('[data-r19-table-status]');if(status){let busy=false;const poll=async()=>{if(busy||document.hidden)return;busy=true;try{const response=await fetch(base+'/api/table-state-r19?ticket='+encodeURIComponent(status.dataset.ticket),{credentials:'same-origin',headers:{Accept:'application/json'}});if(response.ok){const out=await response.json();if(out.ok){$('[data-r19-preparation]',status).textContent=out.label;$('[data-r19-command-count]',status).textContent=out.pending+' por enviar a comanda';}}}catch(_){}finally{busy=false;}};poll();setInterval(poll,2000);}
 // Refresh the attendance selector from the current business directory on each opening.
 const clock=$('.hr17-clock-dialog');
 if(clock)$('.hr17-clock-button')?.addEventListener('click',async()=>{try{const r=await fetch(base+'/api/staff-r18',{credentials:'same-origin',headers:{Accept:'application/json'}});if(!r.ok)return;const out=await r.json();if(!out.ok)return;const clockSelect=clock.querySelector('select[aria-label="Usuario que marca"]');if(!clockSelect)return;clockSelect.replaceChildren(new Option('Selecciona tu usuario',''));for(const u of out.users)clockSelect.add(new Option((u.username_r17||u.name)+(u.username_r17?' · '+u.name:''),u.email));clockSelect.focus();}catch(_){}});
})();
