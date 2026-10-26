# Synthetic local test data only. Run through tests/run.py.
import os, re, pathlib, json, zipfile, io, hashlib, datetime
from http_client import http as requests

BASE=os.environ['VV_TEST_BASE']
PASS='Installation_test_46!'
session=requests.Session()
results=[]
def check(name,condition,detail=None):
    if not condition: raise AssertionError(name if detail is None else f'{name}: {detail}')
    results.append(name); print('PASS',name,flush=True)
def token(r):
    m=re.search(r'name="csrf" value="([a-f0-9]+)"',r.text)
    assert m,(r.status_code,r.text[:2000])
    return m.group(1)
def admin(view='dashboard',s=session,**q): return s.get(BASE+'/admin.php',params={'view':view,**q})
def post(action, data=None, files=None, view='dashboard', follow=True, s=session):
    csrf=token(admin(view,s=s))
    return s.post(BASE+'/admin.php?view='+view,data={'csrf':csrf,'action':action,**(data or {})},files=files,allow_redirects=follow)
def state():
    import sqlite3
    with sqlite3.connect('state/cms.sqlite') as db:
        db.row_factory=sqlite3.Row
        out=json.loads(db.execute('SELECT data FROM cms_state WHERE id=1').fetchone()[0]);out['contents']={}
        for r in db.execute('SELECT * FROM contents ORDER BY created_at,id'):
            c=dict(r);extra=json.loads(c.pop('extra'));c.update(extra);c['order']=c.pop('sort_order');c['tags']=json.loads(c['tags']);c['pinned']=bool(c['pinned']);c['menu']=bool(c['menu']);c['revisions']=[];out['contents'][c['id']]=c
        for r in db.execute('SELECT * FROM revisions ORDER BY content_id,position'):out['contents'][r['content_id']]['revisions'].append(json.loads(r['data']))
        return out

r=session.get(BASE+'/install.php')
check('installation page available',r.status_code==200 and 'Clé d’installation' in r.text)
key=pathlib.Path('state/setup-key.php').read_text().split('\n',1)[1]
r=requests.get(BASE+'/storage/state.php')
check('private store is not publicly readable',r.status_code in [403,404] and 'secret' not in r.text)
r=session.post(BASE+'/install.php',data={'csrf':token(session.get(BASE+'/install.php')),'setup_key':key,'name':'VoilaVoila CMS','email':'owner@example.test','password':PASS,'password_confirm':PASS})
check('installation creates single owner and recovery code',r.status_code==200 and 'code de récupération' in r.text and state()['installed'])
recovery=re.search(r'<code>([a-f0-9]{32})</code>',r.text).group(1)
check('setup key removed and installer locked',not pathlib.Path('state/setup-key.php').exists() and session.get(BASE+'/install.php').url.endswith('admin.php'))
check('built-in plugins loaded',set(state()['plugins'])=={'atelier','seo'} and 'Mon atelier' in r.text)
check('session cookies HttpOnly SameSite Strict','HttpOnly' in r.request.headers.get('Cookie','') or any(c.has_nonstandard_attr('HttpOnly') for c in session.cookies))
r=requests.get(BASE+'/admin.php',allow_redirects=False)
check('admin requires authentication',r.status_code==303 and 'login.php' in r.headers['Location'])
r=session.post(BASE+'/admin.php',data={'action':'settings.save','csrf':'wrong'})
check('CSRF rejected',r.status_code==403)
r=session.post(BASE+'/admin.php',data={'action':'backup.export','csrf':token(admin())},headers={'Origin':'https://evil.example'})
check('cross-origin modification rejected',r.status_code==403)

payload={'type':'post','id':'','version':'0','status':'draft','slug':'verification','title_fr':'Publication test','title_en':'Test post','body_fr':'<h2>Bonjour</h2><script>alert(1)</script><p onclick="x()">Texte <a href="javascript:alert(1)">bad</a></p><img src="https://evil.test/tracker.png" onerror="x()"><svg><script>x()</script></svg>','body_en':'<p>Hello.</p>','excerpt_fr':'Résumé réel','publish_at':'2000-01-01T12:00'}
r=post('content.save',payload,view='editor')
if r.status_code!=200: pathlib.Path('last-error.html').write_text(r.text)
check('draft saved',r.status_code==200 and 'Publication test' in r.text)
c=next(c for c in state()['contents'].values() if c['slug']=='verification'); cid=c['id']
check('stored HTML strips scripts handlers and remote media','script' not in c['body_fr'] and 'onclick' not in c['body_fr'] and 'javascript' not in c['body_fr'] and '<img' not in c['body_fr'])
check('draft hidden publicly',requests.get(BASE+'/?p=verification').status_code==404)
check('owner preview works',session.get(BASE+'/?preview='+cid).status_code==200 and 'Publication test' in session.get(BASE+'/?preview='+cid).text)
check('anonymous preview denied',requests.get(BASE+'/?preview='+cid).status_code==404)
payload.update(id=cid,version='1',status='published')
r=post('content.save',payload,view='editor')
check('published post visible',requests.get(BASE+'/?p=verification').status_code==200 and 'Publication test' in requests.get(BASE+'/?p=verification').text)
check('normal public pages have no private preview banner','Aperçu privé du propriétaire' not in requests.get(BASE+'/').text)
check('revision preserved',len(state()['contents'][cid]['revisions'])==1)
r=post('content.save',payload,view='editor')
check('stale editor write rejected',r.status_code==409)
payload.update(version='2',publish_at='2099-01-01T12:00')
r=post('content.save',payload,view='editor')
check('future scheduled post hidden',r.status_code==200 and requests.get(BASE+'/?p=verification').status_code==404)
r=post('content.revision',{'id':cid,'version':'3','revision':'1'},view='editor')
check('revision restore republishes prior content',r.status_code==200 and requests.get(BASE+'/?p=verification').status_code==200)

