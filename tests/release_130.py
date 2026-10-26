#!/usr/bin/env python3
"""Isolated 1.3 Forms, branding, native API and optional-engine regressions."""
import argparse, hashlib, hmac, html, io, json, os, re, shutil, sqlite3, subprocess, tempfile, time, zipfile
from pathlib import Path
from run import SOURCE, server, read_state
from http_client import Session
PASS='Forms_test_owner_130!'
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--php',default=os.getenv('PHP_BIN','php'));p.add_argument('--report',type=Path);a=p.parse_args();checks=[]
 def ck(name,condition):
  if not condition:raise AssertionError(name)
  checks.append(name);print('PASS',name,flush=True)
 with tempfile.TemporaryDirectory(prefix='vv-forms-130-') as tmp:
  work=Path(tmp);shutil.copytree(SOURCE,work/'app',ignore=shutil.ignore_patterns('.git','storage','node_modules','__pycache__','dist','test-results'))
  with server(a.php,work,work/'state') as env:
   base=env['VV_TEST_BASE'];owner=Session();guest=Session()
   def csrf(r):return re.search(r'name="csrf" value="([a-f0-9]+)"',r.text).group(1)
   def post(action,data={},view='plugin-forms',files=None,follow=False):return owner.post(base+'/admin.php?view='+view,data={'csrf':csrf(owner.get(base+'/admin.php?view='+view)),'action':action,**data},files=files,allow_redirects=follow)
   r=owner.get(base+'/install.php');key=(work/'state/setup-key.php').read_text().split('\n',1)[1]
   r=owner.post(base+'/install.php',data={'csrf':csrf(r),'setup_key':key,'name':'Forms fixture','email':'owner@example.test','password':PASS,'password_confirm':PASS})
   ck('install and original PNG branding',r.status_code==200 and 'assets/voilavoila-logo.png' in r.text and 'Conception et ingénierie françaises' in r.text)
   ck('WordPress bridge inactive by default',guest.get(base+'/compat/').status_code==404 and guest.get(base+'/wp.php').status_code==404)
   ck('engine template inaccessible over HTTP',guest.get(base+'/content/plugins/wp-runtime/engine/Gateway.template.txt').status_code==404)
   for plugin in ['forms','wp-runtime']:
    folder=work/'app/content/plugins'/plugin;buf=io.BytesIO();expected={}
    with zipfile.ZipFile(buf,'w',zipfile.ZIP_DEFLATED) as z:
     for source in folder.rglob('*'):
      if source.is_file():relative=source.relative_to(folder).as_posix();expected[relative]=source.read_bytes();z.writestr(plugin+'/'+relative,expected[relative])
    shutil.move(folder,work/('before-'+plugin))
    result=post('package.install',{'type':'plugin','current_password':PASS},'plugins',files={'package':(plugin+'.zip',buf.getvalue(),'application/zip')})
    ck('native ZIP installer accepts first-party '+plugin,result.status_code==303)
    ck('installed addon bytes preserved '+plugin,all((folder/name).read_bytes()==data for name,data in expected.items()))
   ck('Forms activation',post('plugin.toggle',{'id':'forms'},'plugins').status_code==303)
   ck('empty private Forms panel',owner.get(base+'/admin.php?view=plugin-forms').status_code==200)
   field={'op':'definition','id':'contact','version':'0','name':'Contact','published':'on','retention_days':'90','privacy':'Private reply only.','confirmation':'Saved request.', 'field_0_key':'name','field_0_type':'text','field_0_label':'Name','field_0_required':'on','field_0_order':'0','field_1_key':'email','field_1_type':'email','field_1_label':'Email','field_1_required':'on','field_1_order':'1','field_2_key':'message','field_2_type':'textarea','field_2_label':'Message','field_2_required':'on','field_2_order':'2'}
   r=post('plugin.save',field);ck('form definition saved',r.status_code==303)
   r=post('plugin.save',field);ck('stale form edit rejected',r.status_code==409)
   content={'type':'page','id':'','version':'0','status':'published','slug':'contact','title_fr':'Contact','title_en':'Contact','body_fr':'<p>[form-contact]</p>','body_en':'<p>[form-contact]</p>','publish_at':'2000-01-01T12:00'}
   ck('form tag saved in native SQLite page',post('content.save',content,'editor').status_code==303)
   page=guest.get(base+'/?p=contact');ck('public accessible no-JavaScript form',page.status_code==200 and 'forms_token' in page.text and 'aria-labelledby' in page.text and 'Private reply only.' in page.text)
   ck('native footer original brand and French flag','assets/france.svg' in page.text and 'Conception et ingénierie françaises' in page.text)
   ck('English engineering signature', 'French design and engineering' in guest.get(base+'/?p=contact&lang=en').text)
   ck('forged success URL not confirmed','Saved request.' not in guest.get(base+'/?p=contact&form_received=contact').text)
   ck('forms absent from private owner preview','forms_token' not in owner.get(base+'/?preview='+next(c['id'] for c in read_state(work/'state')['contents'].values() if c['slug']=='contact')).text)
   def payload(r):
    fields={m[0]:html.unescape(m[1]) for m in re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',r.text)}
    return {**fields,'fields[name]':'Alice','fields[email]':'alice@example.test','fields[message]':'<script>secret text</script>','website':''}
   data=payload(page)
   ck('missing guest CSRF rejected',guest.post(base+'/?p=contact',data={**data,'forms_token':'bad'}).status_code==403)
   ck('foreign origin rejected',guest.post(base+'/?p=contact',data=data,headers={'Origin':'https://evil.example'}).status_code==403)
   ck('bad email validation retains field',guest.post(base+'/?p=contact',data={**data,'fields[email]':'invalid'}).status_code==422)
   ck('unknown field rejected',guest.post(base+'/?p=contact',data={**data,'fields[owner]':'yes'}).status_code==422)
   ck('honeypot rejected',guest.post(base+'/?p=contact',data={**data,'website':'spam'}).status_code==422)
   ck('tampered signature rejected',guest.post(base+'/?p=contact',data={**data,'submission':data['submission'][:-1]+('0' if data['submission'][-1]!='0' else '1')}).status_code==409)
   # Scientific notation-looking randomness must not affect the timestamp parser.
   s=read_state(work/'state');f=s['plugin_data']['forms']['forms']['contact'];cid=data['content_id'];stamp=str(int(time.time()));raw=stamp+'.123e4567890123456789012345678901';proof=hmac.new(s['secret'].encode(),('contact|1|'+cid+'|'+data['forms_token']+'|'+raw).encode(),hashlib.sha256).hexdigest();data['submission']=raw+'.'+proof
   r=guest.post(base+'/?p=contact',data=data,allow_redirects=False);ck('submission with exponent-looking nonce persisted',r.status_code==303)
   ck('receipt confirms after PRG','Saved request.' in guest.get(base+r.headers['Location']).text)
   r=guest.post(base+'/?p=contact',data=data,allow_redirects=False);ck('duplicate browser retry acknowledged',r.status_code==303)
   d=read_state(work/'state')['plugin_data']['forms'];ck('duplicate does not create another request',len(d['rows'])==1);rid=next(iter(d['rows']));row=d['rows'][rid]
   ck('visitor data stored as text without IP or owner credentials',row['values']['message']=='<script>secret text</script>' and 'ip' not in row and row['notification']['status']=='disabled')
   panel=owner.get(base+'/admin.php?view=plugin-forms&request='+rid);ck('private inbox HTML escaped','&lt;script&gt;secret text&lt;/script&gt;' in panel.text and '<script>secret text</script>' not in panel.text)
   ck('anonymous inbox denied',guest.get(base+'/admin.php?view=plugin-forms',allow_redirects=False).status_code==303)
   ck('JSON export reauthentication required',post('plugin.save',{'op':'export-json','current_password':'bad'}).status_code==403)
   r=post('plugin.save',{'op':'export-json','current_password':PASS});ck('private JSON export faithful',r.status_code==200 and json.loads(r.text)['rows'][rid]['values']['message']==row['values']['message'])
   r=post('backup.export',{'current_password':PASS},'tools');ck('native backup export still works with password',r.status_code==200 and r.content[:2]==b'PK')
   ck('notification settings reject header injection',post('plugin.save',{'op':'settings','notify':'on','to':'owner@example.test\r\nBcc:bad@example.test','from':'cms@example.test','current_password':PASS}).status_code==400)
   ck('status optimistic lock',post('plugin.save',{'op':'status','id':rid,'version':'0','status':'handled'}).status_code==409)
   ck('request marked handled',post('plugin.save',{'op':'status','id':rid,'version':'1','status':'handled'}).status_code==303)
   ck('deletion requires current owner password',post('plugin.save',{'op':'delete-row','id':rid,'version':'2','current_password':'bad'}).status_code==403)
   ck('request deleted',post('plugin.save',{'op':'delete-row','id':rid,'version':'2','current_password':PASS}).status_code==303)
   guest.post(base+'/?p=contact',data=data,allow_redirects=False);ck('deletion retains anti-replay receipt',not read_state(work/'state')['plugin_data']['forms']['rows'])
   ck('plugin deactivation keeps private definitions',post('plugin.toggle',{'id':'forms'},'plugins').status_code==303 and 'contact' in read_state(work/'state')['plugin_data']['forms']['forms'])
   ck('disabled Forms no public input','forms_token' not in guest.get(base+'/?p=contact').text)
   unit=work/'unit.php';unit.write_text("<?php require "+json.dumps(str(work/'app/core/bootstrap.php'))+";require VV_ROOT.'/content/plugins/forms/Forms.php';require VV_ROOT.'/content/plugins/wp-runtime/Runtime.php';$checks=[];function ck($n,$ok){global $checks;if(!$ok)throw new Exception($n);$checks[]=$n;}function deny($f){try{$f();return false;}catch(\\VV\\Failure){return true;}}"+r'''
ck('CSV formula guard',\VV\Forms::csvCell(" =1+1")==="' =1+1");
ck('CSV plain text',\VV\Forms::csvCell('Alice')==='Alice');
ck('future Forms schema refused',deny(fn()=>\VV\Forms::validate(['schema'=>9])));
foreach(['/a/../b','/a%2fb','/a%252fb','/a//b','/a\\b',"/a\0b"] as $path)ck('unsafe path '.bin2hex($path),deny(fn()=>\VV\Security::quarantinePath($path)));
ck('valid path accepted',\VV\Security::quarantinePath('/wp-json/vv-probe/v1')==='/wp-json/vv-probe/v1');
ck('unknown bridge method rejected',deny(fn()=>\VV\WordPress\Runtime::policy(['ajax'=>['test'=>['plugin'=>'test-plugin','methods'=>['DELETE'],'fields'=>[]]]])));
ck('reserved bridge field rejected',deny(fn()=>\VV\WordPress\Runtime::policy(['ajax'=>['test'=>['plugin'=>'test-plugin','methods'=>['POST'],'fields'=>['action'=>'text']]]])));
ck('undeclared query parameter rejected',deny(fn()=>\VV\WordPress\Runtime::publicRule(['policy'=>['ajax'=>['test'=>['plugin'=>'test-plugin','methods'=>['POST'],'fields'=>[]]]]],'/wp-admin/admin-ajax.php','POST',['injected'=>'1'],['action'=>'test'])));
ck('query body conflicts rejected',deny(fn()=>\VV\WordPress\Runtime::publicRule([], '/wp-admin/admin-ajax.php','POST',['action'=>'evil'],['action'=>'test'])));
ck('API 1 zero argument shortcode', (function()use($vv){$vv->shortcodes['zero']='getmypid';return $vv->body(['body_fr'=>'[zero]','body_en'=>'[zero]'])===(string)getmypid()&&$vv->currentContent()===null;})());

$d=\VV\Forms::data($vv->state);$form=$d['forms']['contact'];$id=bin2hex(random_bytes(12));
$d['settings']=['notify'=>true,'to'=>'owner@example.test','from'=>'cms@example.test'];
$d['rows'][$id]=['id'=>$id,'form'=>$form,'content_id'=>array_key_first($vv->state['contents']),'values'=>['name'=>'Alice','email'=>'alice@example.test','message'=>'Test'], 'status'=>'new','created'=>time(),'expires'=>time()+86400,'version'=>1,'notification'=>['status'=>'pending','to'=>'owner@example.test','from'=>'cms@example.test','attempts'=>0,'reserved'=>0,'lease'=>'']];
$vv->store->change(function(&$s)use($d){\VV\Forms::put($s,$d);});$calls=0;
$result=\VV\Forms::drain($vv,10,function($job)use($vv,&$calls){$calls++;$vv->store->read();return 'accepted';});
ck('outbox transport runs outside SQLite transaction',$result['accepted']===1&&$calls===1);
\VV\Forms::drain($vv,10,function()use(&$calls){$calls++;return 'accepted';});ck('accepted job not resent',$calls===1);
$vv->store->change(function(&$s)use($id){$s['plugin_data']['forms']['rows'][$id]['notification']['status']='pending';});
$result=\VV\Forms::drain($vv,10,function(){throw new Exception('private transport failure');});ck('transport exception becomes unknown',$result['unknown']===1);
\VV\Forms::drain($vv,10,function()use(&$calls){$calls++;return 'accepted';});ck('unknown result not retried automatically',$calls===1);
$vv->store->change(function(&$s)use($id){$n=&$s['plugin_data']['forms']['rows'][$id]['notification'];$n['status']='processing';$n['reserved']=time()-400;$n['lease']=bin2hex(random_bytes(16));});
\VV\Forms::drain($vv);ck('stale processing lease becomes unknown',$vv->store->read()['plugin_data']['forms']['rows'][$id]['notification']['status']==='unknown');
$vv->store->change(function(&$s)use($id){$s['plugin_data']['forms']['settings']['notify']=false;$s['plugin_data']['forms']['rows'][$id]['notification']['status']='pending';});
\VV\Forms::drain($vv,10,function()use(&$calls){$calls++;return 'accepted';});ck('disabled notifications pause pending jobs',$calls===1);
require VV_ROOT.'/content/plugins/forms/Admin.php';\VV\Forms::register($vv);$vv->refresh();
$restored=vv_filter('backup_restore_data_v1',['plugin_data'=>['forms'=>\VV\Forms::data($vv->state)]]);
ck('backup restore disables notifications',$restored['plugin_data']['forms']['settings']===['notify'=>false,'to'=>'','from'=>'']);
ck('backup restore never replays pending mail',$restored['plugin_data']['forms']['rows'][$id]['notification']['status']==='unknown');
$vv->store->change(function(&$s)use($id){$s['plugin_data']['forms']['rows'][$id]['expires']=time()-1;});
ck('expired inbox row purged',\VV\Forms::purgeExpired($vv)===1);

echo json_encode($checks);
''');unitchecks=json.loads(subprocess.check_output([a.php,str(unit)],env={**os.environ,'VV_STORAGE_PATH':str(work/'state')},text=True));checks.extend(unitchecks)
   if os.getenv('VV_FORMS_BROWSER_REPORT'):
    ck('Forms reactivation for browser fixture',post('plugin.toggle',{'id':'forms'},'plugins').status_code==303)
    subprocess.run(['node',str(SOURCE/'tests/browser_130.cjs')],env={**os.environ,'VV_TEST_BASE':base},check=True,timeout=180)
 result={'suite':'1.3 Forms and bridge boundary','checks':len(checks),'passed':checks,'php':subprocess.check_output([a.php,'-r','echo PHP_VERSION;'],text=True)}
 if a.report:a.report.parent.mkdir(parents=True,exist_ok=True);a.report.write_text(json.dumps(result,indent=2)+'\n')
 print('PASS',len(checks),'1.3 checks')
if __name__=='__main__':main()
