package com.fisitaap.android;

import org.junit.Test;
import org.junit.Rule;
import org.junit.rules.TemporaryFolder;
import static org.junit.Assert.*;
import java.io.*;
import java.net.*;
import java.util.concurrent.*;
import java.util.concurrent.atomic.AtomicInteger;

public final class SafetyTest {
    @Rule public TemporaryFolder temp=new TemporaryFolder();
    @Test public void onlyPrivateUnambiguousPrinterAddresses() {
        for(String ip:new String[]{"192.168.1.50","10.0.0.2","172.16.0.1","172.31.255.254"})assertTrue(ip,Addresses.privateIPv4(ip));
        for(String ip:new String[]{"127.0.0.1","8.8.8.8","172.15.0.1","172.32.0.1","169.254.1.2","224.0.0.1","192.168.999.1","192.168.001.1","printer.example.com","192.168.1.50/route","0xC0A80132"})assertFalse(ip,Addresses.privateIPv4(ip));
    }
    @Test public void configurationDoesNotAcceptCredentialsOrPublicCleartext() {
        assertEquals("https://fisitaap.com",Addresses.webBase("https://fisitaap.com/"));
        assertEquals("https://example.com/app",Addresses.webBase("https://example.com/app/"));
        assertEquals("http://192.168.1.20:18766",Addresses.central("http://192.168.1.20:18766/"));
        for(String value:new String[]{"http://example.com","https://user:pass@example.com","https://example.com/?token=a","https://example.com/#x"})assertThrows(IllegalArgumentException.class,()->Addresses.webBase(value));
        for(String value:new String[]{"http://8.8.8.8:18766","https://192.168.1.20:18766","http://192.168.1.20:18765","http://user:pass@192.168.1.20:18766"})assertThrows(IllegalArgumentException.class,()->Addresses.central(value));
        assertTrue(Addresses.sameOrigin("https://fisitaap.com:443/admin/recibo","https://fisitaap.com"));
        assertFalse(Addresses.sameOrigin("https://fisitaap.com.evil.example","https://fisitaap.com"));
        assertFalse(Addresses.sameOrigin("http://fisitaap.com","https://fisitaap.com"));
    }
    @Test public void completedNetworkJobSurvivesRestartWithoutPrintingTwice() throws Exception {
        File directory=temp.newFolder(); AtomicInteger sends=new AtomicInteger();
        assertFalse(new PrintLedger(directory).deliver("sale-1","receipt|printer",()->sends.incrementAndGet()));
        assertTrue(new PrintLedger(directory).deliver("sale-1","receipt|printer",()->sends.incrementAndGet()));
        assertEquals(1,sends.get());
        assertThrows(IOException.class,()->new PrintLedger(directory).deliver("sale-1","changed receipt",()->sends.incrementAndGet()));
        assertEquals(1,sends.get());
    }
    @Test public void uncertainPartialWriteRequiresExplicitNewCopy() throws Exception {
        File directory=temp.newFolder(); AtomicInteger writes=new AtomicInteger();
        assertThrows(IOException.class,()->new PrintLedger(directory).deliver("sale-2","receipt",()->{writes.incrementAndGet();throw new IOException("Socket closed after partial write");}));
        assertThrows(IOException.class,()->new PrintLedger(directory).deliver("sale-2","receipt",()->writes.incrementAndGet()));
        assertEquals(1,writes.get());
        assertFalse(new PrintLedger(directory).deliver("sale-2-copy-new-id","receipt",()->writes.incrementAndGet()));assertEquals(2,writes.get());
    }
    @Test public void simultaneousCashierClicksSerializeAndDeduplicate() throws Exception {
        PrintLedger ledger=new PrintLedger(temp.newFolder());AtomicInteger writes=new AtomicInteger();ExecutorService workers=Executors.newFixedThreadPool(2);
        try {Callable<Boolean> operation=()->ledger.deliver("one-receipt","same content",()->writes.incrementAndGet());Future<Boolean> first=workers.submit(operation),second=workers.submit(operation);assertNotEquals(first.get(),second.get());assertEquals(1,writes.get());}finally{workers.shutdownNow();}
    }
    @Test public void durableQueueSendsToATcpReceiverOnce() throws Exception {
        try(ServerSocket server=new ServerSocket(0,1,InetAddress.getLoopbackAddress())) {
            ExecutorService worker=Executors.newSingleThreadExecutor();
            try {
                Future<byte[]> received=worker.submit(()->{try(Socket peer=server.accept()){return peer.getInputStream().readAllBytes();}});
                File directory=temp.newFolder();PrintLedger.Sender sender=()->{try(Socket socket=new Socket(InetAddress.getLoopbackAddress(),server.getLocalPort())){socket.getOutputStream().write(new byte[]{27,64,70,73,83,73,84,65,65,80,10,29,86,66,0});}};
                assertFalse(new PrintLedger(directory).deliver("tcp-receipt","content",sender));
                assertArrayEquals(new byte[]{27,64,70,73,83,73,84,65,65,80,10,29,86,66,0},received.get(5,TimeUnit.SECONDS));
                assertTrue(new PrintLedger(directory).deliver("tcp-receipt","content",sender));
            }finally{worker.shutdownNow();}
        }
    }
}
