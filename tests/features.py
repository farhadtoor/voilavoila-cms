# Synthetic schema-1 fixture; run through tests/run.py.
import os,pathlib,json,sqlite3,re,hashlib,copy,io,zipfile,subprocess,shutil
from http_client import http as requests

BASE=os.environ['VV_TEST_BASE']; PASS='Installation_test_46!';ROOT=pathlib.Path.cwd();STORE=ROOT/'migration-state';APP=ROOT/'app'
expected=json.loads((ROOT/'migration-expected.json').read_text());checks=[];session=requests.Session()
def ck(name,ok):
 assert ok,name
 checks.append(name);print('PASS',name,flush=True)
def st():
 with sqlite3.connect(STORE/'cms.sqlite') as db:
  db.row_factory=sqlite3.Row;s=json.loads(db.execute('SELECT data FROM cms_state').fetchone()[0]);s['contents']={}
  for row in db.execute('SELECT * FROM contents'):
   c=dict(row);c.update(json.loads(c.pop('extra')));c['order']=c.pop('sort_order');c['tags']=json.loads(c['tags']);c['pinned']=bool(c['pinned']);c['menu']=bool(c['menu']);c['revisions']=[];s['contents'][c['id']]=c
  for r in db.execute('SELECT * FROM revisions ORDER BY content_id,position'):s['contents'][r['content_id']]['revisions'].append(json.loads(r['data']))
  return s
def csrf(r):return re.search(r'name="csrf" value="([a-f0-9]+)"',r.text).group(1)
def admin(v='security'):return session.get(BASE+'/admin.php',params={'view':v})
def post(a,d=None,files=None,v='security'):return session.post(BASE+'/admin.php?view='+v,data={'csrf':csrf(admin(v)),'action':a,**(d or {})},files=files)
def cli(code):return subprocess.run([os.environ['VV_TEST_PHP'],'-r',"require 'app/core/bootstrap.php';"+code],env={**os.environ,'VV_STORAGE_PATH':str(STORE)},cwd=ROOT,capture_output=True,text=True)
s=st()
ck('migration stores pages and posts in real SQLite rows',s['contents']==expected['contents'])
ck('migration preserves owner settings and plugin data',all(s[k]==expected[k] for k in ['owner','settings','plugins','plugin_data']))
ck('legacy source and migration copy preserved',(STORE/'state.php').read_bytes()==(STORE/'legacy/state-v1-before-migration.php').read_bytes())
with sqlite3.connect(STORE/'cms.sqlite') as db:
 meta=json.loads(db.execute('SELECT data FROM cms_state').fetchone()[0]); n=db.execute('SELECT COUNT(*) FROM revisions').fetchone()[0]
 ck('editorial bodies are outside the settings JSON','contents' not in meta and n==sum(len(c['revisions']) for c in expected['contents'].values()))
 ck('SQLite integrity passes',db.execute('PRAGMA integrity_check').fetchone()[0]=='ok')
for id,m in expected['media'].items():
 migrated=s['media'][id];raw=STORE/'media'/migrated['stored_name'];before=(STORE/'legacy/media'/f'{id}.php').read_bytes().split(b'\n',1)[1]
 ck('legacy media converted to raw file with original bytes',raw.read_bytes()==before and not (STORE/'media'/f'{id}.php').exists())
 public=requests.get(BASE+'/media.php?id='+id)
 ck('legacy media links preserved',public.status_code==(200 if migrated['public'] else 404))
login=session.get(BASE+'/login.php');r=session.post(BASE+'/login.php',data={'csrf':csrf(login),'password':PASS});ck('owner logs in after migration',r.status_code==200 and 'login.php' not in r.url)
for ext,fmt in [('jpeg','JPEG'),('png','PNG'),('webp','WEBP')]:
 raw=(ROOT/'fixtures'/('image.'+ext)).read_bytes()
 r=post('media.upload',{'public':'on','alt':'Format original'},files={'file':('original.'+ext,raw,'image/'+ext)},v='media');ck('upload '+ext,r.status_code==200)
 m=next(x for x in st()['media'].values() if x['name']=='original.'+ext);p=STORE/'media'/m['stored_name']
 ck('preserve '+ext+' bytes extension and SHA256',p.suffix=='.'+ext and p.read_bytes()==raw and m['sha256']==hashlib.sha256(raw).hexdigest())
 response=requests.get(BASE+'/media.php?id='+m['id']);ck('serve '+ext+' without PHP prefix',response.content==raw and response.headers['Content-Type']==m['mime'])
 ck('raw '+ext+' cannot bypass media access control',requests.get(BASE+'/storage/media/'+m['stored_name']).status_code in [403,404])
 if ext=='jpeg':
  post('media.update',{'id':m['id'],'alt':'Privé'},v='media');ck('private original JPEG stays private',requests.get(BASE+'/media.php?id='+m['id']).status_code==404 and session.get(BASE+'/media.php?id='+m['id']).content==raw)
