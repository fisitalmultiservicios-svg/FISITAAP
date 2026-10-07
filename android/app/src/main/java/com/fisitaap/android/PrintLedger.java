package com.fisitaap.android;

import java.io.*;
import java.nio.charset.StandardCharsets;
import java.nio.file.*;
import java.security.MessageDigest;

/** Persist the decision before printing. Never retry an uncertain physical delivery. */
public final class PrintLedger {
    public interface Sender { void send() throws Exception; }
    private final File directory;
    public PrintLedger(File directory) { this.directory=directory; directory.mkdirs(); }
    public static String digest(String data) throws Exception {
        byte[] hash=MessageDigest.getInstance("SHA-256").digest(data.getBytes(StandardCharsets.UTF_8));
        StringBuilder result=new StringBuilder(); for(byte b:hash)result.append(String.format("%02x", b&255)); return result.toString();
    }
    public synchronized boolean deliver(String id, String fingerprint, Sender sender) throws Exception {
        if(id==null || id.trim().isEmpty() || id.length()>240) throw new IOException("Identificador de impresión inválido.");
        File record=new File(directory,digest(id)+".job"); String hash=digest(fingerprint);
        if(record.exists()) {
            String[] previous=new String(Files.readAllBytes(record.toPath()),StandardCharsets.UTF_8).split("\n");
            if(previous.length<2 || !previous[0].equals(hash)) throw new IOException("Este comprobante ya se registró con otro contenido o impresora. Usa Reimprimir copia.");
            if("completed".equals(previous[1])) return true;
            throw new IOException("El envío anterior no quedó confirmado. Revisa el papel antes de usar Reimprimir copia.");
        }
        write(record, hash+"\nsending\n");
        sender.send();
        write(record,hash+"\ncompleted\n");
        return false;
    }
    private void write(File record,String data) throws IOException {
        File temporary=new File(record.getPath()+".tmp");
        try(FileOutputStream out=new FileOutputStream(temporary)) { out.write(data.getBytes(StandardCharsets.UTF_8)); out.getFD().sync(); }
        Files.move(temporary.toPath(),record.toPath(),StandardCopyOption.ATOMIC_MOVE,StandardCopyOption.REPLACE_EXISTING);
    }
}
