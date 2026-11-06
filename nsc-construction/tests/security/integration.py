import uuid,json,urllib.request,urllib.error,http.cookiejar,unittest
BASE='http://127.0.0.1:4174/api/'
class Client:
 def __init__(self):
  self.jar=http.cookiejar.CookieJar();self.http=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(self.jar));self.csrf=''
 def call(self,path,data=None,method=None,headers=None):
  if data is not None and path.startswith('finance.php') and 'requestId' not in data:data['requestId']=str(uuid.uuid4())
  h={'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-Token':self.csrf}
  h.update(headers or {})
  req=urllib.request.Request(BASE+path,data=json.dumps(data).encode() if data is not None else None,method=method or ('POST' if data is not None else 'GET'),headers=h)
  try:r=self.http.open(req);return r.status,json.load(r)
  except urllib.error.HTTPError as e:return e.code,json.load(e)
 def login(self,user):
  self.csrf=self.call('auth.php')[1]['data']['csrf'];status,res=self.call('auth.php?action=login',{'username':user,'password':'Test-passphrase-2026!'});assert status==200,(status,res);self.csrf=res['data']['csrf'];return res
class SecurityTests(unittest.TestCase):
 def setUp(self):self.a=Client();self.a.login('owner-a');self.b=Client();self.b.login('owner-b')
 def test_login_has_no_bypass(self):
  c=Client();c.csrf=c.call('auth.php')[1]['data']['csrf']
  for p in ['password123','admin123','master123','']:
   status,_=c.call('auth.php',{'username':'owner-a','password':p});self.assertIn(status,[400,401])
 def test_unauthenticated_gate_key_is_denied(self):
  for key in ['password123','nik@master2026','0093783788278']:
   status,_=Client().call('office7.php',headers={'X-Office7-Gate-Key':key});self.assertEqual(status,401)
 def test_master_role_required(self):self.assertEqual(self.a.call('office7.php')[0],403)
 def test_no_cross_company_reads(self):
  s,r=self.a.call('projects.php');self.assertEqual(s,200);self.assertEqual([p['id'] for p in r['data']['projects']],['project-a'])
  self.assertEqual(self.a.call('projects.php?id=project-b')[0],404)
  s,r=self.a.call('finance.php?section=treasury_accounts');self.assertEqual(s,200);self.assertEqual([p['id'] for p in r['data']['accounts']],['account-a'])
 def test_no_cross_company_updates(self):
  self.assertEqual(self.a.call('projects.php',{'id':'project-b','name':'Hijacked'})[0],404)
  self.assertEqual(self.a.call('finance.php?action=create_income',{'projectId':'project-a','amount':10,'targetAccountId':'account-b'})[0],404)
 def test_csrf_and_origin(self):
  self.assertEqual(self.a.call('projects.php',{'name':'Bad'},headers={'X-CSRF-Token':'bad'})[0],403)
  self.assertEqual(self.a.call('projects.php',{'name':'Bad'},headers={'Origin':'https://evil.example'})[0],403)
 def test_reports_and_scoped_lists(self):
  for p in ['reports.php','materials.php','payroll.php','suppliers.php','subcontractors.php','apartments.php','assets.php','journal.php','finance.php?section=transfers']:
   self.assertEqual(self.a.call(p)[0],200,p)
 def test_workspace_persistence_conflict_and_isolation(self):
  s,r=self.a.call('workspace.php');self.assertEqual(s,200);revision=r['data']['revision']
  doc={'projects':[{'id':'project-a','name':'A'}],'expenses':[]}
  s,r=self.a.call('workspace.php',{'revision':revision,'document':doc},'PUT');self.assertEqual(s,200,r)
  self.assertEqual(self.a.call('workspace.php')[1]['data']['document'],doc)
  self.assertNotEqual(self.b.call('workspace.php')[1]['data']['document'],doc)
  self.assertEqual(self.a.call('workspace.php',{'revision':revision,'document':doc},'PUT')[0],409)
 def test_staff_denied_ungranted_write(self):
  c=Client();c.login('staff');self.assertEqual(c.call('workspace.php')[0],200);self.assertEqual(c.call('projects.php',{'name':'No'})[0],403)
 def test_financial_request_is_idempotent(self):
  payload={'projectId':'project-a','amount':25,'targetAccountId':'account-a','requestId':str(uuid.uuid4())}
  first=self.a.call('finance.php?action=create_income',payload);second=self.a.call('finance.php?action=create_income',payload)
  self.assertEqual(first[0],200,first);self.assertEqual(first[1]['data']['id'],second[1]['data']['id'])
  payload['amount']=30;self.assertEqual(self.a.call('finance.php?action=create_income',payload)[0],409)
 def test_password_reset_rejects_universal_code(self):
  c=Client();c.csrf=c.call('auth.php')[1]['data']['csrf'];self.assertEqual(c.call('auth.php?action=complete_reset',{'token':'123456','password':'Another-long-passphrase'})[0],400)
 def test_project_grants_and_revocation(self):
  s,r=self.a.call('users.php',{'action':'update','id':'test-staff','name':'Staff','username':'staff','email':'staff@example.test','disabled':False,'permissions':{'projectGrants':[{'projectId':'project-a','modules':{'expenses':['view','create']}}]}});self.assertEqual(s,200,r)
  staff=Client();staff.login('staff');workspace=staff.call('workspace.php')[1]['data'];doc=workspace['document'] or {}
  doc['expenses']=[{'id':'new-expense-staff','projectId':'project-a','amount':10}]
  s,r=staff.call('workspace.php',{'revision':workspace['revision'],'document':doc},'PUT');self.assertEqual(s,200,r)
  w=staff.call('workspace.php')[1]['data'];doc=w['document'];doc['expenses'][0]['amount']=20
  self.assertEqual(staff.call('workspace.php',{'revision':w['revision'],'document':doc},'PUT')[0],403)
  self.assertEqual(self.a.call('users.php',{'action':'disable','id':'test-staff'})[0],200);self.assertEqual(staff.call('workspace.php')[0],401)
  self.a.call('users.php',{'action':'update','id':'test-staff','name':'Staff','username':'staff','email':'staff@example.test','disabled':False,'permissions':{'projectGrants':[]}})
 def test_invalid_workspace_amount_is_rejected(self):
  w=self.a.call('workspace.php')[1]['data'];doc=w['document'] or {};doc['projects']=[{'id':'project-a','name':'A'}];doc['expenses']=[{'id':'bad-value','projectId':'project-a','amount':-1}]
  self.assertEqual(self.a.call('workspace.php',{'revision':w['revision'],'document':doc},'PUT')[0],400)
 def test_closed_period_blocks_payroll(self):
  w=self.a.call('workspace.php')[1]['data'];old=w['document'];doc=json.loads(json.dumps(old or {}));doc.setdefault('appSettings',{})['closedThrough']='2026-10-13'
  s,r=self.a.call('workspace.php',{'revision':w['revision'],'document':doc},'PUT');self.assertEqual(s,200,r)
  doc['labor']=[{'id':'closed-payroll','projectId':'project-a','date':'2026-10-12','daysWorked':1,'dailyRate':700,'advanceDeduction':0,'currency':'AFN'}]
  self.assertEqual(self.a.call('workspace.php',{'revision':r['data']['revision'],'document':doc},'PUT')[0],409)
  self.a.call('workspace.php',{'revision':r['data']['revision'],'document':old or {}},'PUT')
 def test_master_lock_is_enforced_for_owner(self):
  master=Client();master.login('master');w=self.a.call('workspace.php')[1]['data'];old=w['document'];doc=json.loads(json.dumps(old or {}));doc.setdefault('appSettings',{})['dashboardDesign']={'moduleSettings':{'expenses':{'locked':True}}}
  s,r=master.call('workspace.php?company=test-owner-a',{'revision':w['revision'],'document':doc},'PUT');self.assertEqual(s,200,r)
  doc['expenses']=[{'id':'locked-expense','projectId':'project-a','amount':10}]
  self.assertEqual(self.a.call('workspace.php',{'revision':r['data']['revision'],'document':doc},'PUT')[0],403)
  master.call('workspace.php?company=test-owner-a',{'revision':r['data']['revision'],'document':old or {}},'PUT')
 def test_paid_bill_currency_cannot_be_changed(self):
  w=self.a.call('workspace.php')[1]['data'];old=w['document'];doc=json.loads(json.dumps(old or {}));doc['projects']=[{'id':'project-a','name':'A'}];doc['expenses']=[{'id':'paid-currency','projectId':'project-a','amount':7000,'paidAmount':3500,'currency':'AFN','exchangeRate':70}]
  s,r=self.a.call('workspace.php',{'revision':w['revision'],'document':doc},'PUT');self.assertEqual(s,200,r)
  doc=r['data']['document'];doc['expenses'][0]['currency']='USD';self.assertEqual(self.a.call('workspace.php',{'revision':r['data']['revision'],'document':doc},'PUT')[0],409)
  self.a.call('workspace.php',{'revision':r['data']['revision'],'document':old or {}},'PUT')
 def test_report_permission_hides_contacts_and_preserves_records(self):
  w=self.a.call('workspace.php')[1]['data'];old=w['document'];doc=json.loads(json.dumps(old or {}));doc['projects']=[{'id':'project-a','name':'A'}];doc['expenses']=[{'id':'private-party','projectId':'project-a','amount':100,'partyName':'Private Supplier','phone':'0700000000','description':'Private description'}]
  s,r=self.a.call('workspace.php',{'revision':w['revision'],'document':doc},'PUT');self.assertEqual(s,200,r)
  self.a.call('users.php',{'action':'update','id':'test-staff','name':'Staff','username':'staff','email':'staff@example.test','disabled':False,'permissions':{'projectGrants':[{'projectId':'project-a','modules':{'reports':['view']}}]}})
  staff=Client();staff.login('staff');view=staff.call('workspace.php')[1]['data'];self.assertNotIn('0700000000',json.dumps(view['document']));self.assertNotIn('Private Supplier',json.dumps(view['document']))
  s,r=staff.call('workspace.php',{'revision':view['revision'],'document':view['document']},'PUT');self.assertEqual(s,200,r)
  self.assertEqual(self.a.call('workspace.php')[1]['data']['document']['expenses'][0]['partyName'],'Private Supplier')
  self.a.call('workspace.php',{'revision':r['data']['revision'],'document':old or {}},'PUT')
  self.a.call('users.php',{'action':'update','id':'test-staff','name':'Staff','username':'staff','email':'staff@example.test','disabled':False,'permissions':{'projectGrants':[]}})
 def test_logout_revokes_session(self):
  self.assertEqual(self.a.call('auth.php?action=logout',{})[0],200);self.assertEqual(self.a.call('projects.php')[0],401)
 def test_money_validation(self):
  self.assertEqual(self.a.call('finance.php?action=create_income',{'projectId':'project-a','amount':-10,'targetAccountId':'account-a'})[0],400)
 def test_income_transaction_updates_only_own_account(self):
  s,r=self.a.call('finance.php?action=create_income',{'projectId':'project-a','amount':10,'targetAccountId':'account-a'});self.assertEqual(s,200,r)
  s,r=self.b.call('finance.php?section=treasury_accounts');self.assertEqual(r['data']['accounts'][0]['current_balance'],'10000.00')
if __name__=='__main__':unittest.main(verbosity=2)
