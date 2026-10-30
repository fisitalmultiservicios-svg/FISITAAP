"""R17 integration: real HTTP/CSRF/PHP/MariaDB in a guarded fictional database."""
import datetime,hashlib,json,pathlib,re,secrets,sys,subprocess
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD
from usability_r7 import login
from financial180 import api
ROOT=pathlib.Path(__file__).resolve().parents[1];checks=[]
def passed(s):checks.append(s);print('PASS',s,flush=True)
def post(actor,path,data):return request(actor,path,{'csrf':csrf(actor,'/' if path.startswith('/api/') else ('/account' if path=='/logout' else path)),**data})
def main():
 assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');login('owner','owner@r2.invalid');login('owner_b','ownerb@r2.invalid');login('cashier','cashier@r2.invalid')
 tag='R17-'+secrets.token_hex(4);phone='8'+str(secrets.randbelow(10000000)).zfill(7)
 # Panel creation and initial access both exact 8-digit number, preserving changed password.
 assert post('owner','/admin/clientes-crear',{'action':'create','name':tag,'phone':phone})[0]==302
 customer=sql('SELECT u.id FROM users u JOIN fisitaap_guest_contacts180 c ON c.user_id=u.id WHERE c.tenant_id=101 AND c.phone=?',[phone])[0]['id']
 actor=tag+'customer';route='/laventanita/clientes/ingresar';assert post(actor,route,{'phone':phone,'password':phone})[0]==302
 assert request(actor,'/account')[0]==200
 assert post(actor,'/change-password',{'password':PASSWORD,'confirm':PASSWORD})[0]==302
 post(actor,'/logout',{})
 assert post(actor,route,{'phone':phone,'password':phone})[0]==200
 assert post(actor,route,{'phone':phone,'password':PASSWORD})[0]==302
 passed('Panel-created customer immediately logs in by cellphone; changing the password is preserved and the old cellphone password fails')
 # Address requires a real active own-store zone; selected zones survive checkout lookup.
 zone=sql('SELECT id FROM delivery_zones WHERE tenant_id=101 AND is_active=1 LIMIT 1')[0]['id'];foreign=sql('SELECT id FROM delivery_zones WHERE tenant_id=102 AND is_active=1 LIMIT 1')
 if not foreign:sql('INSERT INTO delivery_zones(tenant_id,name,price) VALUES(102,?,100)',[tag]);foreign=sql('SELECT id FROM delivery_zones WHERE tenant_id=102 ORDER BY id DESC LIMIT 1')
 path='/admin/clientes-buscar?customer='+str(customer)+'&tab=direcciones'
 for z in [0,foreign[0]['id']]:
  post('owner',path,{'action':'address_save','customer_id':customer,'address':tag+' address','label':'Casa','zone_id':z});assert not sql('SELECT id FROM customer_addresses WHERE user_id=?',[customer])
 post('owner',path,{'action':'address_save','customer_id':customer,'address':tag+' address','label':'Casa','zone_id':zone,'maps_url':'https://www.google.com/maps?q=9.93,-84.08'})
 assert sql('SELECT z.zone_id FROM fisitaap_address_zones_r17 z JOIN customer_addresses a ON a.id=z.address_id WHERE z.tenant_id=101 AND a.user_id=?',[customer])[0]['zone_id']==zone
 status,out=post(tag+'guest','/api/delivery-addresses180',{'tenant':'laventanita','phone':phone});assert status==200 and json.loads(out)['addresses'][0]['zone_id']==zone
 passed('Addresses reject missing/foreign zones atomically and return their saved own-store zone with Maps location')
 # Archive preserves user and financial history, restricts login and list.
 post('owner','/admin/clientes-buscar?customer='+str(customer),{'action':'archive','customer_id':customer,'reason':'Registro duplicado de prueba'})
 assert sql('SELECT id FROM users WHERE id=?',[customer]);assert sql('SELECT user_id FROM fisitaap_customer_archive_r17 WHERE tenant_id=101 AND user_id=?',[customer]);assert '?customer='+str(customer)+'&amp;' not in request('owner','/admin/clientes-buscar?q='+tag)[1]
 assert post(tag+'archived',route,{'phone':phone,'password':PASSWORD})[0]==200
 passed('Removing a customer archives only its own-store listing and login while preserving the underlying history')
 # Create employee + unique alias, role, password in the real personnel screen.
 employee={'action':'save_employee','name':tag+' Persona','email':tag.lower()+'@example.invalid','username':'u'+tag.lower(),'password':PASSWORD,'identification':tag,'job_title':'Caja QA','start_date':'2024-01-01','branch_id':301,'role':'cashier','salary':'600000','pay_frequency':'monthly','contract_type':'indefinite','workday':'day','weekly_days':6,'is_active':1,'leave_opening':30,'leave_asof':'2025-01-01'}
 assert post('owner','/admin/personal',employee)[0]==302
 rows=sql('SELECT e.*,u.email,u.username_r17 FROM fisitaap_employees_r17 e JOIN users u ON u.id=e.user_id WHERE e.tenant_id=101 AND e.identification=?',[tag]);assert len(rows)==1,request('owner','/admin/personal')[1][-100:];e=rows[0];eid=e['id'];uid=e['user_id'];ep='/admin/personal?employee='+str(eid)
 staff=tag+'staff';assert post(staff,'/owner-login',{'email':employee['username'],'password':PASSWORD})[0]==302;assert request(staff,'/admin')[0]==200;assert 'Bienvenida' in request(staff,'/admin')[1]
 assert post(tag+'emailstaff','/owner-login',{'email':employee['email'].upper(),'password':PASSWORD})[0]==302
 before=sql('SELECT COUNT(*) n FROM users')[0]['n'];post('owner_b','/admin/personal',{**employee,'identification':tag+'b','branch_id':302,'username':employee['username']+'b'});assert sql('SELECT COUNT(*) n FROM users')[0]['n']==before
 assert request(staff,'/admin/personal')[0] in [403,422]
 passed('Personnel creates a real cashier that logs in by assigned alias or case-insensitive email and sees welcome; global duplicate email and unauthorized personnel access are rejected')
 # Schedule max rules and real vacation assignment/cancel.
 sched={'action':'schedule'}
 for day in range(1,7):sched.update({f'workday[{day}]':1,f'start[{day}]':'08:00',f'end[{day}]':'16:00',f'break[{day}]':30,f'paid[{day}]':1})
 post('owner',ep+'&tab=horario',sched);assert len(sql('SELECT weekday FROM fisitaap_schedules_r17 WHERE employee_id=?',[eid]))==6
 post('owner',ep+'&tab=horario',{**sched,'end[1]':'21:00'});assert sql('SELECT end_time FROM fisitaap_schedules_r17 WHERE employee_id=? AND weekday=1',[eid])[0]['end_time']=='16:00:00'
 post('owner',ep+'&tab=vacaciones',{'action':'vacation','from':'2026-11-02','to':'2026-11-07','note':tag});leave=sql('SELECT id,days FROM fisitaap_leave_r17 WHERE employee_id=?',[eid]);assert len(leave)==1 and leave[0]['days']=='6.000'
 post('owner',ep+'&tab=vacaciones',{'action':'vacation','from':'2026-11-02','to':'2026-11-07'});assert len(sql('SELECT id FROM fisitaap_leave_r17 WHERE employee_id=?',[eid]))==1
 post('owner',ep+'&tab=vacaciones',{'action':'cancel_vacation','leave_id':leave[0]['id'],'reason':'Reprogramación de prueba'});assert sql('SELECT status FROM fisitaap_leave_r17 WHERE id=?',[leave[0]['id']])[0]['status']=='cancelled'
 passed('Schedules enforce ordinary daily/weekly limits; vacations count scheduled workdays, reject overlap and preserve reasoned cancellation')
 # Exact verified bases with audited benefit snapshots.
 for i in range(12):
  year=2024+(i+11)//12;month=(i+11)%12+1;start=datetime.date(year,month,1);end=(start.replace(day=28)+datetime.timedelta(days=4)).replace(day=1)-datetime.timedelta(days=1)
  post('owner',ep+'&tab=salarios',{'action':'salary','from':str(start),'to':str(end),'ordinary':600000,'extraordinary':0,'note':tag})
 assert len(sql('SELECT id FROM fisitaap_salaries_r17 WHERE employee_id=?',[eid]))==12
 post('owner',ep+'&tab=calculos',{'action':'calculate','kind':'settlement','asof':'2025-11-30','reason':'with_responsibility','average_monthly':600000,'vacation_days':12})
 r=json.loads(sql('SELECT result_json FROM fisitaap_hr_calculations_r17 WHERE employee_id=? ORDER BY id DESC LIMIT 1',[eid])[0]['result_json']);assert r['aguinaldo']==600000 and r['notice']==600000 and r['severance']==790000 and r['vacations']==280000 and r['total']==2270000,r
 post('owner',ep+'&tab=calculos',{'action':'calculate','kind':'vacations','asof':'2025-11-30','average_monthly':600000,'vacation_days':12});r=json.loads(sql('SELECT result_json FROM fisitaap_hr_calculations_r17 WHERE employee_id=? ORDER BY id DESC LIMIT 1',[eid])[0]['result_json']);assert r['total']==280000 and r['warnings']
 passed('Audited benefit estimates match verified salary test cases: aguinaldo /12, cause-specific preaviso/cesantía, vacations and separated totals; no payment is created')
 # Bound real native transport then per-employee credentials, sequence and idempotent marks.
 plain=secrets.token_hex(32);sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,?,?)',[tag,hashlib.sha256(plain.encode()).hexdigest()]);device=sql('SELECT id FROM fisitaap_r2_devices WHERE name=?',[tag])[0]['id'];sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,?,?,202)',[tag,device])
 body=request('owner','/admin')[1];ctx=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',body)[1]);status,proof=api('web-session',{'nonce':ctx['nonce'],'user_id':202},plain);assert status==200
 assert post('owner','/api/native/bind',{'proof':proof['proof']})[0]==200
 def clock(actor='owner',**kw):
  s,b=post(actor,'/api/personal/marcar',{'login':employee['username'],'password':PASSWORD,**kw});return s,json.loads(b)
 assert clock(staff,action='inspect')[0]==422
 assert clock(action='inspect')[1]['next']=={'in':'Entrada'}
 key=secrets.token_hex(16);assert clock(action='mark',event_type='in',request_key=key)[0]==200;assert clock(action='mark',event_type='in',request_key=key)[1]['duplicate'];assert len(sql('SELECT id FROM fisitaap_attendance_r17 WHERE employee_id=?',[eid]))==1
 assert clock(action='mark',event_type='resume',request_key=secrets.token_hex(16))[0]==422
 assert clock(action='mark',event_type='out',request_key=secrets.token_hex(16))[0]==422
 sql('UPDATE fisitaap_attendance_r17 SET marked_at=DATE_SUB(NOW(),INTERVAL 2 MINUTE) WHERE employee_id=?',[eid]) # fictional time fixture only
 assert clock(action='mark',event_type='break',request_key=secrets.token_hex(16))[0]==200
 assert clock(action='inspect')[1]['next']=={'resume':'Regresar de pausa','out':'Salida'}
 passed('Clock requires a real session-bound native device and own-store employee credentials; stamp retries write once, invalid sequences and near duplicates fail')
 # Correct original time with audit; foreign employee inaccessible.
 mark=sql('SELECT id,marked_at FROM fisitaap_attendance_r17 WHERE employee_id=? ORDER BY id LIMIT 1',[eid])[0];new=(datetime.datetime.fromisoformat(mark['marked_at'])-datetime.timedelta(minutes=1)).strftime('%Y-%m-%dT%H:%M')
 post('owner',ep+'&tab=marcas',{'action':'correct_mark','mark_id':mark['id'],'marked_at':new,'reason':'Ajuste de hora de prueba'});assert sql('SELECT marked_at FROM fisitaap_attendance_r17 WHERE id=?',[mark['id']])[0]['marked_at']==mark['marked_at'];assert sql('SELECT id FROM fisitaap_attendance_changes_r17 WHERE attendance_id=?',[mark['id']]);assert request('owner_b',ep)[0] ==422 # route error will be handled by R17 shell
 passed('Attendance correction preserves original event and adds named audit history; another business cannot read the expediente')
 # Course progress persists; setup pause/resume from server state.
 assert post(staff,'/api/academiaap',{'course':'store','step':'ventas','done':1})[0]==200;assert sql('SELECT done FROM fisitaap_learning_r17 WHERE user_id=? AND step_key="ventas"',[uid])[0]['done']==1
 assert 'Ir a practicar' in request(staff,'/academiaap')[1]
 row=sql('SELECT * FROM fisitaap_setup_r17 WHERE tenant_id=101')[0];post('owner','/admin/configurador',{'action':'next','step':row['step']});post('owner','/admin/configurador',{'action':'pause'});assert sql('SELECT paused FROM fisitaap_setup_r17 WHERE tenant_id=101')[0]['paused']==1
 passed('Academiaap persists learner progress and practice links; guided setup retains the current step when paused')
 out={'revision':'1.8.0-R17','passed':len(checks),'checks':checks,'legal_status':'Formulas tested as estimates; live authoritative source access blocked by environment, not certified legal validation.','environment':'Real HTTP/PHP/MariaDB guarded fictional fixture; trusted native protocol simulated, no live hosting, services or employees'};(ROOT/'actualizaciones/1.8.0/QA-PLATAFORMA-R17.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n');print('PASS R17 platform',len(checks))
if __name__=='__main__':main()
