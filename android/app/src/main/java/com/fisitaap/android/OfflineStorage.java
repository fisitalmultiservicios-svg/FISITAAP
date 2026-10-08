package com.fisitaap.android;

import android.content.Context;
import android.content.ContentValues;
import android.database.Cursor;
import android.database.sqlite.*;
import android.security.keystore.KeyGenParameterSpec;
import android.security.keystore.KeyProperties;
import android.webkit.JavascriptInterface;
import org.json.JSONObject;
import java.nio.charset.StandardCharsets;
import java.security.KeyStore;
import javax.crypto.*;
import javax.crypto.spec.GCMParameterSpec;
import java.util.Arrays;

/** Private encrypted SQLite journal. Exposed only inside the immutable offline asset WebView. */
public final class OfflineStorage extends SQLiteOpenHelper {
    interface Codec { byte[] seal(String text) throws Exception; String open(byte[] bytes) throws Exception; }
    private final Codec codec;
    public OfflineStorage(Context context) { this(context,keystoreCodec()); }
    OfflineStorage(Context context,Codec codec) {super(context,"offline179.db",null,2);this.codec=codec;setWriteAheadLoggingEnabled(true);}
    @Override public void onCreate(SQLiteDatabase db) {db.execSQL("CREATE TABLE journal (id INTEGER PRIMARY KEY CHECK(id=1), revision INTEGER NOT NULL, pending INTEGER NOT NULL DEFAULT 0, data BLOB NOT NULL)");db.execSQL("CREATE TABLE backups (id INTEGER PRIMARY KEY AUTOINCREMENT, created INTEGER NOT NULL, data BLOB NOT NULL)");}
    @Override public void onUpgrade(SQLiteDatabase db,int from,int to) {
        if(from==1&&to==2){db.execSQL("ALTER TABLE journal ADD COLUMN pending INTEGER NOT NULL DEFAULT 0");try(Cursor row=db.rawQuery("SELECT data FROM journal WHERE id=1",null)){if(row.moveToFirst()){ContentValues values=new ContentValues();values.put("pending",countPending(new JSONObject(codec.open(row.getBlob(0)))));db.update("journal",values,"id=1",null);}}catch(Exception e){throw new IllegalStateException("No se pudo migrar el diario. No se borró nada.",e);}return;}
        throw new IllegalStateException("La caja necesita una migración compatible. No se borró nada.");
    }
    private long countPending(JSONObject state) throws Exception {long count=0;org.json.JSONArray sales=state.getJSONArray("sales");for(int i=0;i<sales.length();i++)if(!sales.getJSONObject(i).optBoolean("synced"))count++;return count;}
    public synchronized String pending() {try(Cursor row=getReadableDatabase().rawQuery("SELECT pending FROM journal WHERE id=1",null)){return new JSONObject().put("ok",true).put("pending",row.moveToFirst()?row.getLong(0):0).toString();}catch(Exception e){return failure(e);}}
    private static Codec keystoreCodec() {return new Codec(){
        private SecretKey key() throws Exception {
            KeyStore keys=KeyStore.getInstance("AndroidKeyStore");keys.load(null);
            if(!keys.containsAlias("fisitaap-offline179")) {KeyGenerator generator=KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES,"AndroidKeyStore");generator.init(new KeyGenParameterSpec.Builder("fisitaap-offline179",KeyProperties.PURPOSE_ENCRYPT|KeyProperties.PURPOSE_DECRYPT).setBlockModes(KeyProperties.BLOCK_MODE_GCM).setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE).build());generator.generateKey();}
            return (SecretKey)keys.getKey("fisitaap-offline179",null);
        }
        public byte[] seal(String text) throws Exception {Cipher cipher=Cipher.getInstance("AES/GCM/NoPadding");cipher.init(Cipher.ENCRYPT_MODE,key());byte[] encrypted=cipher.doFinal(text.getBytes(StandardCharsets.UTF_8)),iv=cipher.getIV();if(iv.length!=12)throw new IllegalStateException("IV inválido");byte[] output=new byte[iv.length+encrypted.length];System.arraycopy(iv,0,output,0,iv.length);System.arraycopy(encrypted,0,output,iv.length,encrypted.length);return output;}
        public String open(byte[] bytes) throws Exception {if(bytes.length<28)throw new IllegalStateException("Almacenamiento incompleto");Cipher cipher=Cipher.getInstance("AES/GCM/NoPadding");cipher.init(Cipher.DECRYPT_MODE,key(),new GCMParameterSpec(128,Arrays.copyOfRange(bytes,0,12)));return new String(cipher.doFinal(Arrays.copyOfRange(bytes,12,bytes.length)),StandardCharsets.UTF_8);}
    };}
    private String failure(Exception error) {try{return new JSONObject().put("ok",false).put("error","No se pudo leer o guardar la caja. No desinstales ni borres los datos; conserva el dispositivo para recuperación.").toString();}catch(Exception ignored){return "{\"ok\":false}";}}
    @JavascriptInterface public String digest(String text) {
        try {byte[] bytes=java.security.MessageDigest.getInstance("SHA-256").digest(text.getBytes(StandardCharsets.UTF_8));StringBuilder out=new StringBuilder();for(byte b:bytes)out.append(String.format(java.util.Locale.ROOT,"%02x",b&255));return out.toString();}catch(Exception e){throw new IllegalStateException("No se pudo verificar la operación.",e);}
    }
    @JavascriptInterface public synchronized String read() {
        try(Cursor row=getReadableDatabase().rawQuery("SELECT revision,data FROM journal WHERE id=1",null)) {return new JSONObject().put("ok",true).put("revision",row.moveToFirst()?row.getLong(0):0).put("state",row.isAfterLast()||row.getCount()==0?JSONObject.NULL:codec.open(row.getBlob(1))).toString();}catch(Exception e){return failure(e);}
    }
    @JavascriptInterface public synchronized String commit(String text,long expected) {
        SQLiteDatabase db=getWritableDatabase();db.beginTransaction();
        try {
            JSONObject state=new JSONObject(text);if(state.optInt("version")!=1 || state.optJSONArray("sales")==null || state.optJSONArray("shifts")==null)throw new IllegalArgumentException("Estado inválido");
            long revision=0;try(Cursor row=db.rawQuery("SELECT revision FROM journal WHERE id=1",null)){if(row.moveToFirst())revision=row.getLong(0);}
            if(revision!=expected)throw new IllegalStateException("La caja cambió en otra pantalla");
            ContentValues values=new ContentValues();values.put("id",1);values.put("revision",revision+1);values.put("pending",countPending(state));values.put("data",codec.seal(text));
            if(revision==0)db.insertOrThrow("journal",null,values);else if(db.update("journal",values,"id=1",null)!=1)throw new IllegalStateException("No se pudo guardar el diario");
            db.setTransactionSuccessful();return new JSONObject().put("ok",true).put("revision",revision+1).toString();
        }catch(Exception e){return failure(e);}finally{db.endTransaction();}
    }
    @JavascriptInterface public synchronized String backup() {
        try {SQLiteDatabase db=getWritableDatabase();db.execSQL("INSERT INTO backups(created,data) SELECT ?,data FROM journal WHERE id=1",new Object[]{System.currentTimeMillis()});return "{\"ok\":true}";}catch(Exception e){return failure(e);}
    }
}
