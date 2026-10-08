'use strict';
const {contextBridge,ipcRenderer}=require('electron');
contextBridge.exposeInMainWorld('fisitaapWindows',{
  workspaceState:()=>ipcRenderer.invoke('fisitaap:workspace-state'),
  useWeb:()=>ipcRenderer.invoke('fisitaap:use-web'),
  useLocal:()=>ipcRenderer.invoke('fisitaap:use-local'),
  onWorkspaceStatus:handler=>{const listener=(_event,value)=>handler(value);ipcRenderer.on('fisitaap:workspace-status',listener);return()=>ipcRenderer.removeListener('fisitaap:workspace-status',listener);},
  printers:()=>ipcRenderer.invoke('fisitaap:printers'),
  settings:()=>ipcRenderer.invoke('fisitaap:settings'),
  retryPrintBridge:()=>ipcRenderer.invoke('fisitaap:retry-print-bridge'),
  saveSettings:value=>ipcRenderer.invoke('fisitaap:save-settings',value),
  print:job=>ipcRenderer.invoke('fisitaap:print',job)
});
