package com.fisitaap.android;

import android.Manifest;
import android.app.*;
import android.content.*;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.net.Uri;
import android.os.*;
import android.print.PrintManager;
import android.view.*;
import android.webkit.*;
import android.widget.*;
import androidx.webkit.*;
import org.json.*;
import java.io.*;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.*;
import java.util.concurrent.*;

public final class MainActivity extends Activity {
    private SharedPreferences preferences;
    private LinearLayout root;
    private FrameLayout views;
    private WebView web, local, active;
    private TextView status;
    private Button webButton, localButton;
    private final Handler ui=new Handler(Looper.getMainLooper());
    private final ExecutorService network=Executors.newSingleThreadExecutor(), printing=Executors.newSingleThreadExecutor();
    private NetworkPrinter printer;
    private String webUrl,centralUrl,bridgeScript;
    private boolean webLoaded, webFailed, localLoaded, localFailed, polling, available, destroyed;
    private int generation, action;
    private long pending;
    private PermissionRequest cameraRequest;
    private GeolocationPermissions.Callback locationCallback;
    private String locationOrigin;
    private ValueCallback<Uri[]> upload;
    private String downloadUrl, downloadName;
    private final Runnable monitor=new Runnable(){public void run(){if(!destroyed){refresh(null);ui.postDelayed(this,10000);}}};

