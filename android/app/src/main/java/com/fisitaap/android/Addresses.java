package com.fisitaap.android;

import java.net.URI;
import java.util.Locale;

/** Only literal private IPv4 addresses can receive cleartext POS or printer traffic. */
public final class Addresses {
    private Addresses() {}
    public static boolean privateIPv4(String value) {
        if (value == null || !value.matches("[0-9]{1,3}(\\.[0-9]{1,3}){3}")) return false;
        String[] parts = value.split("\\."); int[] n = new int[4];
        for (int i=0;i<4;i++) { if (parts[i].length()>1 && parts[i].startsWith("0")) return false; n[i]=Integer.parseInt(parts[i]); if(n[i]>255) return false; }
        return n[0]==10 || (n[0]==172 && n[1]>=16 && n[1]<=31) || (n[0]==192 && n[1]==168);
    }
    public static String webBase(String value) {
        URI u = URI.create(value.trim());
        if (!"https".equals(u.getScheme()) || u.getHost()==null || u.getRawUserInfo()!=null || u.getRawQuery()!=null || u.getRawFragment()!=null) throw new IllegalArgumentException("Escribe la dirección principal con https://, sin usuario ni parámetros.");
        if (!u.getHost().contains(".") || "localhost".equals(u.getHost())) throw new IllegalArgumentException("Escribe la dirección de tu aplicación web.");
        return u.toString().replaceAll("/+$", "");
    }
    public static String central(String value) {
        if (value.trim().isEmpty()) return "";
        URI u = URI.create(value.trim());
        if (!"http".equals(u.getScheme()) || !privateIPv4(u.getHost()) || u.getPort()!=18766 || u.getRawUserInfo()!=null || u.getRawQuery()!=null || u.getRawFragment()!=null || !(u.getPath().isEmpty() || "/".equals(u.getPath()))) throw new IllegalArgumentException("Usa la dirección del equipo central: http://192.168.1.20:18766 (con su IP real).");
        return u.toString().replaceAll("/+$", "");
    }
    public static String origin(String value) {
        URI u=URI.create(value); if(u.getHost()==null) return "";
        int port=u.getPort(); String scheme=u.getScheme().toLowerCase(Locale.ROOT);
        if ((scheme.equals("https") && port==443) || (scheme.equals("http") && port==80)) port=-1;
        return scheme+"://"+u.getHost().toLowerCase(Locale.ROOT)+(port<0?"":":"+port);
    }
    public static boolean sameOrigin(String first, String second) {
        try { return !origin(first).isEmpty() && origin(first).equals(origin(second)); } catch (RuntimeException e) { return false; }
    }
}
