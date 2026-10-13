"""Measure real opted-in GET timings/query counts on explicitly fictional volumes."""
import json,pathlib,re,statistics,subprocess,sys,time
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import sql
def main():
    count=sql('SELECT COUNT(*) n FROM products WHERE tenant_id=101')[0]['n'];assert count>=1000
    seed="""require '/var/www/html/app/bootstrap.php';$app->db->beginTransaction();for($r=0;$r<20;$r++){$name='PERF180 SALÓN '.$r;$room=$app->one('SELECT id FROM fisitaap_r2_rooms WHERE tenant_id=101 AND name=?',[$name]);if($room)continue;$app->exec('INSERT INTO fisitaap_r2_rooms(tenant_id,branch_id,name,service_rate,is_active) VALUES(101,301,?,10,1)',[$name]);$rid=$app->db->lastInsertId();for($s=0;$s<3;$s++)$app->exec('INSERT INTO fisitaap_r3_sectors(tenant_id,branch_id,room_id,name,color,x,y,width,height,service_enabled,service_rate) VALUES(101,301,?,?,"#e7f1ff",?,?,280,500,1,10)',[$rid,'Sector '.$s,30+$s*300,30]);for($i=0;$i<10;$i++){$app->exec('INSERT INTO restaurant_tables(tenant_id,branch_id,name,capacity) VALUES(101,301,?,4)',['PERF180-'.$r.'-'.$i]);$tid=$app->db->lastInsertId();$app->exec('INSERT INTO fisitaap_r2_floor(table_id,room_id,x,y,width,height,shape) VALUES(?,?,?,?,120,120,"square")',[$tid,$rid,60+($i%5)*180,100+(int)floor($i/5)*220]);}for($i=0;$i<5;$i++){$id=bin2hex(random_bytes(16));$app->exec('INSERT INTO fisitaap_r3_objects(id,tenant_id,branch_id,room_id,kind,label,x,y,width,height,rotation) VALUES(?,101,301,?,"plant","",?,?,60,60,0)',[$id,$rid,20+$i*190,30]);}}$app->db->commit();"""
    subprocess.run(['docker','exec','fisitaap-r2-web','php','-r',seed],capture_output=True,check=True)
    room=sql('SELECT id FROM fisitaap_r2_rooms WHERE tenant_id=101 AND name="PERF180 SALÓN 0"')[0]['id'];routes=['/laventanita/catalogo','/admin/ventas','/admin/clientes','/admin/mesas?room='+str(room),'/admin/reportes?dataset=sales&from=2020-01-01&to=2030-01-01','/admin/reportes?dataset=inventory&from=2020-01-01&to=2030-01-01']
    measures=[]
    for route in routes:
        samples=[]
        for _ in range(3):
            at=time.monotonic();p=subprocess.run(['docker','exec','fisitaap-r2-web','curl','--noproxy','*','-sS','--max-time','30','-b','/tmp/r3-owner.cookies','-H','X-Fisitaap-Performance: 1','-D','-','http://localhost'+route],capture_output=True,check=True);raw=p.stdout;head,body=raw.split(b'\r\n\r\n',1);timing=re.search(rb'Server-Timing: ([^\r\n]+)',head,re.I);assert timing,(route,head[:100]);text=timing[1].decode();php=float(re.search(r'php;dur=([\d.]+)',text)[1]);queries=int(re.search(r'queries;desc="(\d+)"',text)[1]);samples.append({'php_ms':php,'queries':queries,'body_bytes':len(body),'local_round_trip_ms':round((time.monotonic()-at)*1000,1)})
        row={'route':route,'median_php_ms':statistics.median(s['php_ms'] for s in samples),'queries':max(s['queries'] for s in samples),'body_bytes':max(s['body_bytes'] for s in samples),'median_local_round_trip_ms':statistics.median(s['local_round_trip_ms'] for s in samples)};measures.append(row);print('PASS timing',route,row['queries'],'queries;',row['median_php_ms'],'PHP ms',flush=True)
    # The exact counts are evidence; network/hosting latency is deliberately separate.
    assert measures[0]['queries']<100,'Catalog lookup unexpectedly grows to one query per product'
    report={'passed':len(measures),'measures':measures,'volumes':{'products':count,'clients':sql('SELECT COUNT(*) n FROM tenant_customers WHERE tenant_id=101')[0]['n'],'additional_rooms':20,'additional_tables':200,'additional_sectors':60,'additional_objects':100},'environment':'PHP/MariaDB fictional fixture, three samples per route; local process/HTTP time, not production speed; long report period 2020-2030','ai_session_release':'code verified before the external AI call; live provider latency is untested without production API configuration'}
    pathlib.Path('actualizaciones/1.8.0/QA-RENDIMIENTO.json').write_text(json.dumps(report,indent=2)+'\n')
if __name__=='__main__':main()
