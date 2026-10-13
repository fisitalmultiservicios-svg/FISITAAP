package com.fisitaap.android;

import android.content.Context;
import org.json.JSONObject;
import org.junit.*;
import org.junit.runner.RunWith;
import org.robolectric.*;
import org.robolectric.annotation.Config;
import java.nio.charset.StandardCharsets;
import static org.junit.Assert.*;

@RunWith(RobolectricTestRunner.class) @Config(sdk=35)
public class OfflineStorageTest {
    private Context context;
    private final OfflineStorage.Codec codec=new OfflineStorage.Codec(){public byte[] seal(String text){return ("encrypted-test:"+text).getBytes(StandardCharsets.UTF_8);}public String open(byte[] bytes){String text=new String(bytes,StandardCharsets.UTF_8);if(!text.startsWith("encrypted-test:"))throw new IllegalStateException("corrupt");return text.substring(15);}};
    @Before public void initialize(){context=RuntimeEnvironment.getApplication();context.deleteDatabase("offline179.db");}
    @Test public void atomicRevisionJournalPersistsAndRejectsStaleOrInvalidWrites() throws Exception {
        String state="{\"version\":1,\"sales\":[{\"id\":\"saved-sale\"}],\"shifts\":[]}";
        try(OfflineStorage store=new OfflineStorage(context,codec)) {
            assertEquals(0,new JSONObject(store.read()).getLong("revision"));
            assertTrue(new JSONObject(store.commit(state,0)).getBoolean("ok"));
            assertEquals(1,new JSONObject(store.pending()).getLong("pending"));
            assertFalse(new JSONObject(store.commit("{\"version\":1,\"sales\":[],\"shifts\":[]}",0)).getBoolean("ok"));
            assertFalse(new JSONObject(store.commit("{}",1)).getBoolean("ok"));
            assertEquals(state,new JSONObject(store.read()).getString("state"));
            assertTrue(new JSONObject(store.backup()).getBoolean("ok"));
        }
        try(OfflineStorage reopened=new OfflineStorage(context,codec)){assertEquals(state,new JSONObject(reopened.read()).getString("state"));assertEquals(1,new JSONObject(reopened.read()).getLong("revision"));assertTrue(new JSONObject(reopened.commit(state,1)).getBoolean("ok"));}
    }
    @Test public void failedEncryptionNeverAcknowledgesOrReplacesASavedSale() throws Exception {
        String state="{\"version\":1,\"sales\":[{\"id\":\"saved-sale\"}],\"shifts\":[]}";
        try(OfflineStorage store=new OfflineStorage(context,codec)){assertTrue(new JSONObject(store.commit(state,0)).getBoolean("ok"));}
        OfflineStorage.Codec failing=new OfflineStorage.Codec(){public byte[] seal(String text){throw new IllegalStateException("disk/key failure");}public String open(byte[] bytes) throws Exception {return codec.open(bytes);}};
        try(OfflineStorage store=new OfflineStorage(context,failing)){assertFalse(new JSONObject(store.commit(state,1)).getBoolean("ok"));assertEquals(1,new JSONObject(store.read()).getLong("revision"));assertEquals(state,new JSONObject(store.read()).getString("state"));}
    }
    @Test public void fingerprintUsesStandardSha256() {try(OfflineStorage store=new OfflineStorage(context,codec)){assertEquals("ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad",store.digest("abc"));}}
    @Test public void metadataMigrationPreservesEncryptedJournalAndPendingCount() throws Exception {
        String state="{\"version\":1,\"sales\":[{\"id\":\"pending\"},{\"id\":\"received\",\"synced\":true}],\"shifts\":[]}";
        try(android.database.sqlite.SQLiteDatabase db=context.openOrCreateDatabase("offline179.db",0,null)){
            db.execSQL("CREATE TABLE journal(id INTEGER PRIMARY KEY,revision INTEGER NOT NULL,data BLOB NOT NULL)");db.execSQL("CREATE TABLE backups(id INTEGER PRIMARY KEY,created INTEGER NOT NULL,data BLOB NOT NULL)");
            db.execSQL("INSERT INTO journal VALUES(1,8,?)",new Object[]{codec.seal(state)});db.setVersion(1);
        }
        try(OfflineStorage store=new OfflineStorage(context,codec)){assertEquals(8,new JSONObject(store.read()).getLong("revision"));assertEquals(state,new JSONObject(store.read()).getString("state"));assertEquals(1,new JSONObject(store.pending()).getLong("pending"));}
    }
    @Test public void webAuthorityAdoptsOneShiftAndPreservesPendingSalesAcrossRestart() throws Exception {
        String id="12345678-1234-4234-8234-123456789abc";
        JSONObject connection=new JSONObject().put("url","https://fisitaap.example").put("token","private-device-token");
        JSONObject snapshot=new JSONObject().put("id","catalog").put("tenant",new JSONObject().put("id",1)).put("branch",new JSONObject().put("id",2));
        JSONObject shift=new JSONObject().put("id",id).put("server_id",10).put("user_id",5).put("register","Caja asignada").put("opening_cash",10000).put("opened_at","2026-10-13T12:00:00Z").put("server_cash_net",20000).put("confirmed_max",20);
        JSONObject cash=new JSONObject().put("version",180).put("tenant_id",1).put("branch_id",2).put("device_id",3).put("register",new JSONObject().put("id",4)).put("shifts",new org.json.JSONArray().put(shift)).put("acknowledged",new org.json.JSONArray());
        JSONObject response=new JSONObject().put("cash",true).put("cash_state",cash).put("offline_policy",new JSONObject().put("version",180).put("allowed",true).put("generation",1));
        JSONObject state=new JSONObject().put("version",1).put("connection",connection).put("snapshot",snapshot).put("sales",new org.json.JSONArray().put(new JSONObject().put("id","saved-sale").put("shift_id",id).put("cashApplied",5000).put("synced",false))).put("shifts",new org.json.JSONArray());
        try(OfflineStorage store=new OfflineStorage(context,codec)){
            assertTrue(new JSONObject(store.commit(state.toString(),0)).getBoolean("ok"));store.acceptAuthority(connection,response,5);
            JSONObject saved=new JSONObject(new JSONObject(store.read()).getString("state"));assertEquals(1,saved.getJSONArray("shifts").length());assertEquals(id,saved.getJSONArray("shifts").getJSONObject(0).getString("id"));assertEquals(1,new JSONObject(store.pending()).getInt("pending"));
            cash.getJSONArray("acknowledged").put(new JSONObject().put("id","saved-sale").put("sale_id",21));shift.put("server_cash_net",25000).put("confirmed_max",21);store.acceptAuthority(connection,response,5);
            assertEquals(0,new JSONObject(store.pending()).getInt("pending"));assertFalse(new JSONObject(store.commit(state.toString(),1)).getBoolean("ok"));
            JSONObject wrong=new JSONObject(connection.toString()).put("token","other-device");try{store.acceptAuthority(wrong,response,5);fail("foreign connection accepted");}catch(IllegalStateException expected){}
        }
        try(OfflineStorage store=new OfflineStorage(context,codec)){
            JSONObject saved=new JSONObject(new JSONObject(store.read()).getString("state"));assertEquals(1,saved.getJSONArray("sales").length());assertEquals(21,saved.getJSONArray("sales").getJSONObject(0).getInt("webSale"));assertEquals(1,saved.getJSONArray("shifts").length());assertEquals(25000,saved.getJSONArray("shifts").getJSONObject(0).getLong("server_cash_net"));
            cash.put("shifts",new org.json.JSONArray());store.acceptAuthority(connection,response,5);saved=new JSONObject(new JSONObject(store.read()).getString("state"));assertTrue(saved.getJSONArray("shifts").getJSONObject(0).getBoolean("closedExternally"));
        }
    }
}
