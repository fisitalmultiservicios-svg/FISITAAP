'use strict';
const {contextBridge,ipcRenderer}=require('electron');
// Only printing is exposed to the configured web; settings remain local-only.
contextBridge.exposeInMainWorld('fisitaapWindows',{
  print:job=>ipcRenderer.invoke('fisitaap:print',job)
});
