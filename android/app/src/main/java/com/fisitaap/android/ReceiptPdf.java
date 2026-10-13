package com.fisitaap.android;

import android.graphics.*;
import java.io.*;
import java.nio.charset.StandardCharsets;
import java.util.*;
import java.util.zip.DeflaterOutputStream;

/** Small image-only PDF writer: uses the same Unicode raster as thermal receipts. */
final class ReceiptPdf {
    private ReceiptPdf() {}
    static void write(File file,List<String> lines,Paint paint,int widthMM,int dots,int lineHeight) throws IOException {
        ByteArrayOutputStream pdf=new ByteArrayOutputStream();List<Integer> offsets=new ArrayList<>();offsets.add(0);int pages=Math.max(1,(lines.size()+149)/150);
        text(pdf,"%PDF-1.4\n%FISITAAP\n");object(pdf,offsets,1,"<< /Type /Catalog /Pages 2 0 R >>");StringBuilder kids=new StringBuilder();for(int i=0;i<pages;i++)kids.append(3+i*3).append(" 0 R ");object(pdf,offsets,2,"<< /Type /Pages /Count "+pages+" /Kids ["+kids+"] >>");
        for(int page=0;page<pages;page++){
            int start=page*150,count=Math.min(150,lines.size()-start),rows=Math.max(32,count*lineHeight+16),stride=(dots+7)/8;Bitmap bitmap=Bitmap.createBitmap(dots,rows,Bitmap.Config.ARGB_8888);Canvas canvas=new Canvas(bitmap);canvas.drawColor(Color.WHITE);
            for(int i=0;i<count;i++)canvas.drawText(lines.get(start+i),8,(i+1)*lineHeight,paint);byte[] raster=new byte[stride*rows];Arrays.fill(raster,(byte)255);int[] pixels=new int[dots];
            for(int y=0;y<rows;y++){bitmap.getPixels(pixels,0,dots,0,y,dots,1);for(int x=0;x<dots;x++)if((pixels[x]&0xffffff)<0x808080)raster[y*stride+x/8]&=(byte)~(128>>(x%8));}bitmap.recycle();
            ByteArrayOutputStream compressed=new ByteArrayOutputStream();try(DeflaterOutputStream zip=new DeflaterOutputStream(compressed)){zip.write(raster);}byte[] image=compressed.toByteArray();int id=3+page*3;double points=widthMM/25.4*72,height=rows*points/dots;String size=String.format(Locale.ROOT,"%.4f %.4f",points,height);
            object(pdf,offsets,id,"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 "+size+"] /Resources << /XObject << /Receipt "+(id+1)+" 0 R >> >> /Contents "+(id+2)+" 0 R >>");
            stream(pdf,offsets,id+1,"/Type /XObject /Subtype /Image /Width "+dots+" /Height "+rows+" /ColorSpace /DeviceGray /BitsPerComponent 1 /Filter /FlateDecode",image);
            stream(pdf,offsets,id+2,"",String.format(Locale.ROOT,"q\n%.4f 0 0 %.4f 0 0 cm\n/Receipt Do\nQ\n",points,height).getBytes(StandardCharsets.US_ASCII));
        }
        int xref=pdf.size();text(pdf,"xref\n0 "+offsets.size()+"\n0000000000 65535 f \n");for(int i=1;i<offsets.size();i++)text(pdf,String.format(Locale.ROOT,"%010d 00000 n \n",offsets.get(i)));text(pdf,"trailer\n<< /Size "+offsets.size()+" /Root 1 0 R >>\nstartxref\n"+xref+"\n%%EOF\n");try(FileOutputStream out=new FileOutputStream(file)){pdf.writeTo(out);out.getFD().sync();}
    }
    private static void object(ByteArrayOutputStream pdf,List<Integer> offsets,int id,String body) throws IOException {offsets.add(pdf.size());text(pdf,id+" 0 obj\n"+body+"\nendobj\n");}
    private static void stream(ByteArrayOutputStream pdf,List<Integer> offsets,int id,String header,byte[] data) throws IOException {offsets.add(pdf.size());text(pdf,id+" 0 obj\n<< "+header+" /Length "+data.length+" >>\nstream\n");pdf.write(data);text(pdf,"\nendstream\nendobj\n");}
    private static void text(ByteArrayOutputStream pdf,String value) throws IOException {pdf.write(value.getBytes(StandardCharsets.US_ASCII));}
}
