; Complete per-user Windows installer. NSIS generates its uninstaller directly;
; building this script does not require executing Windows programs on Linux.
SetCompressor /SOLID lzma
SetCompressorDictSize 32
!include "MUI2.nsh"
!include "x64.nsh"
Name "FISITAAP Escritorio"
InstallDir "$LOCALAPPDATA\Programs\FISITAAP Escritorio"
InstallDirRegKey HKCU "Software\FISITAL\FISITAAP" "InstallDir"
RequestExecutionLevel user
!define MUI_ABORTWARNING
!insertmacro MUI_PAGE_WELCOME
!insertmacro MUI_PAGE_DIRECTORY
!insertmacro MUI_PAGE_INSTFILES
!define MUI_FINISHPAGE_RUN "$INSTDIR\FISITAAP Escritorio.exe"
!define MUI_FINISHPAGE_RUN_TEXT "Abrir FISITAAP Escritorio"
!insertmacro MUI_PAGE_FINISH
!insertmacro MUI_UNPAGE_CONFIRM
!insertmacro MUI_UNPAGE_INSTFILES
!insertmacro addLangs

Function .onInit
  ${IfNot} ${RunningX64}
    MessageBox MB_ICONSTOP "Este instalador requiere Windows de 64 bits."
    Abort
  ${EndIf}
FunctionEnd

Section "Instalar"
  SetShellVarContext current
  SetOutPath "$INSTDIR"
  File /r "${PROJECT_DIR}/dist/win-unpacked/*"
  WriteUninstaller "$INSTDIR\Desinstalar.exe"
  CreateDirectory "$SMPROGRAMS\FISITAAP"
  CreateShortcut "$SMPROGRAMS\FISITAAP\FISITAAP Escritorio.lnk" "$INSTDIR\FISITAAP Escritorio.exe"
  CreateShortcut "$DESKTOP\FISITAAP Escritorio.lnk" "$INSTDIR\FISITAAP Escritorio.exe"
  WriteRegStr HKCU "Software\FISITAL\FISITAAP" "InstallDir" "$INSTDIR"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\FISITAAP" "DisplayName" "FISITAAP Escritorio"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\FISITAAP" "DisplayVersion" "${VERSION}"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\FISITAAP" "UninstallString" '$\"$INSTDIR\Desinstalar.exe$\"'
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\FISITAAP" "Publisher" "FISITAL"
  WriteRegDWORD HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\FISITAAP" "NoModify" 1
  WriteRegDWORD HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\FISITAAP" "NoRepair" 1
SectionEnd

Section "Uninstall"
  SetShellVarContext current
  ; Only program files are removed. Sales and backups live in Electron userData.
  Delete "$DESKTOP\FISITAAP Escritorio.lnk"
  Delete "$SMPROGRAMS\FISITAAP\FISITAAP Escritorio.lnk"
  RMDir "$SMPROGRAMS\FISITAAP"
  !include "${PROJECT_DIR}/dist/uninstall-files.nsh"
  DeleteRegKey HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\FISITAAP"
  DeleteRegKey HKCU "Software\FISITAL\FISITAAP"
SectionEnd
