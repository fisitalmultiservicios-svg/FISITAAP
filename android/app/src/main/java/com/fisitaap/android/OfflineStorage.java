package com.fisitaap.android;

import android.content.Context;
import android.content.ContentValues;
import android.database.Cursor;
import android.database.sqlite.*;
import android.security.keystore.KeyGenParameterSpec;
import android.security.keystore.KeyProperties;
import android.webkit.JavascriptInterface;
import org.json.JSONObject;
import org.json.JSONArray;
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
    OfflineStorage(Context context,Codec codec) {super(context,"offline179.db",null,4);this.codec=codec;setWriteAheadLoggingEnabled(true);}
    @Override public void onCreate(SQLiteDatabase db) {db.execSQL("CREATE TABLE journal (id INTEGER PRIMARY KEY CHECK(id=1), revision INTEGER NOT NULL, pending INTEGER NOT NULL DEFAULT 0, data BLOB NOT NULL)");db.execSQL("CREATE TABLE backups (id INTEGER PRIMARY KEY AUTOINCREMENT, created INTEGER NOT NULL, data BLOB NOT NULL)");db.execSQL("CREATE TABLE print_acks(id INTEGER PRIMARY KEY,claim TEXT NOT NULL,data BLOB NOT NULL)");db.execSQL("CREATE TABLE catalogs180(hash TEXT PRIMARY KEY,data BLOB NOT NULL)");}
    @Override public void onUpgrade(SQLiteDatabase db,int from,int to) {
        if(from==1&&to>=2){db.execSQL("ALTER TABLE journal ADD COLUMN pending INTEGER NOT NULL DEFAULT 0");try(Cursor row=db.rawQuery("SELECT data FROM journal WHERE id=1",null)){if(row.moveToFirst()){ContentValues values=new ContentValues();values.put("pending",countPending(new JSONObject(codec.open(row.getBlob(0)))));db.update("journal",values,"id=1",null);}}catch(Exception e){throw new IllegalStateException("No se pudo migrar el diario. No se borró nada.",e);}from=2;}
        if(from==2&&to>=3){db.execSQL("CREATE TABLE print_acks(id INTEGER PRIMARY KEY,claim TEXT NOT NULL,data BLOB NOT NULL)");from=3;}
        if(from==3&&to>=4){db.execSQL("CREATE TABLE catalogs180(hash TEXT PRIMARY KEY,data BLOB NOT NULL)");db.execSQL("INSERT INTO backups(created,data) SELECT ?,data FROM journal WHERE id=1",new Object[]{System.currentTimeMillis()});from=4;}
        if(from==to)return;
        throw new IllegalStateException("La caja necesita una migración compatible. No se borró nada.");
    }
    private String hydrate(SQLiteDatabase db,String text) throws Exception {
        JSONObject state=new JSONObject(text),ref=state.optJSONObject("storage180");if(ref==null)return text;String hash=ref.getString("catalog");if(!hash.matches("[a-f0-9]{64}"))throw new IllegalStateException("Catálogo no válido");String core;
        try(Cursor row=db.rawQuery("SELECT data FROM catalogs180 WHERE hash=?",new String[]{hash})){if(!row.moveToFirst())throw new IllegalStateException("Falta el catálogo privado");core=codec.open(row.getBlob(0));}if(!digest(core).equals(hash))throw new IllegalStateException("Catálogo incompleto");JSONObject snapshot=new JSONObject(core),overlay=state.getJSONObject("snapshot");for(java.util.Iterator<String> it=overlay.keys();it.hasNext();){String key=it.next();snapshot.put(key,overlay.get(key));}JSONObject stocks=ref.getJSONObject("stocks");JSONArray products=snapshot.optJSONArray("products");
        if(products!=null)for(int i=0;i<products.length();i++){JSONObject product=products.getJSONObject(i),stock=stocks.getJSONObject(""+product.getLong("id"));if(stock.has("stock"))product.put("stock",stock.get("stock"));JSONArray components=product.optJSONArray("components");JSONObject values=stock.getJSONObject("components");if(components!=null)for(int j=0;j<components.length();j++){JSONObject component=components.getJSONObject(j);String key=""+component.getLong("id");if(values.has(key))component.put("stock",values.get(key));}}
        state.put("snapshot",snapshot);state.remove("storage180");return state.toString();
    }
    private String pack(SQLiteDatabase db,String text) throws Exception {
        JSONObject state=new JSONObject(text),source=state.optJSONObject("snapshot");if(source==null)return text;JSONObject core=new JSONObject(source.toString()),overlay=new JSONObject(),stocks=new JSONObject();for(String key:new String[]{"id","generated_at","cash_state","offline_policy"})if(core.has(key)){overlay.put(key,core.get(key));core.remove(key);}JSONArray products=core.optJSONArray("products");
        if(products!=null)for(int i=0;i<products.length();i++){JSONObject product=products.getJSONObject(i),stock=new JSONObject(),values=new JSONObject();if(product.has("stock")){stock.put("stock",product.get("stock"));product.remove("stock");}JSONArray components=product.optJSONArray("components");if(components!=null)for(int j=0;j<components.length();j++){JSONObject component=components.getJSONObject(j);if(component.has("stock")){values.put(""+component.getLong("id"),component.get("stock"));component.remove("stock");}}stock.put("components",values);stocks.put(""+product.getLong("id"),stock);}
        String raw=core.toString(),hash=digest(raw);try(Cursor row=db.rawQuery("SELECT hash FROM catalogs180 WHERE hash=?",new String[]{hash})){if(!row.moveToFirst()){ContentValues values=new ContentValues();values.put("hash",hash);values.put("data",codec.seal(raw));db.insertOrThrow("catalogs180",null,values);}}state.put("snapshot",overlay).put("storage180",new JSONObject().put("catalog",hash).put("stocks",stocks));return state.toString();
    }
    private long countPending(JSONObject state) throws Exception {long count=0;org.json.JSONArray sales=state.getJSONArray("sales");for(int i=0;i<sales.length();i++)if(!sales.getJSONObject(i).optBoolean("synced"))count++;return count;}
    synchronized JSONObject connection() throws Exception {
        try(Cursor row=getReadableDatabase().rawQuery("SELECT data FROM journal WHERE id=1",null)){
            if(!row.moveToFirst())throw new IllegalStateException("Conecta y autoriza este Android en Administración → Equipos y cajas.");
            JSONObject value=new JSONObject(codec.open(row.getBlob(0))).optJSONObject("connection");
            if(value==null)throw new IllegalStateException("Conecta este Android antes de cobrar.");return value;
        }
    }
    synchronized void trackPayment(JSONObject attempt) throws Exception {
        String key=attempt.optString("key");if(!key.matches("[a-f0-9]{32}"))throw new IllegalArgumentException("Referencia de cobro inválida.");JSONObject envelope=new JSONObject(read());if(!envelope.optBoolean("ok")||envelope.isNull("state"))throw new IllegalStateException("Primero conecta y autoriza este equipo.");JSONObject state=new JSONObject(envelope.getString("state"));if(attempt.optLong("user_id")!=state.optLong("authorizedWebUser",-1))throw new IllegalStateException("Confirma primero el acceso del cajero.");JSONArray attempts=state.optJSONArray("webPayments");if(attempts==null)attempts=new JSONArray();boolean exists=false;for(int i=0;i<attempts.length();i++)if(attempts.getJSONObject(i).getString("key").equals(key))exists=true;if(!exists)attempts.put(new JSONObject().put("key",key).put("user_id",attempt.getLong("user_id")).put("created_at",java.time.Instant.now().toString()));state.put("webPayments",attempts);if(!new JSONObject(commit(state.toString(),envelope.getLong("revision"))).optBoolean("ok"))throw new IllegalStateException("No se guardó la comprobación del cobro.");
    }
    synchronized JSONObject printAck() throws Exception {try(Cursor row=getReadableDatabase().rawQuery("SELECT data FROM print_acks ORDER BY id LIMIT 1",null)){return row.moveToFirst()?new JSONObject(codec.open(row.getBlob(0))):null;}}
    synchronized void savePrintAck(JSONObject ack) throws Exception {ContentValues values=new ContentValues();values.put("id",ack.getLong("id"));values.put("claim",ack.getString("claim"));values.put("data",codec.seal(ack.toString()));getWritableDatabase().insertOrThrow("print_acks",null,values);}
    synchronized void deletePrintAck(JSONObject ack) throws Exception {getWritableDatabase().delete("print_acks","id=? AND claim=?",new String[]{""+ack.getLong("id"),ack.getString("claim")});}
    synchronized JSONArray webPaymentKeys() throws Exception {JSONObject envelope=new JSONObject(read());if(!envelope.optBoolean("ok"))throw new IllegalStateException("La caja necesita revisión.");JSONArray keys=new JSONArray();if(!envelope.isNull("state")){JSONArray attempts=new JSONObject(envelope.getString("state")).optJSONArray("webPayments");if(attempts!=null)for(int i=0;i<Math.min(100,attempts.length());i++)keys.put(attempts.getJSONObject(i).getString("key"));}return keys;}
    synchronized org.json.JSONArray pendingIds() throws Exception {
        JSONObject envelope=new JSONObject(read());if(!envelope.optBoolean("ok"))throw new IllegalStateException("La caja necesita revisión.");org.json.JSONArray ids=new org.json.JSONArray();
        if(!envelope.isNull("state")){org.json.JSONArray sales=new JSONObject(envelope.getString("state")).getJSONArray("sales");for(int i=Math.max(0,sales.length()-1000);i<sales.length();i++)ids.put(sales.getJSONObject(i).getString("id"));}return ids;
    }
    synchronized void acceptAuthority(JSONObject connection,JSONObject response,long user) throws Exception {
        SQLiteDatabase db=getWritableDatabase();db.beginTransaction();
        try(Cursor row=db.rawQuery("SELECT revision,data FROM journal WHERE id=1",null)){
            if(!row.moveToFirst())throw new IllegalStateException("Primero conecta este Android.");long revision=row.getLong(0);JSONObject state=new JSONObject(hydrate(db,codec.open(row.getBlob(1)))),current=state.getJSONObject("connection");
            if(!current.getString("url").equals(connection.getString("url"))||!current.getString("token").equals(connection.getString("token")))throw new IllegalStateException("La conexión cambió mientras se comprobaba la caja.");
            JSONObject snapshot=state.getJSONObject("snapshot"),cash=response.getJSONObject("cash_state");
            if(cash.getInt("version")!=180||cash.getLong("tenant_id")!=snapshot.getJSONObject("tenant").getLong("id")||cash.getLong("branch_id")!=snapshot.getJSONObject("branch").getLong("id"))throw new IllegalStateException("La caja pertenece a otro negocio.");
            JSONObject prior=snapshot.optJSONObject("cash_state");if(prior!=null&&prior.getLong("device_id")!=cash.getLong("device_id"))throw new IllegalStateException("La autorización pertenece a otro equipo.");
            org.json.JSONArray sales=state.getJSONArray("sales"),shifts=state.getJSONArray("shifts"),remote=cash.getJSONArray("shifts"),acks=cash.getJSONArray("acknowledged");
            for(int i=0;i<acks.length();i++){JSONObject ack=acks.getJSONObject(i);for(int j=0;j<sales.length();j++){JSONObject sale=sales.getJSONObject(j);if(sale.getString("id").equals(ack.getString("id")))sale.put("synced",true).put("webSale",ack.getLong("sale_id")).put("error",JSONObject.NULL);}}
            for(int i=0;i<remote.length();i++){
                JSONObject incoming=remote.getJSONObject(i),shift=null;String id=incoming.getString("id");if(!id.matches("[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}"))throw new IllegalStateException("Identidad de turno inválida.");
                for(int j=0;j<shifts.length();j++)if(shifts.getJSONObject(j).getString("id").equals(id))shift=shifts.getJSONObject(j);
                if(shift==null){shift=new JSONObject(incoming.toString()).put("snapshot_id",snapshot.getString("id")).put("closed_at",JSONObject.NULL).put("syncedClosed",false).put("adopted",true);shifts.put(shift);}
                else if(shift.getLong("user_id")!=incoming.getLong("user_id")||shift.getLong("opening_cash")!=incoming.getLong("opening_cash"))throw new IllegalStateException("El turno cambió de identidad. No se borró nada.");
                shift.put("server_id",incoming.getLong("server_id")).put("server_cash_net",incoming.getLong("server_cash_net")).put("confirmed_max",incoming.getLong("confirmed_max")).put("pending_accounts",incoming.optInt("pending_accounts")).put("server_closed",false);
            }
            for(int i=0;i<shifts.length();i++){
                JSONObject shift=shifts.getJSONObject(i);if(!shift.has("server_id")||!shift.isNull("closed_at"))continue;boolean exists=false,pending=false;
                for(int j=0;j<remote.length();j++)if(remote.getJSONObject(j).getString("id").equals(shift.getString("id")))exists=true;
                for(int j=0;j<sales.length();j++)if(sales.getJSONObject(j).getString("shift_id").equals(shift.getString("id"))&&!sales.getJSONObject(j).optBoolean("synced"))pending=true;
                if(!exists){if(pending)shift.put("server_closed",true);else shift.put("closed_at",java.time.Instant.now().toString()).put("syncedClosed",true).put("closedExternally",true);}
            }
            if(cash.has("web_payments")){JSONArray priorAttempts=state.optJSONArray("webPayments"),received=cash.getJSONArray("web_payments"),remaining=new JSONArray();if(priorAttempts!=null)for(int i=0;i<priorAttempts.length();i++){JSONObject attempt=priorAttempts.getJSONObject(i);boolean confirmed=false;for(int j=0;j<received.length();j++)if(attempt.getString("key").equals(received.getString(j)))confirmed=true;if(!confirmed)remaining.put(attempt);}state.put("webPayments",remaining);}
            snapshot.put("cash_state",cash).put("offline_policy",response.getJSONObject("offline_policy"));state.put("authorityCheckedAt",java.time.Instant.now().toString());if(response.optBoolean("bound")){state.put("authorizedWebUser",response.optBoolean("cash")?user:JSONObject.NULL).put("authorizedWebAt",java.time.Instant.now().toString());String hash="";org.json.JSONArray users=snapshot.getJSONArray("users");for(int i=0;i<users.length();i++)if(users.getJSONObject(i).getLong("id")==user)hash=users.getJSONObject(i).getString("password_hash");state.put("authorizedWebHash",hash);}
            ContentValues values=new ContentValues();values.put("revision",revision+1);values.put("pending",countPending(state));values.put("data",codec.seal(pack(db,state.toString())));if(db.update("journal",values,"id=1",null)!=1)throw new IllegalStateException("No se guardó la autorización.");db.setTransactionSuccessful();
        }finally{db.endTransaction();}
    }
    synchronized void clearAuthority() throws Exception {
        JSONObject envelope=new JSONObject(read());if(!envelope.optBoolean("ok"))throw new IllegalStateException("La caja necesita revisión.");if(envelope.isNull("state"))return;JSONObject state=new JSONObject(envelope.getString("state"));state.put("authorizedWebUser",JSONObject.NULL).put("authorizedWebAt",JSONObject.NULL);if(!new JSONObject(commit(state.toString(),envelope.getLong("revision"))).optBoolean("ok"))throw new IllegalStateException("No se pudo cerrar la sesión local.");
    }
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
        try(Cursor row=getReadableDatabase().rawQuery("SELECT revision,data FROM journal WHERE id=1",null)) {return new JSONObject().put("ok",true).put("revision",row.moveToFirst()?row.getLong(0):0).put("state",row.isAfterLast()||row.getCount()==0?JSONObject.NULL:hydrate(getReadableDatabase(),codec.open(row.getBlob(1)))).toString();}catch(Exception e){return failure(e);}
    }
    @JavascriptInterface public synchronized String commit(String text,long expected) {
        SQLiteDatabase db=getWritableDatabase();db.beginTransaction();
        try {
            JSONObject state=new JSONObject(text);if(state.optInt("version")!=1 || state.optJSONArray("sales")==null || state.optJSONArray("shifts")==null)throw new IllegalArgumentException("Estado inválido");
            long revision=0;try(Cursor row=db.rawQuery("SELECT revision FROM journal WHERE id=1",null)){if(row.moveToFirst())revision=row.getLong(0);}
            if(revision!=expected)throw new IllegalStateException("La caja cambió en otra pantalla");
            ContentValues values=new ContentValues();values.put("id",1);values.put("revision",revision+1);values.put("pending",countPending(state));values.put("data",codec.seal(pack(db,text)));
            if(revision==0)db.insertOrThrow("journal",null,values);else if(db.update("journal",values,"id=1",null)!=1)throw new IllegalStateException("No se pudo guardar el diario");
            db.setTransactionSuccessful();return new JSONObject().put("ok",true).put("revision",revision+1).toString();
        }catch(Exception e){return failure(e);}finally{db.endTransaction();}
    }
    @JavascriptInterface public synchronized String backup() {
        try {SQLiteDatabase db=getWritableDatabase();try(Cursor row=db.rawQuery("SELECT data FROM journal WHERE id=1",null)){if(row.moveToFirst()){ContentValues values=new ContentValues();values.put("created",System.currentTimeMillis());values.put("data",codec.seal(hydrate(db,codec.open(row.getBlob(0)))));db.insertOrThrow("backups",null,values);}}return "{\"ok\":true}";}catch(Exception e){return failure(e);}
    }
}