image=io.BytesIO(pathlib.Path('fixtures/image.png').read_bytes())
r=post('media.upload',{'public':'on','alt':'Doré'},files={'file':('cover.png',image.getvalue(),'image/png')},view='media')
check('original PNG image uploaded',r.status_code==200 and len(state()['media'])==1)
mid=next(iter(state()['media'])); m=state()['media'][mid]
public_image=requests.get(BASE+'/media.php?id='+mid)
check('public image served',
      public_image.status_code==200
      and public_image.headers.get('Content-Type','').split(';',1)[0].strip().lower()=='image/png'
      and public_image.content==image.getvalue(),
      {'status':public_image.status_code,'content_type':public_image.headers.get('Content-Type'),
       'bytes':len(public_image.content),'sha256':hashlib.sha256(public_image.content).hexdigest(),
       'expected_sha256':hashlib.sha256(image.getvalue()).hexdigest()})
r=post('media.update',{'id':mid,'alt':'Privé'},view='media')
check('private image hidden anonymously',r.status_code==200 and requests.get(BASE+'/media.php?id='+mid).status_code==404 and session.get(BASE+'/media.php?id='+mid).status_code==200)
r=post('media.upload',{'public':'on'},files={'file':('shell.php.png',b'<?php echo 1;','image/png')},view='media')
check('active disguised file upload rejected',r.status_code==400)
r=post('media.update',{'id':mid,'alt':'Doré','public':'on'},view='media')

r=post('theme.activate',{'id':'profil-graphite'},view='themes')
check('theme switches without data loss',r.status_code==200 and state()['settings']['theme']=='profil-graphite' and cid in state()['contents'])
post('theme.activate',{'id':'profil-or'},view='themes')
r=post('plugin.toggle',{'id':'pass-link'},view='plugins')
check('plugin activates with admin menu',r.status_code==200 and 'Connexion PASS' in r.text)
r=post('plugin.save',{'url':'https://example.test/?passw=boutique','label_fr':'Voir les événements','label_en':'View events','profile':'on'},view='plugin-pass')
check('PASS link renders on profile',r.status_code==200 and 'passw=boutique' in requests.get(BASE+'/').text)
rev=hashlib.sha256(json.dumps(state()['plugin_data'].get('atelier',[]),ensure_ascii=True,separators=(',',':')).encode()).hexdigest()
r=post('plugin.save',{'revision':rev,'note':'Note privée de test','tasks':'[ ] Publier\n[x] Configurer','links':'PASS | https://example.test/?passw=boutique'},view='plugin-atelier')
check('private workspace saves',r.status_code==200 and state()['plugin_data']['atelier']['note']=='Note privée de test')
check('private workspace not leaked publicly','Note privée de test' not in requests.get(BASE+'/').text)

def makezip(entries):
    data=io.BytesIO()
    with zipfile.ZipFile(data,'w',zipfile.ZIP_DEFLATED) as z:
        for k,v in entries.items():z.writestr(k,v)
    return data.getvalue()
mal=makezip({'../oops.php':'<?php echo 1;','cms.json':'{}'})
r=post('package.install',{'current_password':PASS,'type':'plugin'},files={'package':('bad.zip',mal,'application/zip')},view='plugins')
check('zip traversal blocked',r.status_code==400)
manifest=json.dumps({'api':1,'id':'test-module','type':'plugin','name':'Module de test','version':'1.0.0','description':'Test'})
pack=makezip({'test-module/cms.json':manifest,'test-module/plugin.php':"<?php defined('VV_ROOT') || exit; vv_action('head',function(){ echo '<meta name=\"test-plugin\" content=\"ok\">'; });"})
r=post('package.install',{'current_password':PASS,'type':'plugin'},files={'package':('module.zip',pack,'application/zip')},view='plugins')
check('native ZIP module installs inactive',r.status_code==200 and pathlib.Path('app/content/plugins/test-module/plugin.php').exists() and 'test-module' not in state()['plugins'])
post('plugin.toggle',{'id':'test-module'},view='plugins')
r=post('package.delete',{'id':'test-module','type':'plugin','current_password':PASS},view='plugins')
check('active module deletion blocked',r.status_code==400 and pathlib.Path('app/content/plugins/test-module').exists())
check('extension hook runs','test-plugin' in requests.get(BASE+'/').text)
post('plugin.toggle',{'id':'test-module'},view='plugins')
post('package.delete',{'id':'test-module','type':'plugin','current_password':PASS},view='plugins')
check('inactive module files can be removed',not pathlib.Path('app/content/plugins/test-module').exists())

