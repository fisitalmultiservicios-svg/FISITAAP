package com.fisitaap.android;

import android.app.*;
import android.os.Bundle;
import android.net.Uri;
import android.webkit.*;
import android.content.*;
import android.widget.*;
import androidx.webkit.*;
import org.json.*;
import java.io.*;
import java.net.*;
import java.nio.charset.StandardCharsets;
import java.util.*;
import java.util.concurrent.*;

/** Isolated local sales UI: no web navigation, no local-network listening server. */
public final class OfflineActivity extends Activity {
    private static final String ORIGIN="https://appassets.androidplatform.net";
    private final ExecutorService network=Executors.newSingleThreadExecutor(),printing=Executors.newSingleThreadExecutor();
    private OfflineStorage storage;private WebView view;private NetworkPrinter printer;private boolean destroyed;
    @Override public void onCreate(Bundle saved) {
        super.onCreate(saved);storage=new OfflineStorage(this);printer=new NetworkPrinter(getFilesDir());
        if(!WebViewFeature.isFeatureSupported(WebViewFeature.WEB_MESSAGE_LISTENER)||!WebViewFeature.isFeatureSupported(WebViewFeature.DOCUMENT_START_SCRIPT)){new AlertDialog.Builder(this).setMessage("Actualiza Android System WebView para usar Caja local.").setPositiveButton("Cerrar",(d,w)->finish()).show();return;}
        LinearLayout layout=new LinearLayout(this);layout.setOrientation(LinearLayout.VERTICAL);layout.setOnApplyWindowInsetsListener((v,insets)->{v.setPadding(insets.getSystemWindowInsetLeft(),insets.getSystemWindowInsetTop(),insets.getSystemWindowInsetRight(),insets.getSystemWindowInsetBottom());return insets.consumeSystemWindowInsets();});Button back=new Button(this);back.setText("Volver al sistema web · conserva las ventas locales");back.setOnClickListener(v->onBackPressed());layout.addView(back);view=new WebView(this);layout.addView(view,new LinearLayout.LayoutParams(-1,0,1));setContentView(layout);
        WebSettings settings=view.getSettings();settings.setJavaScriptEnabled(true);settings.setDomStorageEnabled(true);settings.setAllowFileAccess(false);settings.setAllowContentAccess(false);settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);settings.setSupportMultipleWindows(false);
        // This object is never added to the live web view. Only signed, bundled assets execute here.
        view.addJavascriptInterface(storage,"OfflineStorage");
        Set<String> origins=Collections.singleton(ORIGIN);
        WebViewCompat.addWebMessageListener(view,"FisitaapOfflineNetwork",origins,(sender,message,origin,main,reply)->{
                if(!main || !Addresses.sameOrigin(origin.toString(),ORIGIN) || !Addresses.sameOrigin(sender.getUrl(),ORIGIN))return;
            try {String raw=message.getData();if(raw==null||raw.length()>2500000)return;JSONObject request=new JSONObject(raw);String id=request.getString("requestId");if(id.length()>100)return;network.execute(()->{JSONObject out;try{out=remote(request);}catch(Exception e){out=new JSONObject();try{out.put("transport_error","No se pudo confirmar la conexión. Las ventas siguen guardadas en este Android.");}catch(Exception ignored){}}respond(reply,id,out);});}catch(Exception ignored){}
        });
        WebViewCompat.addWebMessageListener(view,"FisitaapAndroid",origins,(sender,message,origin,main,reply)->{
            if(!main||!Addresses.sameOrigin(origin.toString(),ORIGIN)||!Addresses.sameOrigin(sender.getUrl(),ORIGIN))return;
            try {String raw=message.getData();if(raw==null||raw.length()>240000)return;JSONObject request=new JSONObject(raw);String id=request.getString("requestId");if(id.length()>100)return;
                if(!"print".equals(request.optString("action"))){respond(reply,id,new JSONObject().put("ok",false).put("error","Imprime el recibo guardado con su botón Imprimir."));return;}
                JSONObject job=request.getJSONObject("job");printing.execute(()->{JSONObject out;try{android.content.SharedPreferences prefs=getSharedPreferences("fisitaap",MODE_PRIVATE);out=printer.print(job,prefs.getString("printer_host",""),prefs.getInt("printer_port",9100),prefs.getInt("printer_width",80),prefs.getBoolean("printer_cut",true));}catch(Exception e){out=new JSONObject();try{out.put("ok",false).put("error",e.getMessage());}catch(Exception ignored){}}respond(reply,id,out);});
            }catch(Exception ignored){}
        });
        try {WebViewCompat.addDocumentStartJavaScript(view,readAsset("native-print.js"),origins);}catch(Exception e){throw new IllegalStateException(e);}
        view.setWebViewClient(new WebViewClient(){
            @Override public boolean shouldOverrideUrlLoading(WebView v,WebResourceRequest r){return true;}
            @Override public WebResourceResponse shouldInterceptRequest(WebView v,WebResourceRequest r){
                String name=r.getUrl().getPath();
                if(!Addresses.sameOrigin(r.getUrl().toString(),ORIGIN)||!"GET".equals(r.getMethod())||name==null||!name.matches("/offline/[A-Za-z0-9.-]+"))return blocked();
                String file=name.substring(1);if(!Arrays.asList("offline/index.html","offline/style.css","offline/app.js","offline/adapter.js","offline/engine.js","offline/start.js","offline/bcrypt.js","offline/mobile.js").contains(file))return blocked();
                try {String mime=file.endsWith(".js")?"application/javascript":file.endsWith(".css")?"text/css":"text/html";Map<String,String> headers=new HashMap<>();headers.put("Cache-Control","no-store");headers.put("Content-Security-Policy","default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'none'; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'");headers.put("X-Content-Type-Options","nosniff");return new WebResourceResponse(mime,"UTF-8",200,"OK",headers,getAssets().open(file));}catch(Exception e){return blocked();}
            }
            @Override public boolean onRenderProcessGone(WebView v,RenderProcessGoneDetail detail){v.destroy();view=null;new AlertDialog.Builder(OfflineActivity.this).setMessage("Android cerró la pantalla. Tus ventas confirmadas permanecen guardadas. Vuelve a abrir Caja local.").setPositiveButton("Cerrar",(d,w)->finish()).show();return true;}
        });
        view.setWebChromeClient(new WebChromeClient(){
            @Override public boolean onJsAlert(WebView v,String url,String text,JsResult result){new AlertDialog.Builder(OfflineActivity.this).setMessage(text).setPositiveButton("Aceptar",(d,w)->result.confirm()).setOnCancelListener(d->result.cancel()).show();return true;}
            @Override public boolean onJsConfirm(WebView v,String url,String text,JsResult result){new AlertDialog.Builder(OfflineActivity.this).setMessage(text).setPositiveButton("Aceptar",(d,w)->result.confirm()).setNegativeButton("Cancelar",(d,w)->result.cancel()).setOnCancelListener(d->result.cancel()).show();return true;}
            @Override public boolean onJsPrompt(WebView v,String url,String text,String value,JsPromptResult result){EditText input=new EditText(OfflineActivity.this);input.setText(value);new AlertDialog.Builder(OfflineActivity.this).setMessage(text).setView(input).setPositiveButton("Aceptar",(d,w)->result.confirm(input.getText().toString())).setNegativeButton("Cancelar",(d,w)->result.cancel()).setOnCancelListener(d->result.cancel()).show();return true;}
        });
        view.loadUrl(ORIGIN+"/offline/index.html");
    }
    private String readAsset(String name) throws IOException {try(InputStream in=getAssets().open(name)){ByteArrayOutputStream bytes=new ByteArrayOutputStream();byte[] buffer=new byte[8192];int n;while((n=in.read(buffer))!=-1)bytes.write(buffer,0,n);return bytes.toString("UTF-8");}}
    private WebResourceResponse blocked(){return new WebResourceResponse("text/plain","UTF-8",403,"Forbidden",Collections.emptyMap(),new ByteArrayInputStream(new byte[0]));}
    private JSONObject remote(JSONObject request) throws Exception {
        String base=Addresses.webBase(getSharedPreferences("fisitaap",MODE_PRIVATE).getString("web","https://fisitaap.com"));String target=request.getString("url");
        if(!Arrays.asList(base+"/api/desktop/pair",base+"/api/desktop/snapshot",base+"/api/desktop/sync",base+"/api/desktop/release").contains(target))throw new IOException("Configura la misma dirección web en Ajustes.");
        byte[] body=request.optString("body","{}").getBytes(StandardCharsets.UTF_8);if(body.length>2097152)throw new IOException("Lote demasiado grande");String token=request.optString("token");if(!token.isEmpty()&&!token.matches("Bearer [a-f0-9]{64}"))throw new IOException("Conexión inválida");
        HttpURLConnection c=(HttpURLConnection)new URL(target).openConnection();c.setConnectTimeout(10000);c.setReadTimeout(45000);c.setInstanceFollowRedirects(false);c.setRequestMethod("POST");c.setRequestProperty("Content-Type","application/json");c.setRequestProperty("Accept","application/json");if(!token.isEmpty()){c.setRequestProperty("Authorization",token);c.setRequestProperty("X-Fisitaap-Device",token.substring(7));}c.setDoOutput(true);
        try {try(OutputStream out=c.getOutputStream()){out.write(body);}int status=c.getResponseCode();InputStream input=status<400?c.getInputStream():c.getErrorStream();if(input==null)throw new IOException("Respuesta vacía");ByteArrayOutputStream bytes=new ByteArrayOutputStream();try(input){byte[] buffer=new byte[8192];int n;while((n=input.read(buffer))!=-1){bytes.write(buffer,0,n);if(bytes.size()>8388608)throw new IOException("Catálogo demasiado grande");}}return new JSONObject().put("status",status).put("body",bytes.toString("UTF-8"));}finally{c.disconnect();}
    }
    private void respond(JavaScriptReplyProxy reply,String id,JSONObject value){runOnUiThread(()->{if(!destroyed&&view!=null&&WebViewFeature.isFeatureSupported(WebViewFeature.WEB_MESSAGE_LISTENER))try{value.put("requestId",id);reply.postMessage(value.toString());}catch(Exception ignored){}});}
    @Override public void onBackPressed(){new AlertDialog.Builder(this).setMessage("¿Volver a la web? Termina el cobro y sincroniza las ventas antes de vender en otra pantalla.").setPositiveButton("Volver",(d,w)->finish()).setNegativeButton("Cancelar",null).show();}
    @Override protected void onDestroy(){destroyed=true;if(view!=null){view.removeJavascriptInterface("OfflineStorage");view.destroy();}network.shutdown();printing.shutdown();if(storage!=null)storage.close();super.onDestroy();}
}
