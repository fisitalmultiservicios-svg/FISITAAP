<?php
declare(strict_types=1);

// Retire only FISITAAP's old web cache. Preserve cookies, carts, other apps and
// the native principal device's offline storage.
function browser_recovery179(string $base):void {
    $worker=json_encode(rtrim($base,'/').'/sw.js',JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    echo '<script>window.fisitaapRefreshBrowser=async function(){const own=new URL('.$worker.',location.href);const tasks=[];if("caches" in window)tasks.push(caches.keys().then(keys=>Promise.all(keys.filter(key=>key.startsWith("fisitapp-")).map(key=>caches.delete(key)))));if("serviceWorker" in navigator)tasks.push(navigator.serviceWorker.getRegistrations().then(registrations=>Promise.all(registrations.filter(registration=>[registration.active,registration.waiting,registration.installing].some(worker=>{if(!worker)return false;const url=new URL(worker.scriptURL);return url.origin===own.origin&&url.pathname===own.pathname})).map(registration=>registration.unregister()))));const results=await Promise.allSettled(tasks);return results.every(result=>result.status==="fulfilled")};let refreshed=false;try{refreshed=sessionStorage.getItem("fisitaap-browser-179m2")==="1"}catch(_){}if(!refreshed)window.fisitaapRefreshBrowser().then(ok=>{if(ok)try{sessionStorage.setItem("fisitaap-browser-179m2","1")}catch(_){}});</script>';
}
