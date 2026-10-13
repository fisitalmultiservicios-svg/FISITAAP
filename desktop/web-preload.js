'use strict';
const {contextBridge,ipcRenderer}=require('electron');
// The device secret stays in the main process. Web pages receive only a
// short-lived proof bound to their existing user and server nonce.
contextBridge.exposeInMainWorld('fisitaapWindows',{
  trackWebPayment:data=>ipcRenderer.invoke('fisitaap:track-payment',data),
  printerInfo:()=>ipcRenderer.invoke('fisitaap:web-printers'),
  flushPrint:()=>ipcRenderer.invoke('fisitaap:flush-print'),
  print:job=>ipcRenderer.invoke('fisitaap:print',job),
  authorize:data=>ipcRenderer.invoke('fisitaap:authorize',data)
});
