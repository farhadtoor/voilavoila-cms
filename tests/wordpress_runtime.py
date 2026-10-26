#!/usr/bin/env python3
"""Real, disposable WordPress/FPM/MariaDB test; never uses an existing database.
Development transport is explicit. This test does not certify OS/container isolation.
"""
import argparse, contextlib, json, os, re, shutil, socket, subprocess, tempfile, time, urllib.request, zipfile
from pathlib import Path
from http_client import Session
from run import SOURCE
PASS='WP_runtime_owner_130!'
GUARD=b'<?php http_response_code(404); exit; ?>\n'
def port():
 with socket.socket() as s:s.bind(('127.0.0.1',0));return s.getsockname()[1]
def ready(url,process):
 for _ in range(150):
  if process.poll() is not None:raise RuntimeError('Service stopped: '+url)
  try:urllib.request.urlopen(url,timeout=.4);return
  except Exception:time.sleep(.1)
 raise RuntimeError('Service unavailable: '+url)
def main():
 p=argparse.ArgumentParser(description=__doc__)
 for k in ['php','fpm','mariadb_root','wordpress_zip','verification_bundle','report']:p.add_argument('--'+k.replace('_','-'),required=True,type=Path)
 p.add_argument('--keep',type=Path);a=p.parse_args();checks=[];services=[];logs=[]
 def ck(n,v):
  if not v:raise AssertionError(n)
  checks.append(n);print('PASS',n,flush=True)
 temporary=None
 if a.keep:work=a.keep;work.mkdir(parents=True,exist_ok=False)
 else:temporary=tempfile.TemporaryDirectory(prefix='vv-real-wp-');work=Path(temporary.name)
 try:
  shutil.copytree(SOURCE,work/'app',ignore=shutil.ignore_patterns('.git','storage','node_modules','__pycache__','dist','test-results'));(work/'private').mkdir(mode=0o700)
  wpport,nativeport,fpmport,dbport=[port() for _ in range(4)];native=f'http://127.0.0.1:{nativeport}';legacy=f'http://127.0.0.1:{wpport}';engine=work/'private/engine';storage=work/'private/native';connector=work/'private/wp-connector.php'
  cfg={'format':1,'public_url':legacy+'/compat','cms_path':'','engine_root':str(engine),'socket':f'tcp://127.0.0.1:{fpmport}','transport':'loopback-development','secret':os.urandom(32).hex(),'policy':{'ajax':{},'rest':{}},'verification_bundle':str(a.verification_bundle.absolute())}
  connector.write_bytes(GUARD+json.dumps(cfg).encode());connector.chmod(0o600)
  env={**os.environ,'VV_STORAGE_PATH':str(storage),'VV_WP_CONNECTOR':str(connector),'VV_CANONICAL_ORIGIN':native}
  def start(cmd,name,envv=env,cwd=None):
   log=open(work/(name+'.log'),'wb');logs.append(log);proc=subprocess.Popen(list(map(str,cmd)),stdout=log,stderr=subprocess.STDOUT,env=envv,cwd=cwd);services.append(proc);return proc
  for origin,number in [('native',nativeport),('legacy',wpport)]:
   proc=start([a.php,'-S',f'127.0.0.1:{number}','-t',work/'app',work/'app/router.php'],origin);ready((native if origin=='native' else legacy)+'/assets/site.css',proc)
  owner=Session();guest=Session()
  def csrf(r):return re.search(r'name="csrf" value="([a-f0-9.]+)"',r.text).group(1)
  r=owner.get(native+'/install.php');key=(storage/'setup-key.php').read_text().split('\n',1)[1]
  r=owner.post(native+'/install.php',data={'csrf':csrf(r),'setup_key':key,'name':'Real WP fixture','email':'owner@example.test','password':PASS,'password_confirm':PASS});ck('native owner installed',r.status_code==200)
  def post(data,follow=False):return owner.post(native+'/admin.php?view=plugin-wp-runtime',data={'csrf':csrf(owner.get(native+'/admin.php?view=plugin-wp-runtime')),'action':'plugin.save','current_password':PASS,**data},allow_redirects=follow)
  r=owner.post(native+'/admin.php?view=plugins',data={'csrf':csrf(owner.get(native+'/admin.php?view=plugins')),'action':'plugin.toggle','id':'wp-runtime'});ck('optional runtime activation',r.status_code==200)
  ck('native admin unavailable at WordPress origin',guest.get(legacy+'/admin.php').status_code==404)
  dbroot=a.mariadb_root.absolute();dbenv={**env,'LD_LIBRARY_PATH':str(dbroot/'lib/x86_64-linux-gnu')+':'+os.environ.get('LD_LIBRARY_PATH',''),'PATH':str(dbroot/'bin')+':'+os.environ['PATH']};dbdir=work/'database'
  subprocess.run([str(dbroot/'bin/mariadb-install-db'),'--no-defaults','--basedir='+str(dbroot),'--datadir='+str(dbdir),'--auth-root-authentication-method=normal','--skip-test-db'],env=dbenv,stdout=open(work/'db-install.log','w'),stderr=subprocess.STDOUT,check=True)
  db=start([dbroot/'sbin/mariadbd','--no-defaults','--basedir='+str(dbroot),'--datadir='+str(dbdir),'--user='+str(os.getuid()),'--bind-address=127.0.0.1','--port='+str(dbport),'--socket=','--pid-file='+str(work/'db.pid'),'--skip-log-bin'], 'mariadb',dbenv)
  sql=[str(dbroot/'bin/mariadb'),'--no-defaults','--protocol=tcp','--host=127.0.0.1','--port='+str(dbport),'--user=root']
  for _ in range(150):
   result=subprocess.run(sql+['-e','SELECT 1'],env=dbenv,capture_output=True)
   if result.returncode==0:break
   if db.poll() is not None:raise RuntimeError('MariaDB stopped')
   time.sleep(.1)
  else:raise RuntimeError('MariaDB did not start')
  subprocess.run(sql+['-e',"CREATE DATABASE vvwp; CREATE USER 'vvwp'@'127.0.0.1' IDENTIFIED BY 'fixture_db_130!'; GRANT ALL ON vvwp.* TO 'vvwp'@'127.0.0.1';"],env=dbenv,check=True)
  dbjson=json.dumps({'db_host':f'127.0.0.1:{dbport}','db_name':'vvwp','db_user':'vvwp','db_password':'fixture_db_130!'}).encode()
  result=subprocess.run([a.php,work/'app/bin/console.php','wp:quarantine:prepare',a.wordpress_zip],input=dbjson,env=env,capture_output=True)
  if result.returncode:raise RuntimeError(result.stderr.decode())
  ck('official engine prepared outside webroot',engine.is_dir() and (engine/'wp-load.php').is_file() and not (work/'app/wp-load.php').exists())
  fpmconfig=work/'fpm.conf';fpmconfig.write_text(f'''[global]\nerror_log = {work}/fpm-error.log\ndaemonize = no\n[engine]\nlisten = 127.0.0.1:{fpmport}\nuser = root\ngroup = root\npm = ondemand\npm.max_children = 2\npm.process_idle_timeout = 10s\nclear_env = yes\ncatch_workers_output = yes\nsecurity.limit_extensions = .php\nphp_admin_value[auto_prepend_file] = {engine}/vv-Authorize.php\nphp_admin_value[open_basedir] = {engine}\nphp_admin_value[upload_tmp_dir] = {engine}/vv-data\nphp_admin_value[sys_temp_dir] = {engine}/vv-data\nphp_admin_value[session.save_path] = {engine}/vv-data\nphp_admin_flag[log_errors] = on\nphp_admin_flag[display_errors] = off\n''')
  pool=start([a.fpm,'--allow-to-run-as-root','-y',fpmconfig], 'fpm');time.sleep(.4)
  ck('public rendering unavailable before owner initialization',guest.get(legacy+'/compat/').status_code==503)
  def ticket(target='dashboard'):
   r=post({'op':'open','target':target});ck('CMS creates one-use handoff '+target,r.status_code==200 and 'bridge-login' in r.text)
   return re.search(r'name="ticket" value="([a-f0-9.]+)"',r.text).group(1)
  def handoff(target='dashboard'):
   token=ticket(target);r=owner.post(legacy+'/compat/bridge-login',data={'ticket':token},headers={'Origin':native},allow_redirects=False)
   if r.status_code>=400:print('HANDOFF',r.status_code,r.text[:1000],flush=True)
   return token,r
  token,r=handoff('health');ck('real WordPress initializes dedicated database',r.status_code==200 and json.loads(r.text)['wordpress'])
  health=json.loads(r.text);ck('WordPress runs in another PHP process',health['pid']!=os.getpid() and health['file_modifications'] is False and health['external_http'] is False)
  ck('one-use ticket replay denied',owner.post(legacy+'/compat/bridge-login',data={'ticket':token},headers={'Origin':native}).status_code==403)
  ck('missing CMS origin denied',guest.post(legacy+'/compat/bridge-login',data={'ticket':ticket()}).status_code==403)
  _,r=handoff();ck('owner dashboard redirect',r.status_code==303 and '/wp-admin/' in r.headers.get('Location',''))
  r=owner.get(legacy+'/compat/wp-admin/');ck('real WordPress dashboard',r.status_code==200 and 'wpadminbar' in r.text and 'voilavoila-logo.png' in r.text)
  ck('opaque administration assets', '/assets/admin/' in r.text or '/assets/core/' in r.text)
  home=guest.get(legacy+'/compat/');ck('real default WordPress theme renders',home.status_code==200 and 'Hello world' in home.text and 'voilavoila-logo.png' in home.text)
  css=re.findall(r'href=[\x22\x27]([^\x22\x27]+\.css[^\x22\x27]*)',home.text);local=[x for x in css if '/assets/' in x];ck('core CSS served through bounded static gate',guest.get(legacy+'/assets/core/css/dashicons.min.css').status_code==200)
  for path in ['/compat/wp-admin/','/compat/wp-login.php','/compat/xmlrpc.php','/compat/wp-cron.php','/compat/wp-config.php','/compat/vv-Gateway.php','/compat/wp-json/wp/v2/posts','/compat/wp-admin/admin-ajax.php?action=undeclared','/assets/core/../engine-config.php','/assets/core/version.php']:
   ck('public route refused '+path,guest.get(legacy+path,allow_redirects=False).status_code in {403,404})
  ck('engine templates not downloadable',guest.get(legacy+'/content/plugins/wp-runtime/engine/Bridge.template.txt').status_code==404)
  theme=work/'theme.zip'
  with zipfile.ZipFile(theme,'w',zipfile.ZIP_DEFLATED) as z:
   z.writestr('vv-classic/style.css','/* Theme Name: VV Classic Fixture */\nbody{font-family:sans-serif;max-width:70rem;margin:auto;padding:1rem}img{max-width:100%}')
   z.writestr('vv-classic/index.php','<!doctype html><html><head><meta name="viewport" content="width=device-width"><?php wp_head(); ?><link rel="stylesheet" href="<?php echo esc_url(get_stylesheet_uri()); ?>"></head><body><h1>Genuine classic template</h1><?php while(have_posts()){the_post();the_title("<h2>","</h2>");the_content();} wp_footer(); ?></body></html>')
  installed=subprocess.run([a.php,work/'app/bin/console.php','wp:theme:wrap',theme],env=env,capture_output=True);ck('classic WordPress theme wrapped',installed.returncode==0)
  switch='require '+json.dumps(str(engine/'vv-CLI.php'))+';require '+json.dumps(str(engine/'wp-load.php'))+';switch_theme("vv-classic");'
  subprocess.run([a.php,'-r',switch],env=env,capture_output=True,check=True)
  home=guest.get(legacy+'/compat/');css=re.search(r'href=[\x22\x27]([^\x22\x27]+/assets/ui/[^\x22\x27]+)',home.text)
  ck('genuine classic theme renders with opaque working CSS','Genuine classic template' in home.text and bool(css) and guest.get(css.group(1)).status_code==200)
  # Trusted representative WP module; fixture handles one public GET/POST action.
  plugin="""<?php\n/* Plugin Name: VV Runtime Probe */\nadd_action('wp_footer',function(){echo '<p id="probe-rendered">Real plugin</p><form method="post" action="'.esc_url(admin_url('admin-ajax.php')).'"><input name="action" value="vv_probe"><input name="value" value="hello"><button>Probe</button></form>';});\nfunction vv_probe(){wp_send_json(['value'=>sanitize_text_field($_REQUEST['value']??''),'cms_cookie'=>isset($_COOKIE['vvcms'])]);}add_action('wp_ajax_nopriv_vv_probe','vv_probe');add_action('wp_ajax_vv_probe','vv_probe');"""
  module=work/'probe.zip'
  with zipfile.ZipFile(module,'w',zipfile.ZIP_DEFLATED) as z:z.writestr('vv-probe/probe.php',plugin)
  r=subprocess.run([a.php,work/'app/bin/console.php','wp:plugin:wrap',module],env=env,capture_output=True);ck('trusted plugin wrapped with SHA manifest',r.returncode==0 and (engine/'vv-manifests/plugin-vv-probe.json').is_file())
  cli='require '+json.dumps(str(engine/'vv-CLI.php'))+';require '+json.dumps(str(engine/'wp-load.php'))+';update_option("active_plugins",["vv-probe/probe.php"]);echo "OK";'
  subprocess.run([a.php,'-r',cli],env=env,capture_output=True,check=True)
  ck('genuine plugin executes in real theme', 'probe-rendered' in guest.get(legacy+'/compat/').text)
  rule={'plugin':'vv-probe','methods':['GET','POST'],'fields':{'value':'text'}};cfg['policy']['ajax']['vv_probe']=rule;connector.write_bytes(GUARD+json.dumps(cfg).encode());manifest=engine/'vv-manifests/plugin-vv-probe.json';m=json.loads(manifest.read_text());m['policy']['ajax']={'vv_probe':rule};manifest.write_text(json.dumps(m))
  r=guest.get(legacy+'/compat/wp-admin/admin-ajax.php?action=vv_probe&value=hello');ck('allowlisted public GET executes',r.status_code==200 and json.loads(r.text)['value']=='hello' and json.loads(r.text)['cms_cookie'] is False)
  r=guest.get(legacy+'/compat/');token=re.search(r'name="vv_guest" value="([a-f0-9.]+)"',r.text).group(1)
  ck('public form contains guest token without JavaScript',bool(token))
  ck('public POST missing CSRF rejected',guest.post(legacy+'/compat/wp-admin/admin-ajax.php',data={'action':'vv_probe','value':'hello'}).status_code==403)
  r=guest.post(legacy+'/compat/wp-admin/admin-ajax.php',data={'action':'vv_probe','value':'hello','vv_guest':token});ck('allowlisted public POST executes with CSRF',r.status_code==200 and json.loads(r.text)['value']=='hello')
  ck('query smuggling rejected',guest.post(legacy+'/compat/wp-admin/admin-ajax.php?injected=1',data={'action':'vv_probe','value':'hello','vv_guest':token}).status_code==403)
  ck('query body action conflict rejected',guest.post(legacy+'/compat/wp-admin/admin-ajax.php?action=evil',data={'action':'vv_probe','value':'hello','vv_guest':token}).status_code==403)
  for maintenance in [True,False]:
   subprocess.run([a.php,'-r','require '+json.dumps(str(work/'app/core/bootstrap.php'))+';$vv->store->change(function(&$s){$s["settings"]["maintenance"]='+('true' if maintenance else 'false')+';});'],env=env,check=True)
   if maintenance:
    ck('native maintenance also gates WordPress visitors',guest.get(legacy+'/compat/').status_code==503)
    ck('owner can retain WordPress access in maintenance',owner.get(legacy+'/compat/wp-admin/').status_code==200)
  version=subprocess.check_output([a.php,engine/'vv-Console.php','version'],env=env,text=True).strip();ck('private offline version helper works',version==health['wordpress'])
  if os.getenv('VV_WP_BROWSER_REPORT'):
   subprocess.run(['node',str(SOURCE/'tests/browser_wp_runtime.cjs')],env={**env,'VV_WP_NATIVE':native,'VV_WP_LEGACY':legacy},check=True,timeout=180)
  # Invalidate native epoch and prove owner bridge revocation.
  subprocess.run([a.php,'-r','require '+json.dumps(str(work/'app/core/bootstrap.php'))+';$vv->store->change(function(&$s){$s["owner"]["epoch"]=bin2hex(random_bytes(16));});'],env=env,check=True)
  ck('CMS owner epoch revokes bridge sessions',owner.get(legacy+'/compat/wp-admin/').status_code==403)
  report={'suite':'real WordPress runtime','checks':len(checks),'passed':checks,'wordpress':health['wordpress'],'php':health['php'],'wordpress_zip_sha256':__import__('hashlib').sha256(a.wordpress_zip.read_bytes()).hexdigest(),'os_uid_isolation_tested':False,'transport':'explicit loopback development; same UID','limitations':['No hosted-provider, TLS or container/UID isolation certification.']}
  a.report.parent.mkdir(parents=True,exist_ok=True);a.report.write_text(json.dumps(report,indent=2)+'\n');print('PASS',len(checks),'real WordPress checks')
 except Exception:
  print('WORK',work,flush=True)
  for log in work.glob('*.log'):
   print('LOG',log.name,log.read_text(errors='replace')[-3500:],flush=True)
  raise
 finally:
  for process in reversed(services):
   process.terminate()
   try:process.wait(timeout=5)
   except subprocess.TimeoutExpired:process.kill();process.wait()
  for log in logs:log.close()
  if temporary:temporary.cleanup()
if __name__=='__main__':main()
