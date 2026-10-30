package com.fisitaap.android;

import android.graphics.*;
import org.json.JSONObject;
import java.io.*;
import java.net.*;
import java.util.*;
import java.util.concurrent.*;

public final class NetworkPrinter {
    private final PrintLedger ledger;private final File printDir;
    public NetworkPrinter(File files) { printDir=new File(files,"impresion");ledger=new PrintLedger(printDir); }
    public JSONObject print(JSONObject job,String defaultHost,int defaultPort,int defaultWidth,boolean cut) throws Exception {return printInternal(job,defaultHost,defaultPort,defaultWidth,cut,false);}
    JSONObject printVerified(JSONObject job,String defaultHost,int defaultPort,int defaultWidth,boolean cut) throws Exception {return printInternal(job,defaultHost,defaultPort,defaultWidth,cut,true);}
    private JSONObject printInternal(JSONObject job,String defaultHost,int defaultPort,int defaultWidth,boolean cut,boolean verified) throws Exception {
        if(job.optBoolean("demo"))return new JSONObject().put("ok",true).put("status","simulated").put("duplicate",false);
        String id=job.optString("id"), text=job.optString("text").replaceAll("[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f\\x7f-\\x9f]", "");
        if(text.trim().isEmpty() || text.length()>180000) throw new IOException("El comprobante está vacío o es demasiado grande.");
        JSONObject profile=job.optJSONObject("printer");
        if(profile!=null&&"usb".equals(profile.optString("type")))throw new IOException("Este punto usa una impresora de Windows. Asigna un equipo Windows o cambia el punto a impresora de red para Android.");
        if(profile!=null&&"browser".equals(profile.optString("type")))return savePDF(id,text,job.optInt("width",defaultWidth));
        String host=defaultHost; int port=defaultPort;
        if(profile!=null && "network".equals(profile.optString("type"))) { host=profile.optString("host").trim(); port=profile.optInt("port",9100); }
        if(!Addresses.privateIPv4(host) || port<1 || port>65535) throw new IOException("Configura la IP privada de la impresora de red en Ajustes. Si es una comanda, revisa también su zona de impresión en la web.");
        int width=job.optInt("width",defaultWidth); if(width!=58 && width!=80) throw new IOException("El papel debe ser de 58 u 80 mm.");
        final boolean drawer=verified&&job.optBoolean("drawer")&&!job.optBoolean("copy")&&Arrays.asList("receipt","invoice").contains(job.optString("document"));
        final String printerHost=host; final int printerPort=port; final int paperWidth=width; final String content=text;
        boolean duplicate=ledger.deliver(id,host+":"+port+"|"+width+"|"+cut+(drawer?"|drawer":"")+"|"+text,()->send(printerHost,printerPort,content,paperWidth,cut,drawer));
        return new JSONObject().put("ok",true).put("status","sent_to_printer").put("duplicate",duplicate);
    }
    private static String hexDigest(String value) throws Exception {StringBuilder hex=new StringBuilder();for(byte b:java.security.MessageDigest.getInstance("SHA-256").digest(value.getBytes(java.nio.charset.StandardCharsets.UTF_8)))hex.append(String.format(Locale.ROOT,"%02x",b&255));return hex.toString();}
    private JSONObject savePDF(String id,String text,int width) throws Exception {
        if(width!=58&&width!=80&&width!=216)throw new IOException("El papel debe ser de 58 mm, 80 mm o carta.");String digest=hexDigest(id);File target=new File(printDir,digest+".pdf");
        boolean duplicate=ledger.deliver(id,"PDF|"+width+"|"+text,()->{
            int dots=width==216?1275:width==58?384:576,lineHeight=width==216?30:width==58?27:30;Paint paint=new Paint();paint.setColor(Color.BLACK);paint.setTypeface(Typeface.MONOSPACE);paint.setTextSize(width==58?20:22);paint.setAntiAlias(false);List<String> lines=wrap(text,paint,dots-(width==216?120:16));ReceiptPdf.write(target,lines,paint,width,dots,lineHeight);
        });return new JSONObject().put("ok",true).put("status","pdf_created").put("duplicate",duplicate);
    }
    private void send(String host,int port,String text,int width,boolean cut,boolean drawer) throws Exception {
        try(Socket socket=new Socket()) {
            socket.connect(new InetSocketAddress(host,port),5000);
            ScheduledExecutorService timer=Executors.newSingleThreadScheduledExecutor();
            ScheduledFuture<?> limit=timer.schedule(()->{try{socket.close();}catch(IOException ignored){}},20,TimeUnit.SECONDS);
            try {
                OutputStream out=socket.getOutputStream(); out.write(new byte[]{27,64});if(drawer)out.write(new byte[]{27,112,0,25,(byte)250});
                // Raster preserves accents and ₡ without depending on the printer code page.
                int dots=width==58?384:576; Paint paint=new Paint(); paint.setColor(Color.BLACK); paint.setTypeface(Typeface.MONOSPACE); paint.setTextSize(width==58?20:22); paint.setAntiAlias(false);
                List<String> lines=wrap(text,paint,dots-16); int height=width==58?27:30;
                for(int start=0;start<lines.size();start+=16) {
                    int count=Math.min(16,lines.size()-start),rows=count*height+4;
                    Bitmap image=Bitmap.createBitmap(dots,rows,Bitmap.Config.ARGB_8888); Canvas canvas=new Canvas(image);canvas.drawColor(Color.WHITE);
                    for(int i=0;i<count;i++) canvas.drawText(lines.get(start+i),8,(i+1)*height-7,paint);
                    int stride=dots/8; byte[] raster=new byte[stride*rows]; int[] pixels=new int[dots];
                    for(int y=0;y<rows;y++){image.getPixels(pixels,0,dots,0,y,dots,1);for(int x=0;x<dots;x++)if((pixels[x]&0xffffff)<0x808080)raster[y*stride+x/8]|=(byte)(128>>(x%8));}
                    image.recycle(); out.write(new byte[]{29,118,48,0,(byte)stride,(byte)(stride>>8),(byte)rows,(byte)(rows>>8)});out.write(raster);
                }
                out.write(new byte[]{10,10,10});if(cut)out.write(new byte[]{29,86,66,0});out.flush();
            } finally {limit.cancel(false);timer.shutdownNow();}
        }
    }
    private List<String> wrap(String text,Paint paint,int maxWidth) {
        List<String> result=new ArrayList<>();
        for(String source:text.replace("\r","").replace("\t","    ").split("\n",-1)) {
            if(source.isEmpty()){result.add("");continue;}
            while(!source.isEmpty()) { int count=paint.breakText(source,true,maxWidth,null); count=Math.max(1,count); if(count<source.length() && Character.isHighSurrogate(source.charAt(count-1)))count--; count=Math.max(1,count); result.add(source.substring(0,count));source=source.substring(count); }
        }
        return result;
    }
}
