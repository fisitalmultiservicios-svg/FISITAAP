(() => {
  'use strict';
  if (window.top !== window || window.__fisitaapAndroidInstalled) return;
  window.__fisitaapAndroidInstalled = true;
  const waiting = new Map(), prepared = new WeakSet();
  let counter = 0;
  FisitaapAndroid.onmessage = event => {
    let response; try { response = JSON.parse(event.data); } catch { return; }
    const pending = waiting.get(response.requestId); if (!pending) return;
    clearTimeout(pending.timer); waiting.delete(response.requestId);
    response.ok ? pending.resolve(response) : pending.reject(new Error(response.error || 'Impresión no confirmada.'));
  };
  function request(action, data = {}) {
    return new Promise((resolve, reject) => {
      const requestId = 'android-' + (++counter);
      const timer = setTimeout(() => { waiting.delete(requestId); reject(new Error('El envío no quedó confirmado. Revisa la impresora antes de reimprimir.')); }, 30000);
      waiting.set(requestId, { resolve, reject, timer });
      FisitaapAndroid.postMessage(JSON.stringify({ requestId, action, ...data }));
    });
  }
  const uuid = () => crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36) + '-' + [...crypto.getRandomValues(new Uint8Array(16))].map(x=>x.toString(16).padStart(2,'0')).join('');
  function status(card, text, error = false) {
    const node = card.querySelector('[data-print-status]');
    if (node) { node.textContent = text; node.classList.toggle('alert-error', error); node.classList.toggle('alert-success', !error); }
  }
  function payload(card) {
    const source = card.querySelector('#fisitaapPrintPayload,[data-print-payload]');
    if (!source) throw new Error('No se encontró el comprobante.');
    return JSON.parse(source.textContent);
  }
  async function printCard(card, copy = false) {
    if (card.dataset.androidPrinting === '1') return;
    card.dataset.androidPrinting = '1';
    const buttons = [...card.querySelectorAll('[data-bridge-print],[data-android-copy]')]; buttons.forEach(x=>x.disabled=true);
    try {
      const job = payload(card); if (copy) job.id = String(job.id).slice(0,170) + '-copy-' + uuid();
      status(card, 'Enviando desde Android a la impresora de red…');
      const result = await request('print', { job });
      status(card, result.duplicate ? 'Este trabajo ya se envió. Usa Reimprimir copia si necesitas otro.' : 'Enviado desde Android. Revisa la salida del papel.');
    } catch (error) { status(card, error.message, true); }
    finally { card.dataset.androidPrinting = '0'; buttons.forEach(x=>x.disabled=false); }
  }
  async function printLocal(copy = false) {
    const receipt = document.querySelector('#receipt'), button = document.querySelector('#printReceipt');
    if (!receipt || !button || button.disabled) return;
    const text = receipt.innerText || receipt.textContent;
    const match = text.match(/Comprobante local\s+([a-f0-9-]{36})/i);
    if (!match) { alert('Abre el comprobante de la venta guardada antes de imprimir.'); return; }
    button.disabled = true;
    try {
      const result = await request('print', { job: { id: 'local-' + match[1] + (copy ? '-copy-' + uuid() : ''), text } });
      alert(result.duplicate ? 'Este recibo ya se envió. Usa Reimprimir copia si necesitas otro.' : 'Recibo enviado desde Android. Revisa el papel.');
    } catch (error) { alert(error.message); }
    finally { button.disabled = false; }
  }
  function prepare() {
    document.querySelectorAll('.print-result-card').forEach(card => {
      const job = card.querySelector('[data-print-job]'), button = card.querySelector('[data-bridge-print]');
      if (!job || !button || !card.querySelector('#fisitaapPrintPayload,[data-print-payload]') || prepared.has(card)) return;
      prepared.add(card);
      const auto = job.dataset.auto === '1'; job.dataset.auto = '0';
      button.textContent = 'Imprimir desde Android';
      const copy = document.createElement('button'); copy.type='button'; copy.className='btn btn-light'; copy.dataset.androidCopy='1'; copy.textContent='Reimprimir copia'; button.after(copy);
      status(card, 'Listo para imprimir desde Android.');
      // The web script may initialize its status later in the same parser turn.
      setTimeout(() => { button.textContent='Imprimir desde Android'; if (auto) printCard(card); else if(card.dataset.androidPrinting!=='1')status(card,'Listo para imprimir desde Android.'); }, 0);
    });
    const localButton = document.querySelector('#printReceipt');
    if (localButton && !document.querySelector('#androidLocalCopy')) {
      const copy=document.createElement('button'); copy.id='androidLocalCopy';copy.type='button';copy.className='light';copy.textContent='Reimprimir copia';localButton.after(copy);
    }
  }
  document.addEventListener('click', event => {
    const target = event.target.closest?.('[data-bridge-print],[data-android-copy],#printReceipt,#androidLocalCopy');
    if (!target) return;
    event.preventDefault(); event.stopImmediatePropagation();
    const copy = target.hasAttribute('data-android-copy') || target.id==='androidLocalCopy';
    if (copy && !confirm('¿Imprimir otra copia? Revisa el papel para evitar un duplicado.')) return;
    target.closest('.print-result-card') ? printCard(target.closest('.print-result-card'),copy) : printLocal(copy);
  }, true);
  window.print = () => {
    const local=document.querySelector('#receiptDialog[open]'); if(local)return printLocal();
    const cards=[...document.querySelectorAll('.print-result-card')];
    if(cards.length)return cards.reduce((previous,card)=>previous.then(()=>printCard(card)),Promise.resolve());
    return request('pagePrint').catch(error=>alert(error.message));
  };
  // Capture the local bridge even if an older web screen starts printing early.
  const originalFetch=window.fetch.bind(window);
  window.fetch=(url,options)=>{
    if(String(url)==='http://127.0.0.1:18765/print') {
      try { return request('print',{job:JSON.parse(options.body)}).then(result=>new Response(JSON.stringify(result),{headers:{'Content-Type':'application/json'}})); }
      catch(error){return Promise.reject(error);}
    }
    return originalFetch(url,options);
  };
  new MutationObserver(prepare).observe(document,{childList:true,subtree:true});
  document.addEventListener('DOMContentLoaded',prepare,{once:true}); prepare();
})();
