(() => {
  'use strict';
  document.addEventListener('submit', event => {
    const form=event.target;
    if(!form.matches('[data-r3-collect]')||!form.elements.receipt_output_r20)return;
    // Hidden intent survives disabled submit buttons and the native asynchronous tracking step.
    if(event.submitter?.dataset.receiptAction!==undefined)form.elements.print_receipt.value=event.submitter.dataset.receiptAction;
  },true);
  const pdf=document.querySelector('[data-r20-pdf-download]');
  if(pdf){
    const id='fisitaap-pdf-r20:'+pdf.href;
    try{if(!sessionStorage.getItem(id)){sessionStorage.setItem(id,'1');pdf.click();}}catch{}
  }
})();
