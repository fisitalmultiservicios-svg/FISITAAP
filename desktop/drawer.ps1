$ErrorActionPreference = 'Stop'
Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
public static class FisitaapDrawer {
 [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Unicode)] public struct Doc {public string Name;public string File;public string Type;}
 [DllImport("winspool.drv",CharSet=CharSet.Unicode,SetLastError=true)] static extern bool OpenPrinter(string name,out IntPtr printer,IntPtr defaults);
 [DllImport("winspool.drv",SetLastError=true)] static extern bool ClosePrinter(IntPtr printer);
 [DllImport("winspool.drv",CharSet=CharSet.Unicode,SetLastError=true)] static extern int StartDocPrinter(IntPtr printer,int level,ref Doc doc);
 [DllImport("winspool.drv",SetLastError=true)] static extern bool EndDocPrinter(IntPtr printer);
 [DllImport("winspool.drv",SetLastError=true)] static extern bool StartPagePrinter(IntPtr printer);
 [DllImport("winspool.drv",SetLastError=true)] static extern bool EndPagePrinter(IntPtr printer);
 [DllImport("winspool.drv",SetLastError=true)] static extern bool WritePrinter(IntPtr printer,byte[] data,int count,out int written);
 public static void Pulse(string name) {
   IntPtr printer;if(!OpenPrinter(name,out printer,IntPtr.Zero))throw new Exception("No se pudo abrir la gaveta mediante la impresora de Windows.");
   try {Doc doc=new Doc{Name="FISITAAP gaveta",File=null,Type="RAW"};if(StartDocPrinter(printer,1,ref doc)==0)throw new Exception("Windows rechazó la señal de gaveta.");
     try {if(!StartPagePrinter(printer))throw new Exception("Windows rechazó la señal de gaveta.");try {byte[] pulse={27,112,0,25,250};int written;if(!WritePrinter(printer,pulse,pulse.Length,out written)||written!=pulse.Length)throw new Exception("La señal de gaveta quedó sin confirmar.");}finally{EndPagePrinter(printer);}}finally{EndDocPrinter(printer);}
   }finally{ClosePrinter(printer);}
 }
}
'@
if ([string]::IsNullOrWhiteSpace($env:FISITAAP_DRAWER_PRINTER)) { throw 'Selecciona la impresora de Windows para abrir la gaveta.' }
[FisitaapDrawer]::Pulse($env:FISITAAP_DRAWER_PRINTER)
