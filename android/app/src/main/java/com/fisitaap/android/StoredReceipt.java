package com.fisitaap.android;

import org.json.*;
import java.util.*;

/** Builds a receipt exclusively from an already durable local sale. */
final class StoredReceipt {
    private StoredReceipt() {}
    static JSONObject build(JSONObject state,String id,boolean copy) throws Exception {return build(state,id,copy,"printer");}
    static JSONObject build(JSONObject state,String id,boolean copy,String output) throws Exception {
        if(!Arrays.asList("printer","letter_pdf","receipt_pdf").contains(output))throw new IllegalArgumentException("Formato de comprobante inválido.");
        JSONObject sale=null;JSONArray sales=state.getJSONArray("sales");for(int i=0;i<sales.length();i++)if(sales.getJSONObject(i).getString("id").equals(id))sale=sales.getJSONObject(i);
        if(sale==null)throw new IllegalArgumentException("La venta no está guardada en este Android.");
        JSONObject snapshot=state.getJSONObject("snapshot"),tenant=snapshot.getJSONObject("tenant"),branch=snapshot.getJSONObject("branch"),quote=sale.getJSONObject("quote");
        List<String> lines=new ArrayList<>();lines.add(tenant.getString("name"));lines.add(branch.getString("name"));lines.add("RECIBO · "+id);if(!tenant.isNull("tax_id"))lines.add("Identificación: "+tenant.getString("tax_id"));
        JSONArray items=quote.getJSONArray("lines");for(int i=0;i<items.length();i++){JSONObject item=items.getJSONObject(i);lines.add(item.getDouble("quantity")/1000+" × "+item.getString("name")+" · CRC "+String.format(Locale.ROOT,"%.2f",(item.getLong("net")+item.getLong("tax"))/100.0));JSONArray options=item.optJSONArray("option_labels");if(options!=null)for(int j=0;j<options.length();j++)lines.add(options.getString(j));if(!item.optString("note").isEmpty())lines.add("Nota: "+item.getString("note"));}
        lines.add("TOTAL CRC "+String.format(Locale.ROOT,"%.2f",quote.getLong("total")/100.0));lines.add("Vuelto CRC "+String.format(Locale.ROOT,"%.2f",sale.getLong("change")/100.0));lines.add("Gracias por su compra.");
        JSONObject profiles=branch.optJSONObject("printers"),printer=profiles==null?null:profiles.optJSONObject("receipt"),cash=snapshot.optJSONObject("cash_state"),register=cash==null?null:cash.optJSONObject("register");
        if(!"printer".equals(output))printer=new JSONObject().put("type","browser").put("width","letter_pdf".equals(output)?216:(printer!=null&&printer.optInt("width")==58?58:80));
        boolean drawer="printer".equals(output)&&!copy&&sale.optLong("cashApplied")>0&&printer!=null&&printer.optBoolean("drawer")&&register!=null&&register.optBoolean("drawer_enabled");
        return new JSONObject().put("id","local-"+id+("printer".equals(output)?"":"-"+output)+(copy?"-copy-"+UUID.randomUUID():"")).put("document","receipt").put("text",String.join("\n",lines)).put("copy",copy).put("drawer",drawer).put("width",printer==null?80:printer.optInt("width",80)).put("printer",printer==null?new JSONObject():printer);
    }
}
