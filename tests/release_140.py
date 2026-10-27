#!/usr/bin/env python3
"""1.4 SEO, WXR review, redirects, retention and owner-only operations."""
import argparse,datetime,fcntl,hashlib,html,io,json,os,re,shutil,subprocess,sys,time,urllib.parse,zipfile
from pathlib import Path
from html.parser import HTMLParser
from run import SOURCE,server,read_state,GUARD
from http_client import Session
PASSWORD='CMS_140_Synthetic_Owner!'

class Head(HTMLParser):
 def __init__(self,text):super().__init__();self.head=False;self.tags=[];self.feed(text)
 def handle_starttag(self,tag,attrs):
  if tag=='head':self.head=True
  if self.head:self.tags.append((tag,dict(attrs)))
 def handle_endtag(self,tag):
  if tag=='head':self.head=False
 def metas(self,name):return [a for t,a in self.tags if t=='meta' and (a.get('name')==name or a.get('property')==name)]
 def canonicals(self):return [a['href'] for t,a in self.tags if t=='link' and a.get('rel')=='canonical']

def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--php',required=True);p.add_argument('--work',type=Path,required=True);p.add_argument('--mount',default='');p.add_argument('--source-zip',type=Path);p.add_argument('--native-theme',type=Path);p.add_argument('--native-pass',type=Path);p.add_argument('--browser',action='store_true');p.add_argument('--report',type=Path);a=p.parse_args();a.work=a.work.absolute();a.work.mkdir(parents=True,exist_ok=True);checks=[]
 def ck(name,value):
  checks.append({'name':name,'ok':bool(value)});print(('PASS ' if value else 'FAIL ')+name,flush=True)
  if not value:raise AssertionError(name)
 if a.source_zip:zipfile.ZipFile(a.source_zip).extractall(a.work/'app')
 else:shutil.copytree(SOURCE,a.work/'app',ignore=shutil.ignore_patterns('.git','storage','node_modules','__pycache__','dist','test-results'))
 subprocess.run([a.php,'-r',"$i=imagecreatetruecolor(300,180);$c=imagecolorallocate($i,201,169,99);imagefill($i,0,0,$c);imagepng($i,'fixture.png');"],cwd=a.work,check=True)
 with server(a.php,a.work,a.work/'state',a.mount) as env:
  base=env['VV_TEST_BASE'];root=a.work/('web/'+a.mount if a.mount else 'app');owner=Session();guest=Session();state=lambda:read_state(a.work/'state')
  def csrf(r):return re.search(r'name="csrf" value="([a-f0-9]+)"',r.text).group(1)
  def post(action,data=None,view='tools',files=None):return owner.post(base+'/admin.php?view='+view,data={'csrf':csrf(owner.get(base+'/admin.php?view='+view)),'action':action,**(data or {})},files=files,allow_redirects=False)
  install=owner.get(base+'/install.php');key=(a.work/'state/setup-key.php').read_text().split('\n',1)[1];r=owner.post(base+'/install.php',data={'csrf':csrf(install),'setup_key':key,'name':'VoilaVoila test 1.4','email':'owner@example.test','password':PASSWORD,'password_confirm':PASSWORD})
  ck('fresh 1.4 install and gradient brand',r.status_code==200 and 'VoilaVoila !' in r.text and 'sidebar-collapse' in r.text)
  alias=root/'STORAGE';alias.symlink_to(a.work/'state',target_is_directory=True)
  private=guest.get(base+'/STORAGE/cms.sqlite');ck('private-path guard is case insensitive even through a filesystem alias',private.status_code in [403,404]);alias.unlink()
  ck('health requires owner session',guest.get(base+'/admin.php?view=health',allow_redirects=False).status_code==303)
  ck('owner health UI renders',owner.get(base+'/admin.php?view=health').status_code==200)
  settings=state()['settings'];settings={k:str(v) for k,v in settings.items() if not isinstance(v,(dict,bool))};settings['canonical']='https://cms.example.test'+('/'+a.mount if a.mount else '')
  ck('canonical URL configured',post('settings.save',settings,'settings').status_code==303)
  seed=next(c for c in state()['contents'].values() if c['type']=='post');cid=seed['id']
  payload={'id':cid,'version':str(seed['version']),'type':'post','status':'published','slug':seed['slug'],'title_fr':'Un été à Paris','title_en':'A summer in Paris','body_fr':'<p>Des projets à partager.</p>','body_en':'<p>Projects to share.</p>','publish_at':'2000-01-01T12:00','seo_config':'1','seo_title_fr':'Paris · notre journal','seo_title_en':'Paris · our journal','seo_description_fr':'Les nouvelles du collectif.','seo_description_en':'News from the collective.'}
  ck('per-content bilingual SEO fields save with content',post('content.save',payload,'editor').status_code==303)
  for theme in ['profil-or','profil-graphite']:
   post('theme.activate',{'id':theme},'themes');r=guest.get(base+'/index.php?p=bienvenue&lang=en');h=Head(r.text)
   ck(theme+' emits one title, description, canonical and OG title',sum(t=='title' for t,_ in h.tags)==1 and len(h.metas('description'))==1 and len(h.canonicals())==1 and len(h.metas('og:title'))==1)
   ck(theme+' uses English content SEO override',h.metas('description')[0]['content']=='News from the collective.' and 'Paris · our journal' in r.text)
   ck(theme+' emits valid structured data and reciprocal alternates','"@type":"BlogPosting"' in r.text and sum(t=='link' and 'hreflang' in attrs for t,attrs in h.tags)==2)
  if a.native_theme:
   r=post('package.install',{'type':'theme','current_password':PASSWORD},'themes',{'package':(a.native_theme.name,a.native_theme.read_bytes(),'application/zip')});ck('latest VoilaVoila native journal theme installs',r.status_code==303)
   post('theme.activate',{'id':'voilavoila'},'themes');r=guest.get(base+'/index.php?p=bienvenue&lang=en');h=Head(r.text)
   ck('native journal theme retains full body and receives one metadata set',r.status_code==200 and 'Projects to share.' in r.text and len(h.metas('og:title'))==1 and len(h.canonicals())==1)
  r=guest.get(base+'/index.php?feed=1&page=2&lang=en');h=Head(r.text);ck('paginated canonical keeps its own page and language',h.canonicals() and 'page=2' in h.canonicals()[0] and 'lang=en' in h.canonicals()[0])
  for suffix in ['?q=Paris','?p=missing-page','?preview='+cid]:
   r=owner.get(base+'/index.php'+suffix) if 'preview=' in suffix else guest.get(base+'/index.php'+suffix);h=Head(r.text)
   ck('private preview, search or 404 has no canonical '+suffix.split('=')[0],not h.canonicals() and any('noindex' in x['content'] for x in h.metas('robots')))
  before=state()['contents'][cid];payload.update(version=str(before['version']),seo_exclude='on');post('content.save',payload,'editor');r=guest.get(base+'/index.php?cms=sitemap');ck('sitemap valid and excludes per-content noindex',r.status_code==200 and 'p=bienvenue' not in r.text and 'hreflang="en"' in r.text)
  import xml.etree.ElementTree as ET;ET.fromstring(r.content);ck('sitemap XML parses',True)
  payload.pop('seo_exclude');payload['version']=str(state()['contents'][cid]['version']);post('content.save',payload,'editor')
  post('plugin.toggle',{'id':'seo'},'plugins');r=guest.get(base+'/index.php?p=bienvenue&lang=en');ck('theme remains useful with SEO plugin disabled','Projects to share.' in r.text and 'Un été à Paris' not in re.search(r'<title>(.*?)</title>',r.text,re.S).group(1));post('plugin.toggle',{'id':'seo'},'plugins')
  xml='''<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:wp="http://wordpress.org/export/1.2/" xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel><title>Old site</title><link>https://old.example.test</link><wp:wxr_version>1.2</wp:wxr_version><wp:base_blog_url>https://old.example.test</wp:base_blog_url><item><title>L’été — migration</title><link>https://old.example.test/ancien-article/</link><guid>https://old.example.test/?p=77</guid><wp:post_id>77</wp:post_id><wp:post_type>post</wp:post_type><wp:status>publish</wp:status><wp:post_name>bienvenue</wp:post_name><wp:post_date_gmt>2000-01-01 12:00:00</wp:post_date_gmt><content:encoded><![CDATA[<p>Édition importée, sans écraser le contenu local.</p>]]></content:encoded></item></channel></rss>'''.encode()
  files={'wxr':('wordpress.xml',xml,'application/xml')};initial=state();points=list((a.work/'state/backups').glob('*.php'))
  r=post('wordpress.preview',{'current_password':PASSWORD,'publish':'on'},files=files);ck('WXR preview requires review instead of immediate import',r.status_code==303 and 'import_preview=' in r.headers['Location']);token=urllib.parse.parse_qs(urllib.parse.urlparse(r.headers['Location']).query)['import_preview'][0]
  planpage=owner.get(r.headers['Location'] if r.headers['Location'].startswith('http') else base.split('/'+a.mount)[0]+r.headers['Location'] if a.mount else base+r.headers['Location'])
  ck('preview displays collision and resolved native link','bienvenue-2' in planpage.text and 'wordpress.confirm' in planpage.text)
  ck('preview leaves content, media and restore points untouched',state()['contents']==initial['contents'] and state()['media']==initial['media'] and list((a.work/'state/backups').glob('*.php'))==points)
  protected=guest.get(base+'/storage/import-previews/'+token+'.wxr.php');ck('staged XML is inaccessible publicly',protected.status_code in [403,404] and b'Old site' not in protected.content)
  staged=a.work/'state/import-previews'/f'{token}.wxr.php';original=staged.read_bytes();staged.write_bytes(original+b' ');r=post('wordpress.confirm',{'token':token,'current_password':PASSWORD,'confirm':'IMPORTER'});ck('staged payload tampering is rejected',r.status_code==409 and len(state()['contents'])==len(initial['contents']));staged.write_bytes(original)
  changed=state()['contents'][cid];payload['version']=str(changed['version']);post('content.save',payload,'editor');r=post('wordpress.confirm',{'token':token,'current_password':PASSWORD,'confirm':'IMPORTER'});ck('stale preview after local edit is rejected',r.status_code==409)
  r=post('wordpress.preview',{'current_password':PASSWORD,'publish':'on'},files=files);token=urllib.parse.parse_qs(urllib.parse.urlparse(r.headers['Location']).query)['import_preview'][0]
  ck('reviewed WXR import commits',post('wordpress.confirm',{'token':token,'current_password':PASSWORD,'confirm':'IMPORTER'}).status_code==303)
  imported=next(c for c in state()['contents'].values() if c.get('wp_id')=='77');iid=imported['id'];ck('collision resolved without overwriting local article',imported['slug']=='bienvenue-2' and state()['contents'][cid]['title_en']=='A summer in Paris')
  ck('successful import token cannot be replayed',post('wordpress.confirm',{'token':token,'current_password':PASSWORD,'confirm':'IMPORTER'}).status_code==410)
  ck('legacy path remains 404 before owner approves redirects',guest.get(base+'/ancien-article/',allow_redirects=False).status_code==404)
  ck('redirect approval is CSRF protected',owner.post(base+'/admin.php?view=redirects',data={'action':'redirects.approve','csrf':'bad','current_password':PASSWORD}).status_code==403)
  ck('full redirect proposal report is downloadable',post('redirects.report',{},'redirects').status_code==200)
  revision=re.search(r'name="revision" value="([a-f0-9]+)"',owner.get(base+'/admin.php?view=redirects').text).group(1)
  ck('owner approves redirects separately',post('redirects.approve',{'current_password':PASSWORD,'revision':revision},'redirects').status_code==303)
  for old in ['/ancien-article/','/?p=77','/index.php?p=77']:
   r=guest.get(base+old+('&lang=en' if '?' in old else '?lang=en'),allow_redirects=False);ck('approved old URL redirects directly in subdirectory '+old,r.status_code==301 and 'p=bienvenue-2' in r.headers.get('Location','') and 'lang=en' in r.headers.get('Location',''))
  ck('unsupported redirect query cannot control a target',guest.get(base+'/?p=77&next=https://evil.example',allow_redirects=False).status_code==404)
  imported=state()['contents'][iid];edit={'id':iid,'version':str(imported['version']),'type':'post','status':'private','slug':imported['slug'],'title_fr':'Article privé','body_fr':imported['body_fr'],'publish_at':'2000-01-01T12:00'};post('content.save',edit,'editor');ck('redirect immediately stops when target becomes private',guest.get(base+'/ancien-article/',allow_redirects=False).status_code==404);edit.update(status='published',version=str(state()['contents'][iid]['version']));post('content.save',edit,'editor')
  backup=post('backup.export',{'current_password':PASSWORD});ck('editorial backup includes SEO fields and approved redirects',backup.status_code==200 and zipfile.ZipFile(io.BytesIO(backup.content)).read('data.json').find(b'"redirects"')>=0)
  r=post('backup.restore',{'current_password':PASSWORD,'confirm':'RESTAURER'},files={'backup':('cms.zip',backup.content,'application/zip')});ck('backup restores approved redirects and per-content SEO',r.status_code==303 and state()['contents'][cid]['seo']['title_en']=='Paris · our journal' and state()['redirects'])
  subprocess.run([a.php,'-r',"require 'core/bootstrap.php';$vv->store->change(function(&$s){$s['limits']=[];});"],cwd=root,env={**os.environ,**env},check=True)
  policy={'logs_days':'7','backups_days':'7','backups_keep':'5','backup_hours':'24','auto_backup':'on','current_password':PASSWORD}
  ck('retention policy bounds enforced',post('operations.save',{**policy,'logs_days':'0'},'health').status_code==400)
  ck('owner saves retention and due backups policy',post('operations.save',policy,'health').status_code==303)
  report=post('health.export',{},'health');data=json.loads(report.content);ck('health export has no secret, private path, account or form data',report.status_code==200 and str(a.work/'state') not in report.text and 'owner@example.test' not in report.text and 'secret' not in data and data['telemetry'] is False)
  for f in (a.work/'state/backups').glob('*.php'):f.unlink()
  old=time.time()-40*86400
  for i in range(5):
   f=a.work/'state/backups'/('2026010'+str(i+1)+'-120000-'+str(i).zfill(8)+'.php');f.write_bytes(GUARD+backup.content);os.utime(f,(old,old))
  with (a.work/'state/logs/access.txt').open('ab') as f:f.write(b'2000-01-01T00:00:00+00:00 old-log-line\n')
  orphan=a.work/'state/import-previews'/('a'*32+'.wxr.php');orphan.write_bytes(GUARD+b'orphan');os.utime(orphan,(old,old))
  run=subprocess.run([a.php,str(root/'bin/console.php'),'maintenance:run','--budget=10','--limit=1'],env={**os.environ,**env},text=True,capture_output=True);result=json.loads(run.stdout) if run.returncode==0 else {}
  ck('CLI maintenance runs under a lock and reports its bounded work',run.returncode==0 and result['passed'] and result['budget_seconds']==10)
  ck('retention removes old logs and orphaned previews',b'old-log-line' not in (a.work/'state/logs/access.txt').read_bytes() and not orphan.exists())
  ck('backup retention preserves two prior points and creates one due backup',len(list((a.work/'state/backups').glob('*.php')))==3 and result['counts']['backup_created']==1)
  lock=(a.work/'state/maintenance.lock').open('r+b');fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
  blocked=subprocess.run([a.php,str(root/'bin/console.php'),'maintenance:run'],env={**os.environ,**env},capture_output=True,text=True);fcntl.flock(lock,fcntl.LOCK_UN);lock.close();ck('second concurrent maintenance worker is refused',blocked.returncode!=0 and 'déjà' in blocked.stderr)
  post('plugin.toggle',{'id':'forms'},'plugins')
  definition={'op':'definition','id':'maintenance','version':'0','name':'Maintenance test','published':'on','retention_days':'1','privacy':'Synthetic test only.','confirmation':'Saved.','field_0_key':'message','field_0_type':'text','field_0_label':'Message','field_0_required':'on'}
  ck('Forms definition for maintenance integration saves',post('plugin.save',definition,'plugin-forms').status_code==303)
  ck('Forms notification settings save',post('plugin.save',{'op':'settings','notify':'on','to':'owner@example.test','from':'forms@example.test','current_password':PASSWORD},'plugin-forms').status_code==303)
  page={'type':'page','version':'0','status':'published','slug':'maintenance-contact','title_fr':'Contact maintenance','body_fr':'<p>[form-maintenance]</p>','publish_at':'2000-01-01T12:00'}
  post('content.save',page,'editor');reply=guest.get(base+'/index.php?p=maintenance-contact');fields={name:html.unescape(value) for name,value in re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',reply.text)}
  ck('actual guest submission queues a notification for maintenance',guest.post(base+'/index.php?p=maintenance-contact',data={**fields,'fields[message]':'Synthetic maintenance request','website':''},allow_redirects=False).status_code==303)
  sdk=root/'sdk/operations-example.zip';ck('API 1 maintenance SDK example installs',post('package.install',{'type':'plugin','current_password':PASSWORD},'plugins',{'package':(sdk.name,sdk.read_bytes(),'application/zip')}).status_code==303);post('plugin.toggle',{'id':'operations-example'},'plugins')
  protocol={'base':base,'root':str(root),'protocol_report':str(a.work/'protocol-report.json')};(a.work/'protocol-fixture.json').write_text(json.dumps(protocol))
  subprocess.run([a.php,str(SOURCE/'tests/protocol_140.php'),str(a.work/'protocol-fixture.json')],env={**os.environ,**env},check=True)
  if a.native_pass:
   r=post('package.install',{'type':'plugin','current_password':PASSWORD},'plugins',{'package':(a.native_pass.name,a.native_pass.read_bytes(),'application/zip')});ck('existing native PASS package installs',r.status_code==303);post('plugin.toggle',{'id':'pass'},'plugins');ck('existing PASS shop still responds',guest.get(base+'/index.php?passw=boutique').status_code==200)
  fixture={'base':base,'password':PASSWORD,'work':str(a.work),'report':str(a.work/'browser-report.json')};(a.work/'fixture.json').write_text(json.dumps(fixture))
  if a.browser:subprocess.run(['node',str(SOURCE/'tests/browser_140.cjs')],env={**os.environ,'VV_140_FIXTURE':str(a.work/'fixture.json')},check=True)
  envfile=a.work/'private-test-env.json';envfile.write_text(json.dumps(env))
 result={'passed':True,'checks':checks,'protocol':json.loads((a.work/'protocol-report.json').read_text()),'browser':json.loads((a.work/'browser-report.json').read_text()) if a.browser else None,'mount':a.mount,'php':subprocess.check_output([a.php,'-r','echo PHP_VERSION;'],text=True)}
 (a.work/'report.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
 if a.report:a.report.parent.mkdir(parents=True,exist_ok=True);a.report.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')

if __name__=='__main__':main()
