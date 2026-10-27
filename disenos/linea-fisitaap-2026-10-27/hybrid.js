// Add the new visual exploration without changing the three original proposals.
ui.posTab='ticket';
ui.floorZoom=1;
const lineaGo=go;
go=function(route){lineaGo(route);if(route==='pos'){ui.posTab='ticket';renderView();}};
const lineaBrand=brand;
brand=function(){
 if(ui.mode==='master')return lineaBrand();
 return `<img class="hybrid-brand" src="${ASSETS.logo}" alt="FISITAAP"><span class="hybrid-brand-divider"></span><span><strong>${esc(state.settings.name)}</strong><small>${ui.mode==='driver'?'Tu ruta, al día.':'Aguas Zarcas · Sucursal principal'}</small></span>`;
};
const lineaTicketLine=ticketLine;
ticketLine=function(l,index,scope){
 if(scope!=='pos')return lineaTicketLine(l,index,scope);
 const p=state.products.find(p=>p.id===l.productId);
 return `<article class="ticket-line invoice-row"><div class="invoice-product"><span class="invoice-row-number">${index+1}</span><div><b>${esc(l.name)}</b><small>${esc(l.options?.join(' · ')||p?.category||'')}</small></div></div><div class="quantity"><button data-action="quantity" data-scope="pos" data-index="${index}" data-delta="-1" aria-label="Reducir ${esc(l.name)}">−</button><input type="number" min="${p?.kind==='bulk'?.001:1}" step="${p?.kind==='bulk'?.001:1}" value="${l.qty}" data-quantity-index="${index}" data-quantity-scope="pos" aria-label="Cantidad de ${esc(l.name)}"><button data-action="quantity" data-scope="pos" data-index="${index}" data-delta="1" aria-label="Aumentar ${esc(l.name)}">+</button></div><span class="invoice-unit">${money(l.price)}</span><div class="line-amount">${money(l.qty*l.price)}${ib('trash','line-remove','Quitar '+l.name,`data-scope="pos" data-index="${index}"`)}</div></article>`;
};
const lineaPos=pos;
pos=function(){
 const template=document.createElement('template');template.innerHTML=lineaPos();
 const root=template.content,layout=root.querySelector('.pos-layout'),ticket=root.querySelector('.ticket'),products=root.querySelector('.pos-products');
 layout.prepend(ticket);
 ticket.querySelector('.ticket-head h2').textContent=state.tableId?'Facturación · Cuenta de mesa':'Facturación';
 ticket.querySelector('.ticket-lines').insertAdjacentHTML('beforebegin','<div class="invoice-columns"><span>Producto</span><span>Cantidad</span><span>Precio</span><span>Importe</span></div>');
 const filters=products.querySelector('.filter-bar');filters.querySelector('select').remove();
 products.insertAdjacentHTML('afterbegin',`<header class="quick-heading"><div><p class="eyebrow">CATÁLOGO</p><h2>Carga rápida</h2></div><span class="pill">${state.products.filter(p=>p.active).length} productos</span></header>`);
 filters.insertAdjacentHTML('afterend',`<div class="quick-categories" aria-label="Categorías de carga rápida">${['Todos',...state.categories.map(c=>c.name)].map(c=>`<button class="chip ${ui.category===c?'active':''}" data-action="category" data-category="${esc(c)}" aria-pressed="${ui.category===c}">${c==='Todos'?icon('overview'):icon(c==='Bebidas'?'gift':'bag')}<span>${esc(c)}</span></button>`).join('')}</div>`);
 const tabs=root.querySelector('.pos-mobile-tabs');tabs.prepend(tabs.querySelector('[data-tab=ticket]'));tabs.querySelector('[data-tab=ticket]').innerHTML=`Facturación · ${money(totals(state.cart).total)}`;
 return template.innerHTML;
};
function hybridFurniture(t,index){
 const occupied=t.status==='occupied',color=occupied?'#427dac':'#779b79',shape=index%3===1?'round':index%3===2?'rectangle':'square';
 const seats=Math.max(2,Math.min(8,t.seats));let chairs='';
 for(let i=0;i<seats;i++){const angle=360*i/seats;chairs+=`<g transform="translate(60 60) rotate(${angle})"><rect x="-14" y="-54" width="28" height="28" rx="8" fill="${color}" stroke="#526b68" stroke-width="1"/><path d="M-11-47Q0-52 11-47" fill="none" stroke="#ffffff90" stroke-width="2"/></g>`;}
 const top=shape==='round'?'<circle cx="60" cy="60" r="37" fill="url(#hy-wood)" stroke="#ab8862"/>':`<rect x="${shape==='rectangle'?15:24}" y="24" width="${shape==='rectangle'?90:72}" height="72" rx="9" fill="url(#hy-wood)" stroke="#ab8862"/>`;
 return `<g filter="url(#hy-shadow)">${chairs}${top}<circle cx="60" cy="60" r="16" fill="#fffefa"/><text x="60" y="65" text-anchor="middle" fill="#173c57" font-size="13" font-weight="650">${esc(t.name.replace(/^mesa\s*/i,''))}</text></g>`;
}
function hybridPlant(x,y){return `<g transform="translate(${x} ${y})"><circle r="19" fill="#e9e1d0"/><ellipse rx="10" ry="23" transform="rotate(45)" fill="#71997c"/><ellipse rx="10" ry="23" transform="rotate(-45)" fill="#95b29c"/><ellipse rx="8" ry="21" fill="#527f60"/></g>`;}
function hybridFloor(rows){
 const main=state.tables.filter(t=>t.room!=='Terraza').length,terrace=state.tables.filter(t=>t.room==='Terraza').length,height=Math.max(570,125+(Math.ceil(main/3)-1)*197+205,147+(terrace-1)*195+205);
 return `<div class="floor-viewport"><svg class="hybrid-floor" viewBox="0 0 1000 ${height}" style="width:${ui.floorZoom*100}%;height:${ui.floorZoom*100}%" aria-label="Plano interactivo de mesas, salón principal y terraza"><defs><linearGradient id="hy-wood" x2=".8" y2="1"><stop stop-color="#e5ceb0"/><stop offset="1" stop-color="#c0a07a"/></linearGradient><pattern id="hy-tiles" width="48" height="48" patternUnits="userSpaceOnUse"><path d="M48 0H0V48" fill="none" stroke="#dedfd8" stroke-width=".7"/></pattern><pattern id="hy-deck" width="28" height="28" patternUnits="userSpaceOnUse"><rect width="28" height="28" fill="#f0eadf"/><path d="M0 0H28M0 28H28" stroke="#ded4c4" stroke-width="1"/></pattern><filter id="hy-shadow" x="-25%" y="-25%" width="150%" height="160%"><feDropShadow dx="0" dy="3" stdDeviation="2" flood-color="#2f4334" flood-opacity=".13"/></filter></defs><rect width="1000" height="${height}" fill="#fafbf9"/><rect x="25" y="55" width="640" height="${height-100}" rx="8" fill="#f5f4ee"/><rect x="25" y="55" width="640" height="${height-100}" rx="8" fill="url(#hy-tiles)"/><rect x="700" y="55" width="275" height="${height-100}" rx="8" fill="url(#hy-deck)"/><path d="M665 ${height-45}V55H25V${height-45}H260M340 ${height-45}H665" fill="none" stroke="#a9b4b7" stroke-width="7"/><path d="M700 55H975V${height-45}H700" fill="none" stroke="#c1b69f" stroke-width="4"/><path d="M260 ${height-45}V${height-125}A80 80 0 0 1 340 ${height-45}" fill="none" stroke="#b2bdbd" stroke-width="1.5"/><text x="300" y="${height-18}" text-anchor="middle" fill="#7e8c8e" font-size="11" letter-spacing="2">ENTRADA</text><text x="35" y="32" fill="#60747b" font-size="12" letter-spacing="2">SALÓN PRINCIPAL</text><text x="711" y="32" fill="#60747b" font-size="12" letter-spacing="2">TERRAZA</text><rect x="90" y="52" width="145" height="8" rx="3" fill="#b9d5df"/><rect x="430" y="52" width="145" height="8" rx="3" fill="#b9d5df"/>${hybridPlant(60,95)}${hybridPlant(630,95)}${hybridPlant(737,95)}${hybridPlant(940,height-85)}${rows.map((t)=>{const idx=state.tables.indexOf(t),terrace=t.room==='Terraza',roomRows=state.tables.filter(v=>v.room===t.room),n=roomRows.indexOf(t),x=terrace?778:82+(n%3)*193,y=terrace?147+Math.floor(n)*195:125+Math.floor(n/3)*197,status=t.status==='occupied'?'Ocupada':'Disponible',sel=ui.table===t.id;return `<g class="floor-table ${sel?'selected':''}" data-action="table-select" data-id="${esc(t.id)}" tabindex="0" role="button" aria-label="${esc(t.name)} · ${status} · ${t.seats} personas" transform="translate(${x} ${y})"><title>${esc(t.name)} · ${status}</title><rect class="floor-selection" x="-8" y="-8" width="136" height="163" rx="13"/>${hybridFurniture(t,idx)}<rect x="-2" y="124" width="124" height="31" rx="6" fill="${t.status==='occupied'?'#e4eef8':'#e6f0e7'}"/><text x="60" y="137" font-size="10" font-weight="600" text-anchor="middle" fill="${t.status==='occupied'?'#315d85':'#477453'}">${status}</text><text x="60" y="149" font-size="9" text-anchor="middle" fill="#65787d">${t.status==='occupied'?money(totals(t.cart).total):t.seats+' personas'}</text></g>`;}).join('')}</svg></div>`;
}
const lineaTables=tables;
tables=function(){
 const visible=state.tables.filter(t=>ui.room==='Todos'||t.room===ui.room);
 if(ui.table&&!visible.some(t=>t.id===ui.table))ui.table=visible[0]?.id||null;
 const template=document.createElement('template');template.innerHTML=lineaTables();const root=template.content;
 root.querySelector('h1').textContent='Mesas y caja';root.querySelector('.page-heading p:not(.eyebrow)').textContent='Tu salón, de un vistazo. Selecciona una mesa para abrir o continuar su cuenta.';
 const rows=state.tables.filter(t=>ui.room==='Todos'||t.room===ui.room),panel=root.querySelector('.tables-layout>section');
 panel.classList.add('floor-card');panel.innerHTML=`<header class="floor-toolbar"><div class="hstack"><span class="floor-legend"><i></i>Disponible</span><span class="floor-legend busy"><i></i>Ocupada</span></div><div class="hstack"><button class="btn small" data-hybrid="floor-zoom" data-delta="-.2" aria-label="Alejar plano" ${ui.floorZoom<=1?'disabled':''}>−</button><button class="btn small" data-hybrid="floor-reset" aria-label="Ajustar plano a la pantalla">${Math.round(ui.floorZoom*100)} %</button><button class="btn small" data-hybrid="floor-zoom" data-delta=".2" aria-label="Acercar plano" ${ui.floorZoom>=1.8?'disabled':''}>+</button></div></header>${hybridFloor(rows)}<footer class="floor-hint">${icon('table')}Vista gráfica · ${rows.length} mesas${btn('Pedidos web · '+state.orders.filter(o=>o.status==='Nuevo').length,'incoming','small')}</footer>`;
 if(!ui.table)root.querySelector('.floor-table')?.classList.add('selected');
 return template.innerHTML;
};
document.addEventListener('click',event=>{const button=event.target.closest('[data-hybrid]');if(!button)return;if(button.dataset.hybrid==='floor-zoom')ui.floorZoom=Math.round(Math.max(1,Math.min(1.8,ui.floorZoom+Number(button.dataset.delta)))*10)/10;else ui.floorZoom=1;renderView();});
document.addEventListener('keydown',event=>{const table=event.target.closest('.floor-table');if(table&&['Enter',' '].includes(event.key)){event.preventDefault();ui.table=table.dataset.id;renderView();}});
