(() => {
  'use strict';
  const adapt=()=>{
    const login=document.querySelector('#loginForm');
    if(login&&!login.dataset.mobileReady){login.dataset.mobileReady='1';const help=login.parentElement.querySelector('.small');if(help)help.textContent='La contraseña local se configura en Administración. Solo el dispositivo elegido como principal podrá cobrar aquí.';}
    const pair=document.querySelector('#pairForm');if(pair&&!pair.dataset.mobileReady){pair.dataset.mobileReady='1';pair.parentElement.querySelector('h1').textContent='Preparar Android para vender sin internet';}
    const backup=document.querySelector('#backupButton'),card=backup?.closest('section');
    if(card&&!card.dataset.mobileReady){
      card.dataset.mobileReady='1';const title=document.createElement('h3');title.textContent='Respaldo de este Android';const info=document.createElement('p');info.textContent='Las ventas se guardan en este dispositivo. Sincronízalas con la web al recuperar internet. El respaldo interno no se transfiere a otro teléfono: no desinstales ni borres los datos de la aplicación con ventas pendientes.';
      backup.textContent='Guardar respaldo interno';backup.onclick=async()=>{try{await api('/api/backup',{});message('Respaldo interno guardado en este Android. Sincroniza para conservar también las ventas en la web.');}catch(error){message(error.message,true);}};
      card.replaceChildren(title,info,backup);
    }
  };
  new MutationObserver(adapt).observe(document.querySelector('#root'),{childList:true,subtree:true});adapt();
})();
