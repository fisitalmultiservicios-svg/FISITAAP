'use strict';
const {contextBridge,ipcRenderer}=require('electron');
contextBridge.exposeInMainWorld('fisitaapWindows',{
  windowState:()=>ipcRenderer.invoke('fisitaap:window-state'),
  setFullscreen:value=>ipcRenderer.invoke('fisitaap:fullscreen',value),
  onWindowState:handler=>{const listener=(_event,value)=>handler(value);ipcRenderer.on('fisitaap:window-state',listener);return()=>ipcRenderer.removeListener('fisitaap:window-state',listener);},
  workspaceState:()=>ipcRenderer.invoke('fisitaap:workspace-state'),
  useWeb:()=>ipcRenderer.invoke('fisitaap:use-web'),
  exportConnection:()=>ipcRenderer.invoke('fisitaap:export-connection'),
  onResumeLocal:handler=>{const listener=()=>handler();ipcRenderer.on('fisitaap:resume-local',listener);return()=>ipcRenderer.removeListener('fisitaap:resume-local',listener);},
  useLocal:()=>ipcRenderer.invoke('fisitaap:use-local'),
  onWorkspaceStatus:handler=>{const listener=(_event,value)=>handler(value);ipcRenderer.on('fisitaap:workspace-status',listener);return()=>ipcRenderer.removeListener('fisitaap:workspace-status',listener);},
  printers:()=>ipcRenderer.invoke('fisitaap:printers'),
  settings:()=>ipcRenderer.invoke('fisitaap:settings'),
  retryPrintBridge:()=>ipcRenderer.invoke('fisitaap:retry-print-bridge'),
  saveSettings:value=>ipcRenderer.invoke('fisitaap:save-settings',value),
  printLocal:value=>ipcRenderer.invoke('fisitaap:print-local',value),
  print:job=>ipcRenderer.invoke('fisitaap:print',job),
  localRequest:value=>ipcRenderer.invoke('fisitaap:local-request',value)
});
