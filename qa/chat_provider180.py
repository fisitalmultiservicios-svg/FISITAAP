"""Exercise the configured provider with a local TLS mock, never a real API key."""
import concurrent.futures,json,pathlib,secrets,subprocess,sys,time,uuid
from chat180 import api,config,csrf,message,request,sql

PRIVATE=pathlib.Path('/workspace/fisitaap-env/chat-provider-r4')
WEB=pathlib.Path('/workspace/fisitaap-r2-test')
NAME='fisitaap-chat-provider-r4'
def run(args):return subprocess.run(args,capture_output=True,check=True).stdout
def calls():return [json.loads(line) for line in (PRIVATE/'calls.jsonl').read_text().splitlines()]
def wait_call(n):
    for _ in range(100):
        if len(calls())>=n:return
        time.sleep(.05)
    raise AssertionError('Mock did not receive the request')

def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test'
    PRIVATE.mkdir(exist_ok=True);PRIVATE.chmod(0o755)
    (PRIVATE/'calls.jsonl').write_text('');(PRIVATE/'calls.jsonl').chmod(0o666)
    (PRIVATE/'server.ext').write_text('subjectAltName=DNS:api.openai.com\nbasicConstraints=CA:FALSE\nkeyUsage=digitalSignature,keyEncipherment\nextendedKeyUsage=serverAuth\n')
    run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-days','1','-subj','/CN=FISITAAP isolated QA CA','-keyout',str(PRIVATE/'ca.key'),'-out',str(PRIVATE/'ca.crt'),'-addext','basicConstraints=critical,CA:TRUE'])
    run(['openssl','req','-newkey','rsa:2048','-nodes','-subj','/CN=api.openai.com','-keyout',str(PRIVATE/'server.key'),'-out',str(PRIVATE/'server.csr')])
    run(['openssl','x509','-req','-in',str(PRIVATE/'server.csr'),'-CA',str(PRIVATE/'ca.crt'),'-CAkey',str(PRIVATE/'ca.key'),'-CAcreateserial','-days','1','-extfile',str(PRIVATE/'server.ext'),'-out',str(PRIVATE/'server.crt')])
    for p in PRIVATE.glob('*.key'):p.chmod(0o600)
    (PRIVATE/'server.php').write_text('''<?php
    $ctx=stream_context_create(['ssl'=>['local_cert'=>'/mock/server.crt','local_pk'=>'/mock/server.key']]);
    $server=stream_socket_server('tls://0.0.0.0:443',$errno,$errstr,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,$ctx);
    if(!$server)exit(2);
    while($c=@stream_socket_accept($server,120)){
      $length=0;while(($line=fgets($c))!==false&&trim($line)!==''){if(stripos($line,'Content-Length:')===0)$length=(int)trim(substr($line,15));}
      $body='';while(strlen($body)<$length&&!feof($c))$body.=fread($c,$length-strlen($body));
      $payload=json_decode($body,true);file_put_contents('/mock/calls.jsonl',json_encode($payload)."\\n",FILE_APPEND|LOCK_EX);
      $query=$payload['input'][count($payload['input'])-1]['content']??'';
      if(str_contains($query,'QA_DELAY'))usleep(3000000);
      $fail=str_contains($query,'QA_FAIL');
      $answer=$fail?['error'=>['message'=>'Fictitious failure']]:['output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>'Respuesta de proveedor ficticio para QA.']]]]];
      $json=json_encode($answer);fwrite($c,'HTTP/1.1 '.($fail?'500 Internal Server Error':'200 OK')."\\r\\nContent-Type: application/json\\r\\nContent-Length: ".strlen($json)."\\r\\nConnection: close\\r\\n\\r\\n".$json);fclose($c);
    }
    ''')
    before=(WEB/'config.php').read_bytes();checks=[];started=False;trusted=False
    try:
        run(['docker','run','-d','--name',NAME,'--network','fisitaap-dev','--network-alias','api.openai.com','-v',str(PRIVATE)+':/mock','fisitaap-dev:php83','php','/mock/server.php']);started=True
        run(['docker','cp',str(PRIVATE/'ca.crt'),'fisitaap-r2-web:/usr/local/share/ca-certificates/fisitaap-chat-qa.crt']);trusted=True
        run(['docker','exec','fisitaap-r2-web','update-ca-certificates'])
        # Fake key exists only after DNS points at our isolated mock.
        original=before.decode();assert original.rstrip().endswith('];')
        local=original.rstrip()[:-2]+",'openai_api_key'=>'local-test-placeholder-not-a-real-secret'];"
        local=local.replace('<?php return',"<?php putenv('NO_PROXY=api.openai.com,localhost');putenv('no_proxy=api.openai.com,localhost');return")
        (WEB/'config.php').write_text(local)
        sql('DELETE FROM rate_limits');actor='provider_'+secrets.token_hex(4)
        status,out=message(actor,'Tengo un restaurante');assert status==200 and out['engine']=='ai' and not out['degraded'],out
        status,out=message(actor,'¿Y cuánto cuesta?');assert status==200 and out['engine']=='ai'
        payload=calls()[-1];assert payload['store'] is False and payload['max_output_tokens']==750 and not payload.get('tools')
        assert payload['input'][0]['content']=='Tengo un restaurante'
        assert 'Solo un principal' in payload['instructions'] and 'no inventes precios' in payload['instructions'].lower()
        assert 'Other QA' not in json.dumps(payload) and 'password' not in json.dumps(payload).lower()
        checks.append('verified TLS provider request uses bounded server history, factual sales/support prompt, no tools and store=false; public context has no private business data')
        status,out=message('master','Resumen de negocios','maestro');assert status==200 and out['engine']=='ai'
        payload=calls()[-1];assert 'administrador' in payload['instructions'] and 'no le vendas' in payload['instructions']
        data=json.loads(payload['input'][-1]['content']);assert 'Other QA' in json.dumps(data['datos_autorizados']) and 'vencidos' in data['datos_autorizados']
        assert 'openai_api_key' not in json.dumps(payload) and 'password_hash' not in json.dumps(payload)
        checks.append('master AI receives internal authorized metrics and administrative instructions without keys or passwords')
        status,out=message(actor,'Productos publicados','cliente',tenant='laventanita',branch='principal');assert status==200 and out['engine']=='ai'
        payload=calls()[-1];data=json.loads(payload['input'][-1]['content'])['datos_autorizados']
        assert data['negocio']['nombre']=='LA VENTANITA' and data['productos'] and 'Other QA' not in json.dumps(data)
        assert all('cost' not in p and 'quantity' not in p for p in data['productos'])
        checks.append('store AI gets only published products from the authorized store/branch, without costs, private stock or other business data')
        n=len(calls());status,out=message('master','Cambia el plan del negocio','maestro');assert status==200 and out['engine']=='guide' and out['manage_plan'] and len(calls())==n
        checks.append('master change intent bypasses external AI and requires the existing review/confirmation form')
        token=csrf(actor,'/');data={'profile':'tecnico','message':'QA_DELAY concurrent','request_id':uuid.uuid4().hex};n=len(calls())
        with concurrent.futures.ThreadPoolExecutor() as pool:
            pending=pool.submit(api,actor,data,token);wait_call(n+1)
            start=time.monotonic();assert request(actor,'/')[0]==200;latency=time.monotonic()-start;assert latency<1.5,latency
            assert api(actor,{'profile':'tecnico','message':'Another consultation','request_id':uuid.uuid4().hex},token)[0]==409
            assert pending.result()[1]['engine']=='ai'
        checks.append('same-session navigation completes while delayed AI runs; concurrent messages in one conversation are rejected rather than interleaved')
        n=len(calls());token=csrf(actor,'/')
        with concurrent.futures.ThreadPoolExecutor() as pool:
            pending=pool.submit(api,actor,{'profile':'tecnico','message':'QA_DELAY reset','request_id':uuid.uuid4().hex},token);wait_call(n+1)
            assert api(actor,{'profile':'tecnico','action':'reset'},token)[0]==200
            assert pending.result()[0]==409
        assert config(actor,'/')['history']==[]
        checks.append('reset during a delayed AI request prevents the old reply restoring conversation history')
        status,out=message(actor,'QA_FAIL no imprime la comanda');assert status==200 and out['engine']=='guide' and out['degraded'] and 'Administración → Impresión' in out['reply']
        checks.append('provider HTTP500 returns helpful printing guidance and releases the conversation for the next request')
        assert message(actor,'Hola')[1]['engine']=='ai'
        pathlib.Path('actualizaciones/1.8.0/QA-FISICHAT-PROVEEDOR-R4.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'navigation_ms':round(latency*1000),'scope':'actual PHP cURL through locally trusted TLS provider mock on isolated Docker network; fake key; not real OpenAI model quality/cost/latency'},indent=2)+'\n')
        print('PASS',len(checks),'provider transport, private context, concurrency/reset, fallback; navigation',round(latency*1000),'ms',flush=True)
    finally:
        (WEB/'config.php').write_bytes(before)
        if trusted:
            run(['docker','exec','fisitaap-r2-web','rm','-f','/usr/local/share/ca-certificates/fisitaap-chat-qa.crt']);run(['docker','exec','fisitaap-r2-web','update-ca-certificates'])
        if started:run(['docker','rm','-f',NAME])
        sql('DELETE FROM rate_limits')

if __name__=='__main__':main()