# Trigger a captured plugin error, without a public stack trace.
probe=APP/'content/plugins/log-probe';probe.mkdir(exist_ok=True)
(probe/'cms.json').write_text(json.dumps({'api':1,'id':'log-probe','type':'plugin','name':'Probe','version':'1.0.0'}))
(probe/'plugin.php').write_text("<?php defined('VV_ROOT') || exit; throw new RuntimeException('LOG_PROBE_V11');")
r=cli("$vv->store->change(function(&$s){$s['plugins'][]='log-probe';});");assert r.returncode==0,r.stdout+r.stderr
r=session.get(BASE+'/?password=SECRET_QUERY_SHOULD_NOT_LOG')
access=(STORE/'logs/access.txt').read_text();errors=(STORE/'logs/error.txt').read_text()
ck('error and access logs are separate plain text','LOG_PROBE_V11' in errors and 'LOG_PROBE_V11' not in access and not access.startswith('<?php') and not errors.startswith('<?php'))
ck('access log excludes query and credential values','SECRET_QUERY_SHOULD_NOT_LOG' not in access and 'password=' not in access)
ck('access log includes method status path and duration',all(x in access for x in ['method=GET','status=200','path=/','duration_ms=']))
ck('plugin error stays private',r.status_code==200 and 'LOG_PROBE_V11' not in r.text and 'log-probe' not in st()['plugins'])
for kind in ['access','error']:
 r=post('logs.download',{'type':kind});ck('authenticated '+kind+' log download',r.status_code==200 and r.headers['Content-Type'].startswith('text/plain') and 'filename="'+kind+'.txt"' in r.headers['Content-Disposition'])
 ck('direct '+kind+' log denied',requests.get(BASE+'/storage/logs/'+kind+'.txt').status_code in [403,404])
ck('raw SQLite download denied',requests.get(BASE+'/storage/cms.sqlite').status_code in [403,404])
# SQL transaction must roll back a settings update when a duplicate slug fails.
before=st();php="try{$vv->store->change(function(&$s){$s['settings']['title']='MUST_ROLL_BACK';$c=reset($s['contents']);$c['id']=str_repeat('f',24);$s['contents'][$c['id']]=$c;});echo 'BAD';}catch(Throwable $e){echo 'ROLLBACK_OK';}"
r=cli(php);ck('SQLite rollback protects content and settings','ROLLBACK_OK' in r.stdout and st()['settings']==before['settings'] and st()['contents']==before['contents'])
# Log rotation is bounded and preserves plain text.
with (STORE/'logs/error.txt').open('ab') as f:f.write(b'x'*(5242880+10))
r=cli("VV\\Logger::error('rotation_test','ROTATION_OK');");ck('text logs rotate at five MiB',(STORE/'logs/error.1.txt').exists() and 'ROTATION_OK' in (STORE/'logs/error.txt').read_text() and (STORE/'logs/error.txt').stat().st_size<2000)
# Old .bin backup still imports into real extension files and SQLite.
r=post('backup.restore',{'current_password':PASS,'confirm':'RESTAURER'},files={'backup':('v1-backup.zip',(ROOT/'legacy-backup.zip').read_bytes(),'application/zip')},v='tools');ck('version 1.0 backup remains importable',r.status_code==200 and set(st()['contents'])==set(expected['contents']))
ck('old backup media restored to real extensions',all((STORE/'media'/m['stored_name']).is_file() for m in st()['media'].values()))
# A second initialization must not reimport stale legacy source data.
r=cli("$vv->store->change(function(&$s){$s['settings']['tagline']='IDEMPOTENT_V11';});");before=st();requests.get(BASE+'/');ck('migration is idempotent',st()['settings']['tagline']=='IDEMPOTENT_V11' and st()['contents']==before['contents'])
shutil.rmtree(probe)
(ROOT/'features-results.json').write_text(json.dumps({'checks':len(checks),'passed':checks},ensure_ascii=False,indent=2));print('ALL PASSED',len(checks))
