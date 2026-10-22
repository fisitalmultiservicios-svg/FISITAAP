/* Prepare admin photos before upload; the server validates and optimizes again. */
(()=>{
  'use strict';
  if(!document.body.classList.contains('admin-body'))return;
  const records=new WeakMap(),submitting=new WeakSet();let queue=Promise.resolve();
  const limit=25*1024*1024,allowed=['image/jpeg','image/png','image/webp'];
  const size=n=>n>=1048576?(n/1048576).toLocaleString('es-CR',{maximumFractionDigits:1})+' MB':Math.max(1,Math.round(n/1024))+' KB';
  function notice(input,message,error=false){
    let node=input.parentElement.querySelector('[data-image-upload-status]');
    if(!node){node=document.createElement('span');node.dataset.imageUploadStatus='';node.className='image-upload-status';input.after(node);}
    node.setAttribute('role',error?'alert':'status');node.textContent=message;
  }
  const blob=(canvas,type,quality)=>new Promise((resolve,reject)=>canvas.toBlob(value=>value?resolve(value):reject(new Error('No se pudo preparar la imagen.')),type,quality));
  async function prepare(file){
    if(file.size>limit)throw new Error('Selecciona una imagen de hasta 25 MB.');
    if(!allowed.includes(file.type))throw new Error('Selecciona una imagen JPG, PNG o WebP.');
    let source,objectURL;
    try{
      if(window.createImageBitmap)source=await createImageBitmap(file);
      else{objectURL=URL.createObjectURL(file);source=new Image();source.src=objectURL;await source.decode();}
      const width=source.width||source.naturalWidth,height=source.height||source.naturalHeight;
      if(!width||!height)throw new Error('La imagen no es válida.');
      if(file.size<=450*1024&&Math.max(width,height)<=1600)return file;
      const canvas=document.createElement('canvas');let result;
      for(const [maximum,quality] of [[1600,.82],[1600,.72],[1200,.72],[900,.70]]){
        const ratio=Math.min(1,maximum/Math.max(width,height));canvas.width=Math.max(1,Math.round(width*ratio));canvas.height=Math.max(1,Math.round(height*ratio));
        const ctx=canvas.getContext('2d');if(!ctx)throw new Error('El navegador no pudo preparar la foto.');ctx.drawImage(source,0,0,canvas.width,canvas.height);
        result=await blob(canvas,'image/webp',quality);if(result.size<=600*1024)break;
      }
      // Browsers without WebP encoding may return PNG; keep transparency intact.
      const extension=result.type==='image/webp'?'webp':'png';
      return new File([result],file.name.replace(/\.[^.]*$/,'')+'.'+extension,{type:result.type,lastModified:file.lastModified});
    }finally{source?.close?.();if(objectURL)URL.revokeObjectURL(objectURL);}
  }
  function prepareInput(input){
    const previous=records.get(input);if(previous)return previous.promise;
    const files=[...input.files],record={ready:false};records.set(input,record);
    notice(input,'Preparando imagen'+(files.length>1?'es':'')+'…');
    record.promise=queue.then(async()=>{
      const transfer=new DataTransfer();for(const file of files)transfer.items.add(await prepare(file));
      if(records.get(input)!==record)return;
      input.files=transfer.files;record.ready=true;
      const before=files.reduce((sum,f)=>sum+f.size,0),after=[...input.files].reduce((sum,f)=>sum+f.size,0);
      notice(input,'Imagen'+(files.length>1?'es':'')+' preparada'+(files.length>1?'s':'')+': '+size(before)+' → '+size(after)+'. Se guardará'+(files.length>1?'n':'')+' al guardar el formulario.');
    }).catch(error=>{if(records.get(input)===record)notice(input,error.message||'Vuelve a seleccionar la imagen.',true);throw error;});
    queue=record.promise.catch(()=>{});return record.promise;
  }
  function imageInputs(form){return [...form.querySelectorAll('input[type=file]')].filter(input=>!input.disabled&&input.files?.length&&/image\/(?:jpeg|png|webp)/.test(input.accept));}
  document.addEventListener('change',event=>{
    const input=event.target;if(!input.matches?.('input[type=file]')||!/image\/(?:jpeg|png|webp)/.test(input.accept))return;
    records.delete(input);if(input.files?.length)void prepareInput(input).catch(()=>{});else notice(input,'');
  });
  document.addEventListener('submit',async event=>{
    const form=event.target;if(!(form instanceof HTMLFormElement))return;
    const inputs=imageInputs(form);if(!inputs.length||inputs.every(input=>records.get(input)?.ready))return;
    event.preventDefault();event.stopImmediatePropagation();if(submitting.has(form))return;submitting.add(form);
    const submitter=event.submitter,buttons=[...form.querySelectorAll('button[type=submit],button:not([type])')],disabled=buttons.map(b=>b.disabled);buttons.forEach(b=>b.disabled=true);
    try{
      await Promise.all(inputs.map(prepareInput));
      buttons.forEach((button,i)=>button.disabled=disabled[i]);submitting.delete(form);
      if(imageInputs(form).every(input=>records.get(input)?.ready))form.requestSubmit(submitter||undefined);
    }catch(_){/* Keep the form and its previous published images intact. */}
    finally{buttons.forEach((button,i)=>button.disabled=disabled[i]);submitting.delete(form);}
  },true);
  document.addEventListener('click',event=>{
    const link=event.target.closest('a[href^="#design-"]');if(!link)return;
    const section=document.querySelector(link.getAttribute('href'));if(section instanceof HTMLDetailsElement){section.open=true;section.scrollIntoView({behavior:'smooth',block:'start'});}
  });
})();
