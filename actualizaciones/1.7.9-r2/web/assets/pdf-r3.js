'use strict';
document.addEventListener('DOMContentLoaded', () => {
  const $ = (s, root = document) => root.querySelector(s);
  const $$ = (s, root = document) => [...root.querySelectorAll(s)];
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const money = cents => '₡' + (Number(cents || 0) / 100).toLocaleString('es-CR', {minimumFractionDigits:2, maximumFractionDigits:2});
  const cents = value => Math.round(Number(value || 0) * 100);
  const json = selector => { const node=$(selector);return node ? JSON.parse(node.textContent) : null; };
  const uid = () => {if(crypto.randomUUID)return crypto.randomUUID();const b=crypto.getRandomValues(new Uint8Array(16));b[6]=(b[6]&15)|64;b[8]=(b[8]&63)|128;const h=[...b].map(n=>n.toString(16).padStart(2,'0')).join('');return h.slice(0,8)+'-'+h.slice(8,12)+'-'+h.slice(12,16)+'-'+h.slice(16,20)+'-'+h.slice(20);};
  const open = id => { const dialog=document.getElementById(id);if(dialog&&!dialog.open)dialog.showModal();return dialog; };
  $$('[data-dialog-open]').forEach(button=>button.addEventListener('click',()=>{
    const dialog=open(button.dataset.dialogOpen);if(button.dataset.dialogOpen==='r3Table'){const f=$('form',dialog);f.reset();f.elements.table_id.value='0';$('[data-remove-table]',dialog).hidden=true;}
    if(button.hasAttribute('data-new-customer-open')){const checkbox=$('[data-new-customer]',dialog);if(checkbox){checkbox.checked=true;checkbox.dispatchEvent(new Event('change'));}}
  }));
  $$('[data-dialog-close]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog').close()));
  $$('[data-quantity-step]').forEach(button=>button.addEventListener('click',()=>{
    const form=button.closest('form'),input=form.elements.quantity,step=Number(input.step)||1;
    input.value=String(Math.max(0,Math.round((Number(input.value)+Number(button.dataset.quantityStep)*step)*1000)/1000));form.requestSubmit();
  }));
  document.addEventListener('keydown',event=>{
    if(event.key==='F2'){event.preventDefault();$('.r3-search [name=q]')?.focus();}
    if(event.key==='F4'){event.preventDefault();open('r3Customer');}
    if(event.key==='F8'){event.preventDefault();if($('[data-r3-collect]'))$('[data-confirm-payment]')?.click();else $('.r3-charge-box a')?.click();}
  });
  const lineItems=json('[data-r3-items]');
  if(lineItems)$$('[data-line-edit]').forEach(button=>button.addEventListener('click',async()=>{
    const item=lineItems.find(i=>Number(i.id)===Number(button.dataset.lineEdit));if(!item)return;
    const dialog=open('r3LineEdit'),form=$('form',dialog),fields=$('[data-line-options]',dialog);form.elements.item_id.value=item.id;form.elements.quantity.value=item.quantity;form.elements.notes.value=item.notes||'';
    fields.textContent='Cargando opciones…';$('button[type=submit]',dialog)?.setAttribute('disabled','');const save=$('footer button:last-child',dialog);save.disabled=true;
    try{
      const base=String(window.FISITAPP?.base||'/').replace(/\/$/,'');const response=await fetch(base+'/api/pos-options?product_id='+item.product_id,{headers:{Accept:'application/json'}}),out=await response.json();if(!out.ok)throw Error(out.error);
      const selected=new Set((JSON.parse(item.options_json||'[]')).map(o=>Number(o.id)));
      fields.innerHTML=out.groups.map(g=>'<fieldset><legend>'+esc(g.name)+'</legend>'+g.options.map(o=>'<label><input type="checkbox" name="option_ids[]" value="'+Number(o.id)+'" '+(selected.has(Number(o.id))?'checked':'')+'> '+esc(o.name)+(Number(o.price_delta)?' +'+money(cents(o.price_delta)):'')+'</label>').join('')+'</fieldset>').join('');save.disabled=false;
    }catch(error){fields.textContent=error.message||'No se pudieron cargar las opciones.';}
  }));
  let selectedLine=null;$$('[data-line]').forEach(row=>row.addEventListener('click',()=>{selectedLine=row;$$('[data-line]').forEach(r=>r.classList.toggle('r3-line-selected',r===row));}));document.addEventListener('keydown',e=>{if(e.key==='Delete'&&selectedLine&&!e.target.closest('input,textarea,select')){const button=selectedLine.querySelector('[aria-label^=Eliminar]');if(button&&!button.disabled){e.preventDefault();button.click();}}});
  const editId=new URL(location.href).searchParams.get('edit_item');if(editId)$('[data-line-edit="'+Number(editId)+'"]')?.click();
  const payment=$('[data-r3-collect]'),paymentData=json('[data-r3-payment-data]');
  if(payment&&paymentData) {
    const modal=$('[data-payment-modal]');if(modal.open)modal.removeAttribute('open');modal.showModal();
    let selectedMethod='cash',lastQuote=null,submitted=false;
    const amount=k=>cents(payment.elements[k].value);
    const customer=()=>Number(payment.elements.customer_id?.value||0);
    const profile=()=>paymentData.credit.find(c=>Number(c.user_id)===customer());
    function refreshPayment() {
      try {
        const quantities={};$$('[data-r3-pick]',payment).forEach(i=>quantities[i.dataset.r3Pick]=Number(i.value));
        const mode=payment.elements.split_mode.value;
        const q=window.FisitaapPos.quote(paymentData,mode,quantities,Number(payment.elements.parts.value));lastQuote=q;
        const selected=$('[data-r3-selected-lines]',payment);selected.replaceChildren();
        for(const l of q.lines){const row=document.createElement('div');row.className='r3-selected-line';const label=document.createElement('span');label.textContent=l.qty+' × '+l.item.product_name;const price=document.createElement('b');price.textContent=money(l.total);row.append(label,price);if(mode==='items'){const remove=document.createElement('button');remove.type='button';remove.textContent='←';remove.setAttribute('aria-label','Dejar '+l.item.product_name+' pendiente');remove.onclick=()=>{$('[data-r3-pick="'+l.item.id+'"]',payment).value='0';refreshPayment();};row.append(remove);}selected.append(row);}
        const totals=$('[data-payment-quote]',payment);totals.className='r3-payment-quote';totals.replaceChildren();
        for(const [key,label] of [['net','Subtotal'],['tax','Impuestos'],['service','Servicio'],['discount','Descuento'],['total','Total de la venta']]){if(!q[key]&&key!=='total'&&key!=='net')continue;const row=document.createElement('p'),value=document.createElement('b');row.textContent=label;value.textContent=money(q[key]);row.append(value);if(key==='total')row.className='r3-grand-total';totals.append(row);}
        $('[data-pay-total]',payment).textContent=money(q.total);
        let gift=0;const code=payment.elements.gift_code.value.trim().toUpperCase();
        if(code){const card=paymentData.gifts.find(g=>g.code===code&&Number(g.customer_id)===customer());gift=amount('gift_amount');if(!card)throw Error('La tarjeta de regalo no está disponible para este cliente.');if(!gift){gift=Math.min(cents(card.balance),q.total);payment.elements.gift_amount.value=(gift/100).toFixed(2);}if(gift>cents(card.balance))throw Error('El monto de regalo supera el saldo de la tarjeta.');$('[data-gift-status]').textContent='Saldo disponible: '+money(cents(card.balance));}
        const noncash=amount('card')+amount('sinpe')+amount('credit')+gift,cash=amount('cash'),change=cash-(q.total-noncash),missing=Math.max(0,-change);
        $('[data-pay-received]',payment).textContent=money(cash);$('[data-pay-change]',payment).textContent=money(Math.max(0,change));$('[data-pay-change-summary]',payment).textContent=money(Math.max(0,change));$('[data-pay-missing]',payment).textContent='Saldo pendiente: '+money(missing);
        const cp=profile(),creditAllowed=cp&&Number(cp.credit_enabled)&&cents(cp.credit_limit)-cents(cp.current_balance)>0;
        $('[data-payment-method=credit]',payment).disabled=!creditAllowed;
        if(amount('credit')&&(!creditAllowed||amount('credit')>cents(cp.credit_limit)-cents(cp.current_balance)))throw Error('El cliente no tiene crédito suficiente para este cobro.');
        const valid=q.total>0&&noncash<=q.total&&missing===0;const message=noncash>q.total?'Los pagos superan el cobro.':missing?'Falta pagar '+money(missing):'';
        $('[data-payment-message]',payment).textContent=message;$('[data-confirm-payment]',payment).disabled=!valid||submitted;
      }catch(error){$('[data-payment-message]',payment).textContent=error.message||'Revisa los datos de cobro.';$('[data-confirm-payment]',payment).disabled=true;}
    }
    function method(key) {
      selectedMethod=key;$$('[data-payment-method]',payment).forEach(b=>b.classList.toggle('active',b.dataset.paymentMethod===key));
      const q=lastQuote?.total||0;for(const k of ['cash','card','sinpe','credit'])payment.elements[k].value=key===k?(q/100).toFixed(2):'0';
      payment.elements.gift_code.value='';payment.elements.gift_amount.value='0';$('[data-gift-status]',payment).textContent='';
      $$('[data-pay-field]',payment).forEach(el=>el.hidden=key!=='mixed'&&el.dataset.payField!==key);
      $('[data-payment-title]',payment).textContent={cash:'Pago en efectivo',card:'Pago con tarjeta',sinpe:'Pago con SINPE',mixed:'Pago mixto',credit:'Pago a crédito',gift:'Tarjeta de regalo'}[key];
      $('.r3-cash-presets',payment).hidden=!['cash','mixed'].includes(key);$('.r3-change-panel',payment).hidden=!['cash','mixed'].includes(key);refreshPayment();
    }
    $$('[data-payment-method]',payment).forEach(b=>b.onclick=()=>method(b.dataset.paymentMethod));
    $$('[data-cash-amount]',payment).forEach(b=>b.onclick=()=>{payment.elements.cash.value=b.dataset.cashAmount;refreshPayment();});
    $('[data-cash-exact]',payment).onclick=()=>{payment.elements.cash.value=((lastQuote?.total||0)/100).toFixed(2);refreshPayment();};
    $('[data-split-toggle]',payment).onclick=()=>{$('[data-split-panel]',payment).hidden=!$('[data-split-panel]',payment).hidden;};
    if(new URL(location.href).searchParams.has('divide'))$('[data-split-panel]',payment).hidden=false;
    $('[data-split-none]',payment).onclick=()=>{payment.elements.split_mode.value='items';$$('[data-r3-pick]',payment).forEach(i=>i.value='0');refreshPayment();};
    $$('[data-split-move]',payment).forEach(b=>b.onclick=()=>{payment.elements.split_mode.value='items';const i=$('[data-r3-pick="'+b.dataset.splitMove+'"]',payment);i.value=Number(i.value)?'0':i.max;refreshPayment();});
    payment.addEventListener('input',refreshPayment);payment.addEventListener('change',event=>{
      if(event.target.name==='split_mode'||event.target.name==='parts'){
        refreshPayment();if(selectedMethod!=='mixed'&&['cash','card','sinpe','credit'].includes(selectedMethod))payment.elements[selectedMethod].value=((lastQuote?.total||0)/100).toFixed(2);
      }
      refreshPayment();
    });
    payment.addEventListener('submit',event=>{refreshPayment();if($('[data-confirm-payment]',payment).disabled){event.preventDefault();return;}submitted=true;$('[data-confirm-payment]',payment).disabled=true;$('[data-confirm-payment]',payment).textContent='Registrando cobro…';});
    modal.addEventListener('cancel',event=>{event.preventDefault();location.assign($('.r3-dialog-x',modal).href);});
    refreshPayment();method('cash');
  }
  $('[data-remove-table]')?.addEventListener('click',e=>{if(!confirm('¿Retirar esta mesa del salón? Su historial se conserva.'))return;const f=e.currentTarget.closest('form');f.elements.action.value='delete_table';f.requestSubmit();});
  const search=$('.r3-search');search?.addEventListener('submit',e=>{const q=search.elements.q.value.trim();const f=$$('.pos-product').find(f=>f.dataset.sku===q);if(q&&f){e.preventDefault();f.requestSubmit();}});
  const layoutData=json('[data-r3-layout-data]'),floor=$('[data-r3-floor]');
  if(layoutData) {
    let data=structuredClone(layoutData),dirty=false,selected=null,drag=null,snap=true,scale=1,undo=[],redo=[];
    const editor=Boolean(data.editor),svg=floor?.querySelector('svg'),templates=new Map();
    const stateMessage=$('[data-layout-message]');
    const capture=()=>JSON.stringify({tables:data.tables,objects:data.objects});
    const remember=()=>{undo.push(capture());if(undo.length>80)undo.shift();redo=[];dirty=true;};
    const rowFor=key=>key?.type==='table'?data.tables.find(r=>Number(r.id)===Number(key.id)):data.objects.find(r=>r.id===key?.id);
    if(svg) {
      $$('[data-layout-object]',svg).forEach(g=>{const row=data.objects.find(o=>o.id===g.dataset.layoutObject);if(row)templates.set(row.id,{html:g.innerHTML,w:Number(row.width),h:Number(row.height)});});
      const point=e=>{const p=svg.createSVGPoint();p.x=e.clientX;p.y=e.clientY;return p.matrixTransform(svg.getScreenCTM().inverse());};
      function updateSelection() {
        const group=$('[data-layout-selection]',svg),row=rowFor(selected);if(!group)return;
        group.hidden=!row;group.style.display=row?'':'none';
        if(row){group.setAttribute('transform','translate('+row.x+' '+row.y+')');const rect=$('rect',group);rect.setAttribute('width',row.width);rect.setAttribute('height',row.height);const handle=$('[data-layout-handle]',group);handle.setAttribute('x',Number(row.width)-6);handle.setAttribute('y',Number(row.height)-6);}
        const none=$('[data-layout-none]'),properties=$('[data-layout-properties]');if(none)none.hidden=Boolean(row);if(properties)properties.hidden=!row;
        if(row)$$('[data-layout-property]').forEach(input=>{input.value=row[input.dataset.layoutProperty]??'';input.disabled=input.dataset.layoutProperty==='label'&&selected.type==='table';});
      }
      function renderObject(row) {
        let g=$('[data-layout-object="'+row.id+'"]',svg);if(!g){g=document.createElementNS('http://www.w3.org/2000/svg','g');g.dataset.layoutObject=row.id;$('[data-layout-objects]',svg).append(g);}
        const template=templates.get(row.id);if(template)g.innerHTML='<g transform="scale('+Number(row.width)/template.w+' '+Number(row.height)/template.h+')">'+template.html+'</g>';
        g.setAttribute('transform','translate('+row.x+' '+row.y+') rotate('+row.rotation+' '+Number(row.width)/2+' '+Number(row.height)/2+')');
        if(row.kind==='text')$('text',g).textContent=row.label||'Texto';
      }
      function renderAll() {
        $$('[data-layout-object]',svg).forEach(g=>{if(!data.objects.some(r=>r.id===g.dataset.layoutObject))g.remove();});data.objects.forEach(renderObject);
        for(const table of data.tables){const g=$('[data-r3-table="'+table.id+'"]',svg);if(!g)continue;g.setAttribute('transform','translate('+table.x+' '+table.y+')');g.children[0].setAttribute('transform','rotate('+table.rotation+' '+Number(table.width)/2+' '+Number(table.height)/2+') scale('+Number(table.width)/120+' '+Number(table.height)/120+')');g.children[1]?.setAttribute('transform','translate('+(Number(table.width)/2-60)+' '+(Number(table.height)+5)+')');}
        updateSelection();
      }
      if(editor) {
        svg.addEventListener('pointerdown',e=>{
          const handle=e.target.closest('[data-layout-handle]'),g=e.target.closest('[data-layout-object],[data-r3-table]');
          if(!g&&!handle){selected=null;updateSelection();return;}
          e.preventDefault();if(g)selected=g.dataset.r3Table?{type:'table',id:Number(g.dataset.r3Table)}:{type:'object',id:g.dataset.layoutObject};
          const row=rowFor(selected),p=point(e);if(!row)return;remember();drag={start:p,x:Number(row.x),y:Number(row.y),w:Number(row.width),h:Number(row.height),resize:Boolean(handle),pointer:e.pointerId};svg.setPointerCapture(e.pointerId);updateSelection();
        });
        svg.addEventListener('pointermove',e=>{if(!drag)return;const row=rowFor(selected),p=point(e),round=n=>snap?Math.round(n/10)*10:Math.round(n),dx=p.x-drag.start.x,dy=p.y-drag.start.y;if(drag.resize){row.width=Math.max(selected.type==='table'?50:10,Math.min(1000-Number(row.x),round(drag.w+dx)));row.height=Math.max(selected.type==='table'?50:10,Math.min(650-Number(row.y),round(drag.h+dy)));}else{row.x=Math.max(0,Math.min(1000-Number(row.width),round(drag.x+dx)));row.y=Math.max(0,Math.min(650-Number(row.height),round(drag.y+dy)));}renderAll();});
        const stop=e=>{if(drag){if(svg.hasPointerCapture(drag.pointer))svg.releasePointerCapture(drag.pointer);drag=null;}};svg.addEventListener('pointerup',stop);svg.addEventListener('pointercancel',stop);
        $$('[data-layout-property]').forEach(input=>input.addEventListener('change',()=>{const row=rowFor(selected);if(!row)return;remember();const k=input.dataset.layoutProperty;row[k]=k==='label'?input.value.slice(0,120):Math.max(0,Math.min(k==='rotation'?359:k==='y'?650:1000,Number(input.value)||0));row.x=Math.min(1000-(selected.type==='table'?50:10),Number(row.x));row.y=Math.min(650-(selected.type==='table'?50:10),Number(row.y));row.width=Math.max(selected.type==='table'?50:10,Math.min(1000-Number(row.x),Number(row.width)));row.height=Math.max(selected.type==='table'?50:10,Math.min(650-Number(row.y),Number(row.height)));renderAll();}));
        $('[data-layout-delete]')?.addEventListener('click',()=>{
          if(!selected)return;if(selected.type==='table'){const row=rowFor(selected);const dialog=open('r3Table');const form=$('form',dialog);form.elements.table_id.value=row.id;form.elements.name.value=row.name;form.elements.sector_id.value=row.sector_id||0;form.elements.shape.value=row.shape;form.elements.capacity.value=row.capacity;form.elements.rotation.value=row.rotation;form.elements.x.value=row.x;form.elements.y.value=row.y;$('[data-remove-table]',dialog).hidden=false;return;}
          remember();data.objects=data.objects.filter(r=>r.id!==selected.id);selected=null;renderAll();
        });
        $('[data-layout-undo]')?.addEventListener('click',()=>{if(!undo.length)return;redo.push(capture());Object.assign(data,JSON.parse(undo.pop()));selected=null;dirty=true;renderAll();});
        $('[data-layout-redo]')?.addEventListener('click',()=>{if(!redo.length)return;undo.push(capture());Object.assign(data,JSON.parse(redo.pop()));selected=null;dirty=true;renderAll();});
        $('[data-layout-grid]')?.addEventListener('click',()=>{const grid=$('[data-layout-grid-bg]',svg);grid.style.display=grid.style.display==='none'?'':'none';});
        $('[data-layout-snap]')?.addEventListener('click',e=>{snap=!snap;e.currentTarget.classList.toggle('selected',snap);});
        $$('[data-layout-zoom]').forEach(button=>button.onclick=()=>{scale=Math.min(2,Math.max(.5,scale+Number(button.dataset.layoutZoom)*.1));svg.style.width=scale*100+'%';svg.style.maxWidth='none';$('[data-layout-scale]').textContent=Math.round(scale*100)+'%';});
        function addObject(kind,p={x:100,y:100}) {
          if(['round','square','rectangle'].includes(kind)){const d=open('r3Table');const f=$('form',d);f.reset();f.elements.table_id.value='0';f.elements.shape.value=kind;f.elements.x.value=Math.min(830,Math.max(0,Math.round(p.x)));f.elements.y.value=Math.min(530,Math.max(0,Math.round(p.y)));$('[data-remove-table]',d).hidden=true;return;}
          const defaults={wall:[220,16],door:[80,80],window:[150,16],chair:[45,45],sofa:[150,60],plant:[55,55],bar:[220,70],divider:[170,30],text:[170,35]},size=defaults[kind];if(!size)return;
          remember();const row={id:uid(),kind,label:kind==='text'?'Texto':'',x:Math.min(1000-size[0],Math.max(0,Math.round(p.x))),y:Math.min(650-size[1],Math.max(0,Math.round(p.y))),width:size[0],height:size[1],rotation:0};
          const palette=$('[data-palette="'+kind+'"] svg [data-layout-object]');if(palette)templates.set(row.id,{html:palette.innerHTML,w:70,h:45});data.objects.push(row);selected={type:'object',id:row.id};renderAll();
        }
        $$('[data-palette]').forEach(button=>{button.addEventListener('dragstart',e=>e.dataTransfer.setData('text/plain',button.dataset.palette));button.addEventListener('click',()=>addObject(button.dataset.palette));});
        floor.addEventListener('dragover',e=>e.preventDefault());floor.addEventListener('drop',e=>{e.preventDefault();addObject(e.dataTransfer.getData('text/plain'),point(e));});
      }
      renderAll();
    }
    $$('[data-sector-edit]').forEach(button=>button.onclick=()=>{const s=data.sectors.find(s=>Number(s.id)===Number(button.dataset.sectorEdit));if(!s)return;const dialog=open('r3Sector'),form=$('form',dialog);form.elements.sector_id.value=s.id;for(const k of ['name','color','x','y','width','height','service_rate'])form.elements[k].value=s[k];form.elements.service_enabled.checked=Boolean(Number(s.service_enabled));});
    $('[data-sector-new]')?.addEventListener('click',()=>{const d=open('r3Sector'),form=$('form',d);form.reset();form.elements.sector_id.value=0;});
    const layoutForm=$('[data-layout-form]');layoutForm?.addEventListener('submit',()=>{layoutForm.elements.layout.value=JSON.stringify({tables:data.tables,objects:data.objects});dirty=false;});
    const cards=$('[data-basic-tables]');let ordering=false,moved=null,initialOrder=[];
    $('[data-basic-edit]')?.addEventListener('click',()=>{ordering=true;cards.classList.add('is-ordering');initialOrder=[...cards.children].map(c=>c.dataset.r3Table);$$('[data-r3-table]',cards).forEach(c=>c.draggable=true);$('[data-basic-edit-bar]').hidden=false;});
    if(cards){cards.addEventListener('dragstart',e=>{if(!ordering)return;moved=e.target.closest('[data-r3-table]');moved?.classList.add('dragging');e.dataTransfer.setData('text/plain',moved?.dataset.r3Table||'');});cards.addEventListener('dragover',e=>{if(!ordering)return;e.preventDefault();const target=e.target.closest('[data-r3-table]');if(target&&moved&&target!==moved){const r=target.getBoundingClientRect();cards.insertBefore(moved,e.clientY>r.top+r.height/2?target.nextSibling:target);dirty=true;}});cards.addEventListener('dragend',()=>{moved?.classList.remove('dragging');moved=null;});}
    $('[data-basic-save]')?.addEventListener('click',()=>{[...cards.children].forEach((card,i)=>{const t=data.tables.find(t=>Number(t.id)===Number(card.dataset.r3Table));if(t)t.basic_order=i;});layoutForm.requestSubmit();});
    $('[data-basic-cancel]')?.addEventListener('click',()=>{initialOrder.forEach(id=>cards.append($('[data-r3-table="'+id+'"]',cards)));ordering=false;dirty=false;cards.classList.remove('is-ordering');$$('[data-r3-table]',cards).forEach(c=>c.draggable=false);$('[data-basic-edit-bar]').hidden=true;});
    $('[data-table-search]')?.addEventListener('input',e=>{const q=e.target.value.trim().toLocaleLowerCase('es');$$('[data-r3-table]').forEach(g=>g.style.display=(!q||(g.dataset.search||'').includes(q))?'':'none');});
    if(editor)window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
    if(!editor){const tableData=json('[data-r3-table-data]');if(tableData) {
      $$('[data-r3-table]').forEach(g=>{const names=tableData.accounts.filter(a=>Number(a.table_id)===Number(g.dataset.r3Table)).map(a=>a.customer_name||'').join(' ');g.dataset.search=(g.dataset.search||'')+' '+names.toLocaleLowerCase('es');});
      let currentTable=null,currentAccount=null;
      const base=String(tableData.base).replace(/\/$/,'');
      function showAccount(table,account) {
        currentTable=table;currentAccount=account;const dialog=document.getElementById('r3TableAccount'),isBar=table.space_type==='bar',busy=Number(table.open_count)>0,state=Number(table.payment_count)>0?'due':busy?'busy':'free';
        $('[data-table-name]',dialog).textContent=table.name;$('[data-table-state]',dialog).className='r3-status '+state;$('[data-table-state]',dialog).textContent={free:'Disponible',busy:'Ocupada',due:'Por cobrar'}[state];
        const sector=data.sectors.find(s=>Number(s.id)===Number(table.sector_id));$('[data-table-sector]',dialog).textContent=sector?' · Sector: '+sector.name:'';
        $('[data-table-meta]',dialog).textContent=(account?.customer_name||'Consumidor final')+' · '+table.capacity+' personas · Mesero: '+(account?.waiter_name||'—')+' · '+(account?'Abierta '+new Date(account.created_at.replace(' ','T')).toLocaleTimeString('es-CR',{hour:'2-digit',minute:'2-digit'}):'Mesa disponible');
        const accounts=tableData.accounts.filter(a=>Number(a.table_id)===Number(table.id));const list=$('[data-table-accounts]',dialog);list.replaceChildren();if(accounts.length>1)for(const a of accounts){const b=document.createElement('button');b.type='button';b.className='r3-button '+(a===account?'selected':'');b.textContent=a.display_label||a.ticket_number;b.onclick=()=>showAccount(table,a);list.append(b);}
        const grid=$('.r3-table-detail-grid',dialog);grid.hidden=!account;$('[data-table-open]',dialog).hidden=Boolean(account)&&!isBar;$('[data-table-open] [name=table_id]',dialog).value=table.id;$('[data-table-open] [name=open_key]',dialog).value=uid().replace(/-/g,'');$('[data-table-diner]',dialog).hidden=!isBar;
        $('[data-table-diner] input',dialog).required=isBar;
        const lines=$('[data-table-items]',dialog);lines.replaceChildren();
        if(account){for(const i of tableData.items.filter(i=>Number(i.ticket_id)===Number(account.id))){const row=document.createElement('div');row.className='r3-modal-order-item';const img=document.createElement('img');img.src=i.image;img.alt='';const info=document.createElement('div'),title=document.createElement('strong'),status=document.createElement('small'),price=document.createElement('b');title.textContent=i.quantity+' × '+i.product_name;status.textContent={pending:'Sin enviar',sent:'● Enviado a cocina',preparing:'● Preparando',ready:'● Listo',served:'● Servido'}[i.kitchen_status]||i.kitchen_status;info.append(title,status);if(i.notes){const note=document.createElement('small');note.textContent=i.notes;info.append(note);}for(const o of JSON.parse(i.options_json||'[]')){const note=document.createElement('small');note.textContent=(o.group_name||o.group||'')+': '+o.name;info.append(note);}price.textContent=money(cents(i.line_subtotal)+cents(i.line_tax));const actions=document.createElement('div');actions.className='r3-item-tools';const edit=document.createElement('a');edit.className='r3-button';edit.textContent='✎';edit.title='Editar producto';edit.href=base+'/admin/ventas?screen=order&ticket='+account.id+'&edit_item='+i.id;actions.append(edit);if(i.kitchen_status==='pending'&&!Number(i.paid_quantity)){const f=document.createElement('form');f.method='post';f.action=base+'/admin/ventas?screen=order&ticket='+account.id;for(const [name,value]of Object.entries({csrf:tableData.csrf,action:'quantity',revision:account.revision,item_id:i.id,quantity:'0'})){const h=document.createElement('input');h.type='hidden';h.name=name;h.value=value;f.append(h);}const b=document.createElement('button');b.className='r3-button danger';b.textContent='×';b.title='Quitar producto';f.append(b);actions.append(f);}row.append(img,info,price,actions);lines.append(row);}
          $('[data-table-note]',dialog).value=account.notes||'';
          $('[data-table-totals]',dialog).innerHTML=[['Consumo',Number(account.subtotal)+Number(account.tax_total)],['Servicio '+account.service_rate+'%',account.service_total],['Descuento',-Number(account.discount_total)],['Pagado',account.paid_total],['Total pendiente',Math.max(0,Number(account.total)-Number(account.paid_total))]].map(([k,v])=>'<p><span>'+esc(k)+'</span><b>'+money(cents(v))+'</b></p>').join('');
          $('[data-table-service]',dialog).textContent=Number(account.service_rate)?'✓ Servicio activo en esta cuenta':'Sin cargo por servicio';
          const path=base+'/admin/ventas?screen=order&ticket='+account.id;const charge=base+'/admin/ventas?screen=checkout&ticket='+account.id;
          $('[data-table-add]',dialog).href=path;$('[data-table-more]',dialog).href=path;$('[data-table-charge]',dialog).href=charge;$('[data-table-charge]',dialog).textContent='Cobrar '+money(cents(account.total)-cents(account.paid_total));$('[data-table-split]',dialog).href=charge+'&divide=1';
          $('[data-table-note-form]',dialog).action=path;$('[data-table-note-form] [name=revision]',dialog).value=account.revision;
          $('[data-table-send]',dialog).action=path;$('[data-table-send] [name=revision]',dialog).value=account.revision;
          const pre=$('[data-table-prebill]',dialog);pre.action=path;pre.elements.revision.value=account.revision;$('[data-table-charge]',dialog).hidden=!tableData.canCharge;$('[data-table-split]',dialog).hidden=!tableData.canCharge;
          $('[data-table-reprint]',dialog).href=account.last_sale?base+'/admin/recibo?venta='+account.last_sale:base+'/admin/recibo?precuenta='+account.id;
        }
        $('.r3-table-modal-actions',dialog).hidden=!account;if(!dialog.open)dialog.showModal();
      }
      $$('[data-r3-table]').forEach(g=>g.addEventListener('click',()=>{if(ordering)return;const table=tableData.tables.find(t=>Number(t.id)===Number(g.dataset.r3Table));if(table)showAccount(table,tableData.accounts.find(a=>Number(a.table_id)===Number(table.id)));}));
      for(const [attr,action,title] of [['data-table-move','move_table','Mover cuenta'],['data-table-join','join_tables','Unir mesas']])$('['+attr+']')?.addEventListener('click',()=>{if(!currentAccount)return;const d=open('r3MoveTable'),form=$('[data-move-form]',d);$('[data-move-title]',d).textContent=title;form.action=base+'/admin/ventas?screen=order&ticket='+currentAccount.id;form.elements.action.value=action;form.elements.revision.value=currentAccount.revision;});
    }}
  }
  // Optional balance cards are submitted as a payment, preserving the taxable total.
  const checkout=$('#checkoutForm');if(checkout){
    const key=document.createElement('input');key.type='hidden';key.name='checkout_key';key.value=uid().replace(/-/g,'');checkout.append(key);
    const card=document.createElement('details');card.className='r3-web-gift';card.innerHTML='<summary>Usar una tarjeta de regalo</summary><label>Código de tarjeta<input class="input" name="gift_code" maxlength="32" placeholder="REG-…"></label><label>Monto a usar (vacío: saldo disponible)<input class="input" name="gift_amount" type="number" min="0" step=".01"></label><small>El monto restante se paga con el método seleccionado. Verás el uso de la tarjeta en el resumen del pedido.</small>';checkout.append(card);
  }
});
