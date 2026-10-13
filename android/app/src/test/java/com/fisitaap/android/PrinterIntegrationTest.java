package com.fisitaap.android;

import org.junit.Test;
import org.junit.Rule;
import org.junit.runner.RunWith;
import org.junit.rules.TemporaryFolder;
import org.robolectric.RobolectricTestRunner;
import org.robolectric.annotation.Config;
import org.robolectric.annotation.GraphicsMode;
import org.json.JSONObject;
import static org.junit.Assert.*;
import java.io.*;
import java.net.*;
import java.util.*;
import java.util.concurrent.*;

/** Runs the actual Android raster generator and socket sender, not a substitute Sender. */
@RunWith(RobolectricTestRunner.class)
@Config(sdk=35)
@GraphicsMode(GraphicsMode.Mode.NATIVE)
public final class PrinterIntegrationTest {
    @Rule public TemporaryFolder temp=new TemporaryFolder();
    private String localAddress() throws Exception {
        for(NetworkInterface iface:Collections.list(NetworkInterface.getNetworkInterfaces()))
            for(InetAddress address:Collections.list(iface.getInetAddresses()))
                if(Addresses.privateIPv4(address.getHostAddress()))return address.getHostAddress();
        throw new IOException("The isolated QA runner needs a private LAN interface.");
    }
    private byte[] sendReceipt(int width,boolean cut) throws Exception {
        String host=localAddress();
        try(ServerSocket receiver=new ServerSocket(0,1,InetAddress.getByName(host))) {
            receiver.setSoTimeout(8000);ExecutorService worker=Executors.newSingleThreadExecutor();
            try {
                Future<byte[]> output=worker.submit(()->{try(Socket peer=receiver.accept()){return peer.getInputStream().readAllBytes();}});
                NetworkPrinter printer=new NetworkPrinter(temp.newFolder());
                JSONObject profile=new JSONObject().put("type","network").put("host",host).put("port",receiver.getLocalPort()).put("width",width);
                JSONObject job=new JSONObject().put("id","receipt-"+width).put("text","FISITAAP\nCafé, piña y ₡1.234,56\n"+"Línea de prueba larga 😀 ".repeat(80)).put("width",width).put("printer",profile);
                JSONObject sent=printer.print(job,"8.8.8.8",1,80,cut);
                assertTrue(sent.getBoolean("ok"));assertFalse(sent.getBoolean("duplicate"));
                byte[] result=output.get(8,TimeUnit.SECONDS);
                JSONObject duplicate=printer.print(job,"8.8.8.8",1,80,cut);assertTrue(duplicate.getBoolean("duplicate"));
                return result;
            }finally{worker.shutdownNow();}
        }
    }
    private void validateRaster(byte[] bytes,int expectedDots,boolean cut) {
        assertEquals(27,bytes[0]);assertEquals(64,bytes[1]);int offset=2,blocks=0,black=0;
        while(offset+8<=bytes.length && bytes[offset]==29 && bytes[offset+1]==118) {
            assertEquals(48,bytes[offset+2]);int stride=(bytes[offset+4]&255)|((bytes[offset+5]&255)<<8);
            int rows=(bytes[offset+6]&255)|((bytes[offset+7]&255)<<8);assertEquals(expectedDots/8,stride);assertTrue(rows>0 && rows<=484);
            int end=offset+8+stride*rows;assertTrue(end<=bytes.length);
            for(int i=offset+8;i<end;i++)if(bytes[i]!=0)black++;
            blocks++;offset=end;
        }
        assertTrue("The printer must receive actual painted pixels",black>500);assertTrue(blocks>1);
        assertEquals(10,bytes[offset++]);assertEquals(10,bytes[offset++]);assertEquals(10,bytes[offset++]);
        if(cut){assertEquals(29,bytes[offset++]);assertEquals(86,bytes[offset++]);assertEquals(66,bytes[offset++]);assertEquals(0,bytes[offset++]);}
        assertEquals(bytes.length,offset);
    }
    @Test public void actual58mmRasterAndTcpWithoutCutter() throws Exception {validateRaster(sendReceipt(58,false),384,false);}
    @Test public void actual80mmRasterAndTcpWithCutter() throws Exception {validateRaster(sendReceipt(80,true),576,true);}
    @Test public void publicPrinterAndInvalidPaperNeverCreateQueueRecords() throws Exception {
        File dir=temp.newFolder();NetworkPrinter printer=new NetworkPrinter(dir);
        JSONObject job=new JSONObject().put("id","invalid").put("text","Private receipt");
        assertThrows(IOException.class,()->printer.print(job,"8.8.8.8",9100,80,true));
        job.put("width",72);assertThrows(IOException.class,()->printer.print(job,"192.168.1.50",9100,80,true));
        assertEquals(0,Objects.requireNonNull(new File(dir,"impresion").list()).length);
    }
    @Test public void pdfPointWritesARealPdfWithoutConnectingTheDefaultNetworkPrinter() throws Exception {
        File files=temp.newFolder();NetworkPrinter printer=new NetworkPrinter(files);JSONObject profile=new JSONObject().put("type","browser").put("width",58);JSONObject job=new JSONObject().put("id","pdf-only").put("document","receipt").put("text","Recibo\nCafé y piña ₡1.000,00").put("width",58).put("printer",profile).put("drawer",true);
        JSONObject result=printer.printVerified(job,"8.8.8.8",1,80,true);assertEquals("pdf_created",result.getString("status"));File[] pdfs=new File(files,"impresion").listFiles((dir,name)->name.endsWith(".pdf"));assertNotNull(pdfs);assertEquals(1,pdfs.length);byte[] content=java.nio.file.Files.readAllBytes(pdfs[0].toPath());assertTrue(content.length>100);assertEquals("%PDF",new String(content,0,4,java.nio.charset.StandardCharsets.US_ASCII));assertTrue(printer.printVerified(job,"8.8.8.8",1,80,true).getBoolean("duplicate"));
    }
}