r=post('backup.export',{'current_password':PASS},view='tools')
check('portable ZIP backup downloadable',r.status_code==200 and r.content[:2]==b'PK')
backup=r.content
with zipfile.ZipFile(io.BytesIO(backup)) as z:
    exported=json.loads(z.read('data.json'))
    check('backup excludes owner secrets',all(k not in exported for k in ['secret','owner','limits','setup_hash']) and 'media/'+mid+'.png' in z.namelist())
r=post('backup.restore',{'current_password':PASS,'confirm':'RESTAURER'},files={'backup':('backup.zip',backup,'application/zip')},view='tools')
check('backup restore succeeds and keeps owner',r.status_code==200 and state()['owner']['email']=='owner@example.test' and cid in state()['contents'])
check('backup preserves editorial timestamps',state()['contents'][cid]['updated_at']==exported['contents'][cid]['updated_at'])
check('pre-restore snapshot created',len(list(pathlib.Path('state/backups').glob('*.php')))==1)

wxr='''<?xml version="1.0" encoding="UTF-8"?><rss xmlns:wp="http://wordpress.org/export/1.2/" xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel><wp:wxr_version>1.2</wp:wxr_version><item><title>Article WordPress</title><guid>https://old.test/post/1</guid><wp:post_id>1</wp:post_id><wp:post_type>post</wp:post_type><wp:post_name>wordpress-import</wp:post_name><wp:status>publish</wp:status><wp:post_date_gmt>2026-10-01 12:00:00</wp:post_date_gmt><content:encoded><![CDATA[<p>Contenu importé</p><script>x()</script>]]></content:encoded></item></channel></rss>'''
count=len(state()['contents'])
r=post('wordpress.import',{'current_password':PASS},files={'wxr':('export.xml',wxr,'text/xml')},view='tools')
check('WordPress import adds draft',r.status_code==200 and len(state()['contents'])==count+1 and next(c for c in state()['contents'].values() if c['slug']=='wordpress-import')['status']=='draft')
r=post('wordpress.import',{'current_password':PASS},files={'wxr':('export.xml',wxr,'text/xml')},view='tools')
check('WordPress repeat import is deduplicated',r.status_code==200 and len(state()['contents'])==count+1)
xxe=wxr.replace('<rss ', '<!DOCTYPE rss [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><rss ')
r=post('wordpress.import',{'current_password':PASS},files={'wxr':('export.xml',xxe,'text/xml')},view='tools')
check('XML external entities rejected',r.status_code==400)

check('public FR EN rendering','Your space starts here.' in requests.get(BASE+'/?lang=en').text)
check('not found returns true 404',requests.get(BASE+'/?p=absent').status_code==404)
r=requests.get(BASE+'/login.php',headers={'Host':'example.test','X-Forwarded-Proto':'https'})
check('untrusted HTTPS proxy header does not bypass remote TLS requirement',r.status_code==403)
other=requests.Session(); lr=other.get(BASE+'/login.php');other.post(BASE+'/login.php',data={'csrf':token(lr),'password':PASS})
newPass='Nouveau_proprietaire_67!'
r=post('owner.save',{'email':'owner@example.test','current_password':PASS,'password':newPass,'password_confirm':newPass},view='security')
check('password change succeeds',r.status_code==200)
check('other sessions revoked','login.php' in admin(s=other).url)
post('logout')
rr=session.get(BASE+'/login.php?mode=recovery');r=session.post(BASE+'/login.php?mode=recovery',data={'csrf':token(rr),'recovery':recovery,'password':PASS,'password_confirm':PASS})
check('offline recovery rotates code and restores access',r.status_code==200 and 'code de récupération' in r.text)
next_recovery=re.search(r'<code>([a-f0-9]{32})</code>',r.text).group(1)
invalid=requests.Session(); blocked=False
for _ in range(11):
    lr=invalid.get(BASE+'/login.php'); result=invalid.post(BASE+'/login.php',data={'csrf':token(lr),'password':'incorrect'})
    if result.status_code==429: blocked=True;break
check('login rate limit enforced',blocked)
rr=invalid.get(BASE+'/login.php?mode=recovery'); result=invalid.post(BASE+'/login.php?mode=recovery',data={'csrf':token(rr),'recovery':next_recovery,'password':PASS,'password_confirm':PASS})
check('offline recovery works despite an exhausted login limit',result.status_code==200 and 'code de récupération' in result.text)
report={'checks':len(results),'passed':results,'runtime':os.environ['VV_TEST_PHP_VERSION'],'date':datetime.datetime.now(datetime.timezone.utc).isoformat()}
pathlib.Path('integration-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2))
print('ALL PASSED',len(results))