    @Override public void onCreate(Bundle saved) {
        super.onCreate(saved);
        if((getApplicationInfo().flags & android.content.pm.ApplicationInfo.FLAG_DEBUGGABLE)!=0) WebView.setWebContentsDebuggingEnabled(true);
        getWindow().setStatusBarColor(Color.rgb(25,59,83));
        preferences=getSharedPreferences("fisitaap",MODE_PRIVATE); printer=new NetworkPrinter(getFilesDir());
        webUrl=preferences.getString("web","https://fisitaap.com");centralUrl=preferences.getString("central","");
        try(InputStream in=getAssets().open("native-print.js")){ByteArrayOutputStream bytes=new ByteArrayOutputStream();byte[] buffer=new byte[8192];int count;while((count=in.read(buffer))!=-1)bytes.write(buffer,0,count);bridgeScript=bytes.toString("UTF-8");}catch(IOException e){throw new IllegalStateException(e);}
        if(!WebViewFeature.isFeatureSupported(WebViewFeature.WEB_MESSAGE_LISTENER) || !WebViewFeature.isFeatureSupported(WebViewFeature.DOCUMENT_START_SCRIPT)) {
            new AlertDialog.Builder(this).setTitle("Actualiza Android System WebView").setMessage("FISITAAP necesita una versión actual de Android System WebView o Chrome. Actualízala desde Google Play y vuelve a abrir la aplicación.").setPositiveButton("Abrir Google Play",(d,w)->external("https://play.google.com/store/apps/details?id=com.google.android.webview")).setNegativeButton("Cerrar",(d,w)->finish()).setCancelable(false).show();return;
        }
        createLayout();createViews();
        if(!preferences.getBoolean("configured",false)) settings();
        else openInitial();
        ui.postDelayed(monitor,10000);
    }
    private int dp(int n){return Math.round(n*getResources().getDisplayMetrics().density);}
    private Button button(String text){Button b=new Button(this);b.setText(text);b.setTextSize(12);b.setAllCaps(false);return b;}
    private void createLayout() {
        root=new LinearLayout(this);root.setOrientation(LinearLayout.VERTICAL);root.setBackgroundColor(Color.WHITE);
        root.setOnApplyWindowInsetsListener((v,insets)->{v.setPadding(insets.getSystemWindowInsetLeft(),insets.getSystemWindowInsetTop(),insets.getSystemWindowInsetRight(),insets.getSystemWindowInsetBottom());return insets.consumeSystemWindowInsets();});
        LinearLayout bar=new LinearLayout(this);bar.setPadding(dp(4),0,dp(4),0);bar.setBackgroundColor(Color.rgb(25,59,83));
        webButton=button("Sistema completo");localButton=button("Caja local");Button settings=button("⋮");settings.setContentDescription("Ajustes e impresión");
        bar.addView(webButton,new LinearLayout.LayoutParams(0,dp(52),1));bar.addView(localButton,new LinearLayout.LayoutParams(0,dp(52),1));bar.addView(settings,new LinearLayout.LayoutParams(dp(48),dp(52)));
        status=new TextView(this);status.setTextSize(12);status.setPadding(dp(10),dp(5),dp(10),dp(5));status.setText("FISITAAP Android · Comprobando conexión…");
        views=new FrameLayout(this);root.addView(bar);root.addView(status);root.addView(views,new LinearLayout.LayoutParams(-1,0,1));setContentView(root);
        webButton.setOnClickListener(v->useWeb());localButton.setOnClickListener(v->{action++;useLocal();});
        settings.setOnClickListener(v->new AlertDialog.Builder(this).setItems(new String[]{"Ajustes de conexión e impresora","Imprimir página / guardar PDF","Recargar pantalla"},(dialog,which)->{if(which==0)settings();else if(which==1)pagePrint(active);else new AlertDialog.Builder(this).setMessage("Recargar puede borrar cambios que no hayas guardado en esta pantalla.").setPositiveButton("Recargar",(d,w)->active.reload()).setNegativeButton("Cancelar",null).show();}).show());
    }
    private void createViews() {
        generation++;webLoaded=false;webFailed=false;localLoaded=false;localFailed=false;
        if(web!=null){views.removeView(web);web.destroy();}if(local!=null){views.removeView(local);local.destroy();}
        web=createView(webUrl);local=null; // Secondary Android registers never sell through another device's local server.
        views.addView(web,new FrameLayout.LayoutParams(-1,-1));if(local!=null)views.addView(local,new FrameLayout.LayoutParams(-1,-1));show(web);
    }
    private WebView createView(String base) {
        WebView view=new WebView(this);WebSettings s=view.getSettings();s.setJavaScriptEnabled(true);s.setDomStorageEnabled(true);s.setAllowFileAccess(false);s.setAllowContentAccess(false);s.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);s.setSupportMultipleWindows(true);s.setJavaScriptCanOpenWindowsAutomatically(false);s.setBuiltInZoomControls(true);s.setDisplayZoomControls(false);s.setLoadWithOverviewMode(true);s.setUseWideViewPort(true);
        CookieManager.getInstance().setAcceptCookie(true);CookieManager.getInstance().setAcceptThirdPartyCookies(view,false);
        Set<String> origins=Collections.singleton(Addresses.origin(base));
        final int version=generation;
        if(WebViewFeature.isFeatureSupported(WebViewFeature.WEB_MESSAGE_LISTENER)) WebViewCompat.addWebMessageListener(view,"FisitaapAndroid",origins,(sender,message,sourceOrigin,isMainFrame,reply)->{
            if(destroyed || version!=generation || !isMainFrame || !Addresses.sameOrigin(sourceOrigin.toString(),base) || !Addresses.sameOrigin(sender.getUrl(),base))return;
            String data=message.getData();if(data==null || data.length()>240000)return;
            try {
                JSONObject request=new JSONObject(data);String id=request.optString("requestId"),command=request.optString("action");if(id.length()>100)return;
                if("print".equals(command)) {
                    JSONObject job=request.getJSONObject("job");String host=preferences.getString("printer_host","");int port=preferences.getInt("printer_port",9100),width=preferences.getInt("printer_width",80);boolean cut=preferences.getBoolean("printer_cut",true);
                    printing.execute(()->{JSONObject result;try{result=printer.print(job,host,port,width,cut);}catch(Exception e){result=error(e.getMessage());}respond(reply,id,result,version);});
                } else if("pagePrint".equals(command)){pagePrint(sender);respond(reply,id,new JSONObject().put("ok",true),version);}
                else respond(reply,id,error("Acción no disponible."),version);
            }catch(JSONException ignored){}
        });
        if(WebViewFeature.isFeatureSupported(WebViewFeature.DOCUMENT_START_SCRIPT)) WebViewCompat.addDocumentStartJavaScript(view,bridgeScript,origins);
        view.setWebViewClient(new WebViewClient(){
            @Override public boolean shouldOverrideUrlLoading(WebView v,WebResourceRequest request){String target=request.getUrl().toString();if(Addresses.sameOrigin(target,base))return false;if(request.isForMainFrame())external(target);return true;}
            @Override public void onPageStarted(WebView v,String url,android.graphics.Bitmap icon){if(!Addresses.sameOrigin(url,base)){v.stopLoading();return;}if(v==web)webFailed=false;else localFailed=false;setStatus(v==local?"Caja local · Los datos se guardan en el equipo central.":"Sistema web completo · Usa tu cuenta habitual.");}
            @Override public void onPageFinished(WebView v,String url){if(!Addresses.sameOrigin(url,base))return;CookieManager.getInstance().flush();if(v==web && !webFailed)webLoaded=true;else if(v==local && !localFailed)localLoaded=true;}
            @Override public void onReceivedError(WebView v,WebResourceRequest r,WebResourceError e){if(r.isForMainFrame()){if(v==web){available=false;webFailed=true;webButton.setEnabled(true);setStatus("La web no responde. Revisa cualquier cobro sin confirmar antes de pasar a Caja local.");}else{localFailed=true;localButton.setEnabled(true);setStatus("El equipo central no responde. Comprueba que esté abierto y conectado al mismo router.");}}}
            @Override public void onReceivedHttpError(WebView v,WebResourceRequest r,WebResourceResponse response){if(r.isForMainFrame() && response.getStatusCode()>=500){if(v==web){webFailed=true;webButton.setEnabled(true);}else{localFailed=true;localButton.setEnabled(true);}setStatus("El servidor respondió con un error. Revisa el cobro antes de repetirlo.");}}
            @Override public WebResourceResponse shouldInterceptRequest(WebView v,WebResourceRequest r){String url=r.getUrl().toString();if("file".equals(r.getUrl().getScheme()) || "content".equals(r.getUrl().getScheme()) || (r.isForMainFrame() && !Addresses.sameOrigin(url,base)))return new WebResourceResponse("text/plain","UTF-8",403,"Forbidden",Collections.emptyMap(),new ByteArrayInputStream(new byte[0]));return null;}
            @Override public boolean onRenderProcessGone(WebView failed,RenderProcessGoneDetail detail){
                // Android may terminate WebView under memory pressure. Keep the app and print ledger alive.
                if(destroyed)return true;
                boolean wasWeb=failed==web,wasActive=failed==active;views.removeView(failed);failed.destroy();
                WebView replacement=createView(wasWeb?webUrl:centralUrl);
                if(wasWeb){web=replacement;webLoaded=false;webFailed=true;}else{local=replacement;localLoaded=false;localFailed=true;}
                views.addView(replacement,new FrameLayout.LayoutParams(-1,-1));replacement.setVisibility(View.GONE);
                if(wasActive)show(replacement);
                setStatus("Android cerró esta pantalla. Revisa cualquier cobro sin confirmar y pulsa su botón para abrirla de nuevo. Mantén Android System WebView actualizado.");
                return true;
            }
        });
        view.setWebChromeClient(new WebChromeClient(){
            @Override public boolean onJsAlert(WebView v,String url,String message,JsResult result){new AlertDialog.Builder(MainActivity.this).setMessage(message).setPositiveButton("Aceptar",(d,w)->result.confirm()).setOnCancelListener(d->result.cancel()).show();return true;}
            @Override public boolean onJsConfirm(WebView v,String url,String message,JsResult result){new AlertDialog.Builder(MainActivity.this).setMessage(message).setPositiveButton("Aceptar",(d,w)->result.confirm()).setNegativeButton("Cancelar",(d,w)->result.cancel()).setOnCancelListener(d->result.cancel()).show();return true;}
            @Override public boolean onJsPrompt(WebView v,String url,String message,String value,JsPromptResult result){EditText input=new EditText(MainActivity.this);input.setText(value);new AlertDialog.Builder(MainActivity.this).setMessage(message).setView(input).setPositiveButton("Aceptar",(d,w)->result.confirm(input.getText().toString())).setNegativeButton("Cancelar",(d,w)->result.cancel()).setOnCancelListener(d->result.cancel()).show();return true;}
            @Override public void onPermissionRequest(PermissionRequest request){runOnUiThread(()->{if(!Addresses.sameOrigin(request.getOrigin().toString(),base) || !Addresses.sameOrigin(view.getUrl(),base)){request.deny();return;}if(Arrays.asList(request.getResources()).contains(PermissionRequest.RESOURCE_VIDEO_CAPTURE)){cameraRequest=request;if(checkSelfPermission(Manifest.permission.CAMERA)==PackageManager.PERMISSION_GRANTED)grantCamera();else requestPermissions(new String[]{Manifest.permission.CAMERA},10);}else request.deny();});}
            @Override public void onPermissionRequestCanceled(PermissionRequest request){if(cameraRequest==request)cameraRequest=null;}
            @Override public void onGeolocationPermissionsShowPrompt(String origin,GeolocationPermissions.Callback callback){if(!Addresses.sameOrigin(origin,base)){callback.invoke(origin,false,false);return;}locationCallback=callback;locationOrigin=origin;if(checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION)==PackageManager.PERMISSION_GRANTED){callback.invoke(origin,true,false);locationCallback=null;}else requestPermissions(new String[]{Manifest.permission.ACCESS_COARSE_LOCATION,Manifest.permission.ACCESS_FINE_LOCATION},11);}
            @Override public boolean onShowFileChooser(WebView v,ValueCallback<Uri[]> callback,FileChooserParams params){if(upload!=null)upload.onReceiveValue(null);upload=callback;try{Intent intent=params.createIntent();intent.addCategory(Intent.CATEGORY_OPENABLE);startActivityForResult(intent,20);}catch(Exception e){upload.onReceiveValue(null);upload=null;}return true;}
            @Override public boolean onCreateWindow(WebView v,boolean dialog,boolean gesture,Message resultMsg){if(!gesture)return false;WebView popup=new WebView(MainActivity.this);popup.setWebViewClient(new WebViewClient(){@Override public boolean shouldOverrideUrlLoading(WebView ignored,WebResourceRequest r){String target=r.getUrl().toString();if(Addresses.sameOrigin(target,base))v.loadUrl(target);else external(target);popup.destroy();return true;}});((WebView.WebViewTransport)resultMsg.obj).setWebView(popup);resultMsg.sendToTarget();return true;}
        });
        view.setDownloadListener((url,userAgent,disposition,mime,length)->{if(!Addresses.sameOrigin(url,base)){toast("Solo se descargan archivos del sistema configurado.");return;}downloadUrl=url;downloadName=URLUtil.guessFileName(url,disposition,mime);Intent save=new Intent(Intent.ACTION_CREATE_DOCUMENT);save.addCategory(Intent.CATEGORY_OPENABLE);save.setType(mime==null?"application/octet-stream":mime);save.putExtra(Intent.EXTRA_TITLE,downloadName);startActivityForResult(save,21);});
        return view;
    }
    private JSONObject error(String message){try{return new JSONObject().put("ok",false).put("error",message==null?"No se confirmó la operación.":message);}catch(JSONException e){throw new IllegalStateException(e);}}
    private void respond(JavaScriptReplyProxy reply,String id,JSONObject result,int version){ui.post(()->{if(!destroyed && version==generation && WebViewFeature.isFeatureSupported(WebViewFeature.WEB_MESSAGE_LISTENER)){try{result.put("requestId",id);reply.postMessage(result.toString());}catch(Exception ignored){}}});}
    private void openInitial(){final int requested=action;refresh(info->{if(requested!=action || destroyed)return;if(available&&pending==0)openWeb();else if(pending>0||preferences.getBoolean("native_offline",false))useLocal();else{openWeb();setStatus("Sin internet, la caja secundaria no puede vender. Solo el principal puede usar Caja local.");}});}
    private void show(WebView view){active=view;web.setVisibility(view==web?View.VISIBLE:View.GONE);if(local!=null)local.setVisibility(view==local?View.VISIBLE:View.GONE);webButton.setEnabled(view!=web||webFailed);localButton.setEnabled(view!=local||localFailed);}
    private void openWeb(){if(pending>0){web.setVisibility(View.GONE);setStatus("Sincroniza tus ventas desde Caja local antes de volver a la web.");return;}show(web);if(!webLoaded||webFailed){String current=web.getUrl();web.loadUrl(current!=null && Addresses.sameOrigin(current,webUrl)?current:webUrl+"/owner-login");}setStatus("Sistema web completo · Usa tu cuenta habitual.");}
    @Override protected void onResume(){super.onResume();if(web!=null){try{if(nativePending()>0)web.setVisibility(View.GONE);}catch(Exception e){web.setVisibility(View.GONE);}refresh(null);}}
    private void useLocal(){startActivity(new Intent(this,OfflineActivity.class));}
    private JSONObject nativeState() throws Exception {
        try(OfflineStorage storage=new OfflineStorage(this)){JSONObject result=new JSONObject(storage.read());if(!result.optBoolean("ok"))throw new IOException("La caja local necesita revisión. No borres los datos.");return result.isNull("state")?new JSONObject():new JSONObject(result.getString("state"));}
    }
    private long nativePending() throws Exception {try(OfflineStorage storage=new OfflineStorage(this)){JSONObject result=new JSONObject(storage.pending());if(!result.optBoolean("ok"))throw new IOException("La caja local necesita revisión. No borres los datos.");return result.getLong("pending");}}
    private void useWeb(){final int requested=++action;webButton.setEnabled(false);refresh(info->{if(requested!=action)return;webButton.setEnabled(active!=web||webFailed);if(!available){toast("La web no está disponible. Puedes seguir en Caja local.");return;}if(!centralUrl.isEmpty() && info==null){toast("No se pudo comprobar el equipo central. Revisa las ventas pendientes antes de volver a la web.");return;}if(pending>0){toast("Primero pulsa Sincronizar en Caja local. Hay "+pending+" ventas pendientes.");return;}openWeb();});}
    private JSONObject getJson(String url) throws Exception {
        HttpURLConnection connection=(HttpURLConnection)new URL(url).openConnection();connection.setConnectTimeout(5000);connection.setReadTimeout(5000);connection.setInstanceFollowRedirects(false);connection.setRequestProperty("Accept","application/json");connection.setRequestProperty("Cache-Control","no-cache");
        try{int code=connection.getResponseCode();if(code>=300 && code<400)throw new IOException("Redirección inesperada.");InputStream in=code<400?connection.getInputStream():connection.getErrorStream();if(in==null)throw new IOException("Respuesta vacía.");ByteArrayOutputStream body=new ByteArrayOutputStream();try(in){byte[] buffer=new byte[8192];int count;while((count=in.read(buffer))!=-1){body.write(buffer,0,count);if(body.size()>1000000)throw new IOException("Respuesta demasiado grande.");}}JSONObject json=new JSONObject(body.toString("UTF-8"));json.put("httpStatus",code);return json;}finally{connection.disconnect();}
    }
    private void refresh(java.util.function.Consumer<JSONObject> callback){
        if(polling){if(callback!=null)ui.postDelayed(()->refresh(callback),200);return;}polling=true;final int version=generation;final String central=centralUrl,base=webUrl;
        network.execute(()->{JSONObject info=null;boolean online=false;try{info=new JSONObject().put("ok",true).put("pending",nativePending());}catch(Exception ignored){try{info=new JSONObject().put("ok",false).put("pending",1);}catch(Exception ignoredAgain){}}
            try{JSONObject probe=getJson(base+"/api/desktop/status");online=probe.optInt("httpStatus")==200 && probe.optBoolean("ok") && "fisitaap".equals(probe.optString("system"));}catch(Exception ignored){}
            final JSONObject setup=info;final boolean ready=online;
            ui.post(()->{polling=false;if(destroyed || version!=generation)return;available=ready;if(setup!=null)pending=setup.optLong("pending",0);if(active==web)web.setVisibility(pending>0?View.GONE:View.VISIBLE);if(pending>0)setStatus("Hay "+pending+" ventas locales pendientes. Abre Caja local y sincroniza antes de volver a vender por la web.");if(callback!=null)callback.accept(setup);else if(active==web && !ready)setStatus("Sin internet: solo el equipo principal puede vender desde Caja local.");});
        });
    }
    private void setStatus(String value){if(status!=null)status.setText(value);}
    private void toast(String value){new AlertDialog.Builder(this).setMessage(value).setPositiveButton("Aceptar",null).show();}
    private void external(String url){try{Uri target=Uri.parse(url);if(Arrays.asList("https","mailto","tel","geo").contains(target.getScheme()))startActivity(new Intent(Intent.ACTION_VIEW,target));}catch(Exception e){toast("No hay una aplicación para abrir este enlace.");}}
    private void pagePrint(WebView view){if(view==null)return;try{((PrintManager)getSystemService(PRINT_SERVICE)).print("FISITAAP",view.createPrintDocumentAdapter("FISITAAP"),null);}catch(Exception e){toast("No se pudo abrir el servicio de impresión de Android.");}}
    private EditText field(LinearLayout form,String label,String value,int type){TextView text=new TextView(this);text.setText(label);form.addView(text);EditText input=new EditText(this);input.setText(value);input.setInputType(type);input.setSingleLine(true);form.addView(input);return input;}
    private void settings(){
        ScrollView scroll=new ScrollView(this);LinearLayout form=new LinearLayout(this);form.setPadding(dp(18),dp(8),dp(18),dp(8));form.setOrientation(LinearLayout.VERTICAL);scroll.addView(form);
        TextView intro=new TextView(this);intro.setText("Todas las cajas venden por la web con internet. Solo el equipo elegido como principal en Administración podrá vender en Caja local, aunque sea este Android. Prepara su código y contraseña local antes de desconectarte.");form.addView(intro);
        EditText site=field(form,"Dirección principal de la web",webUrl,android.text.InputType.TYPE_CLASS_TEXT|android.text.InputType.TYPE_TEXT_VARIATION_URI);
        Spinner mode=new Spinner(this);mode.setAdapter(new ArrayAdapter<>(this,android.R.layout.simple_spinner_dropdown_item,new String[]{"Iniciar por la web","Abrir Caja local si falla internet (requiere ser principal)"}));mode.setSelection(preferences.getBoolean("native_offline",false)?1:0);form.addView(mode);
        EditText central=field(form,"La caja local se guarda en este Android; ya no necesita Windows.","",android.text.InputType.TYPE_CLASS_TEXT);central.setVisibility(View.GONE);
        EditText host=field(form,"IP de la impresora: ejemplo 192.168.1.50",preferences.getString("printer_host",""),android.text.InputType.TYPE_CLASS_TEXT);
        EditText port=field(form,"Puerto de impresión (normalmente 9100)",""+preferences.getInt("printer_port",9100),android.text.InputType.TYPE_CLASS_NUMBER);
        TextView paper=new TextView(this);paper.setText("Papel predeterminado de la caja local");form.addView(paper);Spinner width=new Spinner(this);width.setAdapter(new ArrayAdapter<>(this,android.R.layout.simple_spinner_dropdown_item,new String[]{"80 mm","58 mm"}));width.setSelection(preferences.getInt("printer_width",80)==58?1:0);form.addView(width);
        CheckBox cut=new CheckBox(this);cut.setText("Cortar papel al terminar (si la impresora tiene cortador)");cut.setChecked(preferences.getBoolean("printer_cut",true));form.addView(cut);
        TextView zones=new TextView(this);zones.setText("Para cocina, barra o despacho, configura cada zona como Red / ESC-POS en Impresoras y recibos de la web. La IP de cada zona tiene prioridad sobre la impresora predeterminada de Android.");form.addView(zones);
        AlertDialog dialog=new AlertDialog.Builder(this).setTitle("FISITAAP Android 1.7.9 · Ajustes").setView(scroll).setPositiveButton("Guardar",null).setNeutralButton("Prueba de impresión",null).setNegativeButton("Cancelar",null).create();dialog.show();
        dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(v->{try{
            String newWeb=Addresses.webBase(site.getText().toString()),newCentral="",ip=host.getText().toString().trim();
            if(!newWeb.equals(webUrl)){JSONObject state=nativeState();if(nativePending()>0||state.optJSONObject("snapshot")!=null&&state.getJSONObject("snapshot").optJSONObject("offline_policy")!=null&&state.getJSONObject("snapshot").getJSONObject("offline_policy").optBoolean("allowed"))throw new IllegalArgumentException("Sincroniza las ventas y libera primero la función principal antes de cambiar de web.");}
            preferences.edit().putBoolean("native_offline",mode.getSelectedItemPosition()==1).apply();
            int number=Integer.parseInt(port.getText().toString());if(!ip.isEmpty()&&!Addresses.privateIPv4(ip))throw new IllegalArgumentException("La impresora debe tener una IP privada: 192.168…, 10… o 172.16 a 172.31…");if(number<1||number>65535)throw new IllegalArgumentException("Puerto de impresión inválido.");
            Runnable save=()->{boolean changed=!webUrl.equals(newWeb)||!centralUrl.equals(newCentral);preferences.edit().putString("web",newWeb).putString("central",newCentral).putString("printer_host",ip).putInt("printer_port",number).putInt("printer_width",width.getSelectedItemPosition()==1?58:80).putBoolean("printer_cut",cut.isChecked()).putBoolean("configured",true).apply();webUrl=newWeb;centralUrl=newCentral;dialog.dismiss();if(changed){action++;createViews();openInitial();}else if(!webLoaded&&!localLoaded)openInitial();};
            if((webLoaded||localLoaded)&&(!webUrl.equals(newWeb)||!centralUrl.equals(newCentral)))new AlertDialog.Builder(this).setMessage("Cambiar el sitio o el equipo central cerrará las pantallas abiertas. Termina los cobros y sincroniza las ventas locales antes de cambiar.").setPositiveButton("Cambiar conexión",(d,w)->save.run()).setNegativeButton("Cancelar",null).show();else save.run();
        }catch(Exception e){toast(e.getMessage());}});
        dialog.getButton(AlertDialog.BUTTON_NEUTRAL).setOnClickListener(v->{try{String ip=host.getText().toString().trim();int number=Integer.parseInt(port.getText().toString());if(!Addresses.privateIPv4(ip)||number<1||number>65535)throw new IllegalArgumentException("Escribe la IP y el puerto correctos de la impresora.");JSONObject job=new JSONObject().put("id","test-"+UUID.randomUUID()).put("text","FISITAAP Android\nPRUEBA DE IMPRESIÓN\nAcentos: á é í ó ú ñ\nMoneda: ₡ 1.250,00\nImpresión directa por la red local\n");int paperWidth=width.getSelectedItemPosition()==1?58:80;boolean shouldCut=cut.isChecked();printing.execute(()->{String message;try{printer.print(job,ip,number,paperWidth,shouldCut);message="Prueba enviada. Revisa el papel antes de guardar.";}catch(Exception e){message=e.getMessage();}String output=message;ui.post(()->{if(!destroyed)toast(output);});});}catch(Exception e){toast(e.getMessage());}});
    }
    private void grantCamera(){if(cameraRequest!=null){cameraRequest.grant(new String[]{PermissionRequest.RESOURCE_VIDEO_CAPTURE});cameraRequest=null;}}
    @Override public void onRequestPermissionsResult(int request,String[] permissions,int[] results){super.onRequestPermissionsResult(request,permissions,results);if(request==10 && cameraRequest!=null){if(results.length>0 && results[0]==PackageManager.PERMISSION_GRANTED)grantCamera();else{cameraRequest.deny();cameraRequest=null;}}else if(request==11 && locationCallback!=null){boolean granted=false;for(int result:results)if(result==PackageManager.PERMISSION_GRANTED)granted=true;locationCallback.invoke(locationOrigin,granted,false);locationCallback=null;}}
    @Override protected void onActivityResult(int request,int result,Intent data){super.onActivityResult(request,result,data);if(request==20 && upload!=null){upload.onReceiveValue(WebChromeClient.FileChooserParams.parseResult(result,data));upload=null;}else if(request==21 && result==RESULT_OK && data!=null && downloadUrl!=null){String url=downloadUrl;Uri destination=data.getData();String cookie=CookieManager.getInstance().getCookie(url);network.execute(()->{try{HttpURLConnection connection=(HttpURLConnection)new URL(url).openConnection();connection.setConnectTimeout(10000);connection.setReadTimeout(20000);connection.setInstanceFollowRedirects(false);if(cookie!=null)connection.setRequestProperty("Cookie",cookie);try{if(connection.getResponseCode()!=200)throw new IOException("El servidor no entregó el archivo.");try(InputStream in=connection.getInputStream();OutputStream out=getContentResolver().openOutputStream(destination)){if(out==null)throw new IOException("No se pudo guardar el archivo.");byte[] buffer=new byte[8192];int count;while((count=in.read(buffer))!=-1)out.write(buffer,0,count);}}finally{connection.disconnect();}ui.post(()->{if(!destroyed)toast("Archivo guardado.");});}catch(Exception e){ui.post(()->{if(!destroyed)toast("No se pudo descargar: "+e.getMessage());});}});}}
    @Override public void onBackPressed(){if(active!=null && active.canGoBack())active.goBack();else new AlertDialog.Builder(this).setMessage("¿Cerrar FISITAAP? Termina cualquier cobro antes de salir.").setPositiveButton("Cerrar",(d,w)->finish()).setNegativeButton("Cancelar",null).show();}
    @Override protected void onDestroy(){destroyed=true;action++;generation++;ui.removeCallbacksAndMessages(null);if(web!=null)web.destroy();if(local!=null)local.destroy();network.shutdown();printing.shutdown();if(upload!=null)upload.onReceiveValue(null);super.onDestroy();}
}
