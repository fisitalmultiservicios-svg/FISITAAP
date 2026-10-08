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
}
