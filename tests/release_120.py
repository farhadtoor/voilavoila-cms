#!/usr/bin/env python3
"""Security and migration scenarios for 1.2.0. Isolated synthetic data only."""
import argparse, copy, hashlib, hmac, io, json, os, re, shutil, subprocess, tempfile, time, zipfile
from pathlib import Path
from run import SOURCE, server, read_state
from http_client import Session

PASSWORD='Release_validation_120!'
DOCX_MIME='application/vnd.openxmlformats-officedocument.wordprocessingml.document'
def archive(entries, compression=zipfile.ZIP_DEFLATED):
    out=io.BytesIO()
    with zipfile.ZipFile(out,'w',compression) as z:
        for name,body in entries.items():z.writestr(name,body)
    return out.getvalue()
def docx(extra=None):
    return archive({
        '[Content_Types].xml':'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="'+DOCX_MIME+'.main+xml"/></Types>',
        '_rels/.rels':'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
        'word/document.xml':'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Hello</w:t></w:r></w:p></w:body></w:document>',
        **(extra or {})})
def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--php',default=os.environ.get('PHP_BIN','php'));parser.add_argument('--report',type=Path);args=parser.parse_args()
    php=shutil.which(args.php);assert php,'PHP not found'
    checks=[]
    def ck(name,condition):
        if not condition:raise AssertionError(name)
        checks.append(name);print('PASS',name,flush=True)
    with tempfile.TemporaryDirectory(prefix='vv-cms-120-') as tmp:
        work=Path(tmp);shutil.copytree(SOURCE,work/'app',ignore=shutil.ignore_patterns('.git','storage','__pycache__','dist','node_modules','build','test-results'))
        storage=work/'state'
        with server(php,work,storage) as env:
            base=env['VV_TEST_BASE'];owner=Session();guest=Session()
            def state():return read_state(storage)
            def token(r):return re.search(r'name="csrf" value="([a-f0-9]+)"',r.text).group(1)
            def get(view='media'):return owner.get(base+'/admin.php?view='+view)
            def post(action,fields=None,files=None,view='media',follow=True):
                return owner.post(base+'/admin.php?view='+view,data={'csrf':token(get(view)),'action':action,**(fields or {})},files=files,allow_redirects=follow)
            def cli(code):
                r=subprocess.run([php,'-r',"require 'app/core/bootstrap.php';"+code],cwd=work,env=env,text=True,capture_output=True)
                assert r.returncode==0,r.stderr
                return r.stdout
            def clear_limits():cli("$vv->store->change(function(&$s){$s['limits']=[];});")
            def upload(name,body,public=True,mime='application/octet-stream'):
                return post('media.upload',{'public':'on'} if public else {},{'file':(name,body,mime)})
            def comment(data,client=None,headers=None):
                client=client or guest
                return client.post(base+'/index.php?p=bienvenue',data={'cms_action':'comment.submit','content_id':welcome['id'],'comments_token':guest_token,'name':'Visitor','body':'A moderated message','consent':'on',**data},headers=headers,allow_redirects=False)
            installed=owner.get(base+'/install.php')
            key=(storage/'setup-key.php').read_text().split('\n',1)[1]
            r=owner.post(base+'/install.php',data={'csrf':token(installed),'setup_key':key,'name':'Release 1.2','email':'owner@example.test','password':PASSWORD,'password_confirm':PASSWORD})
            ck('fresh 1.2 installation with optional plugins inactive',r.status_code==200 and '1.2.0' in r.text and 'comments' not in state()['plugins'] and 'wp-porter' not in state()['plugins'])
            pdf=b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n'
            csv=b'\xef\xbb\xbfname,value\r\nsample,42\r\n'
            bundle=archive({'guide.txt':'A document','notes/data.csv':csv,'guide.pdf':pdf})
            docs={'guide.pdf':(pdf,'application/pdf'),'report.docx':(docx(),DOCX_MIME),'resources.zip':(bundle,'application/zip'),'data.csv':(csv,'text/csv')}
            for name,(raw,mime) in docs.items():
                r=upload(name,raw)
                ck('upload '+name+' validates real bytes independently of request MIME',r.status_code==200)
                m=next(m for m in state()['media'].values() if m['name']==name);got=guest.get(base+'/media.php?id='+m['id'])
                ck(name+' download has attachment, nosniff, sandbox and original bytes',got.status_code==200 and got.content==raw and got.headers['Content-Type'].split(';')[0]==mime and got.headers['Content-Disposition'].startswith('attachment;') and got.headers['X-Content-Type-Options']=='nosniff' and 'sandbox' in got.headers['Content-Security-Policy'])
                ck(name+' storage retains original extension and SHA',m['stored_name'].endswith('.'+name.split('.')[-1]) and (storage/'media'/m['stored_name']).read_bytes()==raw and m['sha256']==hashlib.sha256(raw).hexdigest())
            r=upload('private.csv',csv,False);private=next(m for m in state()['media'].values() if m['name']=='private.csv')
            ck('private document requires owner even with download flag',r.status_code==200 and guest.get(base+'/media.php?id='+private['id']+'&download=1').status_code==404 and owner.get(base+'/media.php?id='+private['id']).content==csv)
            ck('raw document and SQLite URLs remain denied',guest.get(base+'/storage/media/'+private['stored_name']).status_code in [403,404] and guest.get(base+'/storage/cms.sqlite').status_code in [403,404])
            ck('editor offers a public document picker', 'id="insert-document"' in get('editor').text and 'report.docx' in get('editor').text and 'private.csv' not in get('editor').text)
            bad={
                'php-name':('shell.php.pdf',pdf),
                'mime-mismatch':('fake.pdf',b'<html><script>bad()</script></html>'),
                'csv-binary':('bad.csv',b'name,value\nbad,\x00data'),
                'csv-active':('bad.csv',b'<?php echo "no"; ?>'),
                'docx-missing-parts':('bad.docx',archive({'notes.txt':'text'})),
                'docx-macro':('bad.docx',docx({'word/vbaProject.bin':'blocked'})),
                'docx-dde-field':('bad.docx',docx({'word/document.xml':'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:instrText>DDE cmd</w:instrText></w:body></w:document>'})),
                'docx-simple-dde-field':('bad.docx',docx({'word/document.xml':'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:fldSimple w:instr="DDEAUTO cmd"/></w:body></w:document>'})),
                'docx-relationship':('bad.docx',docx({'word/_rels/document.xml.rels':'<Relationships><Relationship TargetMode="External" Target="https://example.test/template.dotm"/></Relationships>'})),
                'docx-entities':('bad.docx',docx({'word/styles.xml':'<!DOCTYPE x [<!ENTITY xx SYSTEM "file:///etc/passwd">]><x>&xx;</x>'})),
                'docx-utf16-entities':('bad.docx',docx({'word/styles.xml':'<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE x [<!ENTITY xx SYSTEM "file:///etc/passwd">]><x>&xx;</x>'.encode('utf-16')})),
                'zip-traversal':('bad.zip',archive({'../escape.txt':'blocked'})),
                'zip-code':('bad.zip',archive({'shell.php':'<?php echo "no";'})),
                'zip-hidden':('bad.zip',archive({'.htaccess':'SetHandler application/x-httpd-php'})),
                'zip-nested':('bad.zip',archive({'nested.zip':bundle})),
                'zip-case-duplicate':('bad.zip',archive({'a.txt':'a','A.txt':'b'})),
                'zip-entry-limit':('bad.zip',archive({str(i)+'.txt':'a' for i in range(201)})),
                'zip-expansion':('bad.zip',archive({'large.txt':'x'*2097152})),
                'oversized':('huge.csv',b'x'*(8*1024*1024+1))
            }
            special=zipfile.ZipInfo('pipe.txt');special.create_system=3;special.external_attr=(0o010644 << 16)
            out=io.BytesIO()
            with zipfile.ZipFile(out,'w') as z:z.writestr(special,b'data')
            bad['zip-special']=('bad.zip',out.getvalue())
            corrupted=bytearray(archive({'crc.txt':'CRC_MARKER'},zipfile.ZIP_STORED));corrupted[corrupted.index(b'CRC_MARKER')]=ord('X');bad['zip-crc']=('bad.zip',bytes(corrupted))
            for label,(name,raw) in bad.items():
                before=len(state()['media']);r=upload(name,raw)
                ck('reject '+label+' without adding media',r.status_code in [400,413] and len(state()['media'])==before)
            # Existing owner workflow and document insertion survive backup/restore.
            welcome=next(c for c in state()['contents'].values() if c['slug']=='bienvenue')
            original=copy.deepcopy(welcome);payload={**welcome,'version':str(welcome['version']),'tags':','.join(welcome['tags']),'publish_at':'2000-01-01T12:00'}
            payload['body_fr']=''.join('<p><a href="/media.php?id='+m['id']+'&amp;download=1">'+m['name']+'</a></p>' for m in state()['media'].values() if m['public'])
            r=post('content.save',payload,view='editor')
            ck('document links save and render on posts',r.status_code==200 and 'report.docx' in guest.get(base+'/?p=bienvenue').text)
            backup=post('backup.export',view='tools').content
            with zipfile.ZipFile(io.BytesIO(backup)) as z:
                data=json.loads(z.read('data.json'))
                ck('portable backup includes original document formats',all('media/'+m['stored_name'] in z.namelist() and z.read('media/'+m['stored_name'])==docs[m['name']][0] for m in data['media'].values() if m['name'] in docs))
            r=post('backup.restore',{'current_password':PASSWORD,'confirm':'RESTAURER'},{'backup':('backup.zip',backup,'application/zip')},view='tools')
            ck('portable restore revalidates and retains documents',r.status_code==200 and len(state()['media'])==5)
            forged=copy.deepcopy(data);mid=next(iter(forged['media']));forged['media'][mid]['name']='shell.php.pdf'
            with zipfile.ZipFile(io.BytesIO(backup)) as old:
                corrupt=archive({n:(json.dumps(forged) if n=='data.json' else old.read(n)) for n in old.namelist()})
            before=state();r=post('backup.restore',{'current_password':PASSWORD,'confirm':'RESTAURER'},{'backup':('bad.zip',corrupt,'application/zip')},view='tools')
            ck('restore rejects executable filename without replacing content',r.status_code==400 and state()['contents']==before['contents'])
            ck('anonymous cannot open comment moderation',guest.get(base+'/admin.php?view=plugin-comments',allow_redirects=False).status_code==303)
            r=post('plugin.toggle',{'id':'comments'},view='plugins')
            ck('comments plugin activates with moderation menu',r.status_code==200 and 'Commentaires' in get('plugin-comments').text)
            guest=Session();page=guest.get(base+'/?p=bienvenue')
            if 'comments_token' not in page.text:
                print('COMMENT DEBUG',page.status_code, page.text[-1500:], (storage/'logs/error.txt').read_text()[-2000:], flush=True)
            guest_token=re.search(r'name="comments_token" value="([^"]+)"',page.text).group(1)
            ck('public comment form uses dedicated HttpOnly cookie and no owner session',page.headers['Cache-Control']=='private, no-store' and any(c.name.startswith('vvguest-') and c.has_nonstandard_attr('HttpOnly') for c in guest.cookies) and not any(c.name=='vvcms' for c in guest.cookies))
            ck('cross-origin comment rejected',comment({},headers={'Origin':'https://evil.example'}).status_code==403)
            ck('forged comment token rejected',comment({'comments_token':'forged'}).status_code==403)
            ck('token stolen without matching cookie rejected',comment({},client=Session()).status_code==403)
            cookie=next(c for c in guest.cookies if c.name.startswith('vvguest-'))
            saved_cookie=cookie.value
            payload=str(int(time.time())-7201)+'.'+('a'*48)
            expired=payload+'.'+hmac.new(state()['secret'].encode(),('comments|'+payload).encode(),hashlib.sha256).hexdigest()
            cookie.value=expired
            ck('correctly signed but expired guest token is rejected',comment({'comments_token':expired}).status_code==403)
            cookie.value=saved_cookie
            ck('comment honeypot rejected',comment({'website':'https://spam.example'}).status_code==400)
            ck('publication consent required',comment({'consent':''}).status_code==400)
            clear_limits()
            r=comment({'body':'<script>window.bad=1</script>A <b>moderated</b> message'})
            ck('guest comment enters pending state',r.status_code==303 and any(x['status']=='pending' and 'moderated' in x['body'] for x in state()['plugin_data']['comments']['rows'].values()))
            ck('pending comment not public','window.bad=1' not in guest.get(base+'/?p=bienvenue').text)
            rows=state()['plugin_data']['comments']['rows'];row=next(r for r in rows.values() if 'moderated' in r['body'])
            ck('comment stores no email or raw IP',all('email' not in r and 'ip' not in r for r in rows.values()))
            mod={'op':'approved','id':row['id'],'version':str(row['version'])}
            ck('moderation rejects stale version',post('plugin.save',{**mod,'version':'0'},view='plugin-comments').status_code==409)
            r=post('plugin.save',mod,view='plugin-comments')
            public=guest.get(base+'/?p=bienvenue')
            ck('approved comment is escaped on public page',r.status_code==200 and 'window.bad=1' in public.text and '<script>window.bad' not in public.text and 'A moderated message' in public.text)
            current=state()['plugin_data']['comments']['rows'][row['id']]
            r=post('plugin.save',{'op':'spam','id':row['id'],'version':str(current['version'])},view='plugin-comments')
            ck('spam comment disappears publicly',r.status_code==200 and 'window.bad=1' not in guest.get(base+'/?p=bienvenue').text)
            clear_limits()
            for i in range(5):assert comment({'body':'Rate limited test '+str(i)}).status_code==303
            ck('sixth anonymous comment is rate limited',comment({'body':'Sixth rate test'}).status_code==429)
            clear_limits()
            r=post('plugin.save',{'op':'export'},view='plugin-comments')
            ck('owner can export comments JSON',r.status_code==200 and json.loads(r.text)['schema']==1 and r.headers['Content-Disposition'].startswith('attachment;'))
            r=post('plugin.toggle',{'id':'comments'},view='plugins')
            ck('deactivation retains comments and removes public form',r.status_code==200 and 'comments_token' not in guest.get(base+'/?p=bienvenue').text and len(state()['plugin_data']['comments']['rows'])>0)
            post('plugin.toggle',{'id':'comments'},view='plugins')
            # Export WXR with excerpts/tags/statuses, image/document links and local media.
            subprocess.run([php,'-r',"$i=imagecreatetruecolor(80,60);imagejpeg($i,'photo.jpeg');imagedestroy($i);"],cwd=work,check=True)
            jpeg=(work/'photo.jpeg').read_bytes()
            upload_zip=archive({'uploads/2026/10/photo.jpeg':jpeg,'uploads/2026/10/report.docx':docs['report.docx'][0],'uploads/2026/10/data.csv':csv})
            def item(pid,kind='post',status='publish',body='<p>WordPress body</p>',extra='',title=None):
                return f'<item><title>{title or "WP "+str(pid)}</title><link>https://old.example/blog/post-{pid}/</link><guid>https://old.example/blog/?p={pid}</guid><wp:post_id>{pid}</wp:post_id><wp:post_type>{kind}</wp:post_type><wp:post_name>wp-{pid}</wp:post_name><wp:status>{status}</wp:status><wp:post_date_gmt>{"2099-01-01 00:00:00" if status=="future" else "2024-01-02 03:04:05"}</wp:post_date_gmt><wp:post_modified_gmt>2024-01-03 04:05:06</wp:post_modified_gmt><content:encoded><![CDATA[{body}]]></content:encoded>{extra}</item>'
            thumb='<wp:postmeta><wp:meta_key>_thumbnail_id</wp:meta_key><wp:meta_value>201</wp:meta_value></wp:postmeta>'
            comments=''.join(f'<wp:comment><wp:comment_id>{i}</wp:comment_id><wp:comment_author>WP visitor</wp:comment_author><wp:comment_approved>{status}</wp:comment_approved><wp:comment_date_gmt>2024-01-04 00:00:00</wp:comment_date_gmt><wp:comment_content>Imported visitor message {i}</wp:comment_content></wp:comment>' for i,status in [(1,'1'),(2,'0'),(3,'spam')])
            body='<p><a href="https://old.example/blog/post-102/">Internal</a><a href="https://old.example/wp-content/uploads/2026/10/report.docx">Document</a></p><img src="https://old.example/wp-content/uploads/2026/10/photo-300x200.jpeg" srcset="bad"><script>window.importedBad=1</script>[gallery ids="201"]'
            rows=[item(101,body=body,extra=thumb+comments+'<excerpt:encoded><![CDATA[A real excerpt]]></excerpt:encoded><category domain="category">News</category><category domain="post_tag">PHP</category><wp:is_sticky>1</wp:is_sticky>'),item(102,kind='page'),item(103,status='private'),item(104,status='future'),item(105,extra='<wp:post_password>PRIVATE_PASSWORD_NOT_IMPORTED</wp:post_password>'),item(106,extra='<wp:comment_status>closed</wp:comment_status>'),item(999,kind='product')]
            for pid,name in [(201,'photo.jpeg'),(202,'report.docx'),(203,'data.csv')]:
                rows.append(item(pid,kind='attachment',extra=f'<wp:attachment_url>https://old.example/wp-content/uploads/2026/10/{name}</wp:attachment_url><wp:postmeta><wp:meta_key>_wp_attached_file</wp:meta_key><wp:meta_value>2026/10/{name}</wp:meta_value></wp:postmeta>'))
            def wxr(rows,version='1.2'):
                return '<?xml version="1.0"?><rss xmlns:wp="http://wordpress.org/export/'+version+'/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"><channel><wp:wxr_version>'+version+'</wp:wxr_version><wp:base_blog_url>https://old.example/blog</wp:base_blog_url>'+''.join(rows)+'</channel></rss>'
            fields={'current_password':PASSWORD,'publish':'on','public_media':'on','import_comments':'on'}
            r=post('wordpress.import',fields,{'wxr':('export.xml',wxr(rows),'text/xml'),'uploads':('uploads.zip',upload_zip,'application/zip')},view='tools')
            if r.status_code!=200:print('IMPORT ERROR',re.findall(r'<div class="notice error"[^>]*>(.*?)</div>',r.text),flush=True)
            ck('WXR with offline media and comments imports successfully',r.status_code==200)
            imported={str(c.get('wp_id')):c for c in state()['contents'].values() if c.get('wp_source')=='https://old.example/blog'}
            ck('WXR preserves excerpts flat tags sticky and UTC dates',imported['101']['excerpt_fr']=='A real excerpt' and imported['101']['tags']==['News','PHP'] and imported['101']['pinned'] and imported['101']['created_at'].startswith('2024-01-02') and imported['101']['updated_at'].startswith('2024-01-03'))
            ck('WXR preserves private protected and scheduled visibility',imported['103']['status']=='private' and imported['105']['status']=='private' and imported['104']['publish_at']>4000000000 and guest.get(base+'/?p='+imported['103']['slug']).status_code==404 and guest.get(base+'/?p='+imported['104']['slug']).status_code==404)
            ck('WXR rewrites internal links and removes active content',('/index.php?p='+imported['102']['slug']) in imported['101']['body_fr'] and 'window.importedBad' not in imported['101']['body_fr'] and 'srcset' not in imported['101']['body_fr'])
            mapped_media={str(m.get('wp_id')):m for m in state()['media'].values() if m.get('wp_source')=='https://old.example/blog'}
            ck('WXR restores resized image gallery cover and DOCX links',len(mapped_media)==3 and imported['101']['cover']==mapped_media['201']['id'] and imported['101']['body_fr'].count(mapped_media['201']['id'])>=2 and mapped_media['202']['id'] in imported['101']['body_fr'])
            imported_comments=[r for r in state()['plugin_data']['comments']['rows'].values() if 'wp_key' in r]
            ck('WXR imports only real comments and queues all for review',len(imported_comments)==2 and all(r['status']=='pending' for r in imported_comments))
            ck('WXR closed-comments flag is enforced','comments_token' not in guest.get(base+'/?p='+imported['106']['slug']).text)
            clear_limits()
            ck('guest cannot attach a comment to private WordPress content',comment({'content_id':imported['103']['id']}).status_code==404)

            report=post('wordpress.report',view='tools')
            ck('owner downloads import report with URL mapping',report.status_code==200 and json.loads(report.text)['imported']==6 and len(json.loads(report.text)['links'])>=6 and 'PRIVATE_PASSWORD_NOT_IMPORTED' not in report.text)
            c=imported['101'];edit={**c,'title_fr':'Local edited title','version':str(c['version']),'tags':','.join(c['tags']),'publish_at':'2024-01-02T03:04'}
            assert post('content.save',edit,view='editor').status_code==200
            r=post('wordpress.import',fields,{'wxr':('export.xml',wxr([s.replace('WP 101','Changed WordPress title') for s in rows]),'text/xml'),'uploads':('uploads.zip',upload_zip,'application/zip')},view='tools')
            st=state()
            ck('repeat import survives source-title changes and local edits',r.status_code==200 and st['contents'][c['id']]['title_fr']=='Local edited title' and st['imports']['wordpress']['imported']==0 and st['imports']['wordpress']['duplicates']==6 and st['imports']['wordpress']['media_reused']==3 and st['imports']['wordpress']['comments_imported']==0)
            for v,pid in [('1.0',401),('1.1',402)]:
                clear_limits()
                r=post('wordpress.import',{'current_password':PASSWORD},{'wxr':('export.xml',wxr([item(pid)],v),'text/xml')},view='tools')
                ck('WXR '+v+' namespace supported',r.status_code==200 and any(c.get('wp_id')==str(pid) for c in state()['contents'].values()))
            clear_limits()
            private_rows=[item(501),item(502,kind='attachment',extra='<wp:attachment_url>https://old.example/wp-content/uploads/2026/10/data.csv</wp:attachment_url><wp:postmeta><wp:meta_key>_wp_attached_file</wp:meta_key><wp:meta_value>2026/10/data.csv</wp:meta_value></wp:postmeta>')]
            r=post('wordpress.import',{'current_password':PASSWORD},{'wxr':('export.xml',wxr(private_rows),'text/xml'),'uploads':('uploads.zip',upload_zip,'application/zip')},view='tools')
            ck('WXR defaults to draft contents and private media',r.status_code==200 and next(c for c in state()['contents'].values() if c.get('wp_id')=='501')['status']=='draft' and next(m for m in state()['media'].values() if m.get('wp_id')==502 or m.get('wp_id')=='502')['public'] is False)
            before=state()['contents']
            malicious='<!DOCTYPE rss [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'+wxr([item(601)]).replace('<?xml version="1.0"?>','')
            r=post('wordpress.import',{'current_password':PASSWORD},{'wxr':('export.xml',malicious,'text/xml')},view='tools')
            ck('WXR rejects DTD and leaves existing contents unchanged',r.status_code==400 and state()['contents']==before)
            r=post('wordpress.import',{'current_password':PASSWORD},{'wxr':('export.xml',wxr([item(601)]),'text/xml'),'uploads':('uploads.zip',archive({'../escape.txt':'no'}),'application/zip')},view='tools')
            ck('unsafe uploads ZIP aborts before changing contents',r.status_code==400 and state()['contents']==before)
            clear_limits()
            # Rollback both new rows and new media files if the plugin rejects a full queue.
            saved_comments=copy.deepcopy(state()['plugin_data']['comments'])
            full=copy.deepcopy(saved_comments)
            prototype={'content_id':welcome['id'],'name':'Existing visitor','body':'Existing message','status':'pending','created_at':'2024-01-01T00:00:00+00:00','version':1}
            full['rows']={format(i+10000,'024x'):{**prototype,'id':format(i+10000,'024x')} for i in range(1000)}
            (work/'comments-fixture.json').write_text(json.dumps(full))
            cli("$vv->store->change(function(&$s){$s['plugin_data']['comments']=json_decode(file_get_contents('comments-fixture.json'),true);});")
            before=state();files_before={p.name:p.read_bytes() for p in (storage/'media').iterdir() if p.is_file()}
            rollback_rows=[item(701,extra=comments),item(702,kind='attachment',extra='<wp:attachment_url>https://old.example/wp-content/uploads/2026/10/data.csv</wp:attachment_url><wp:postmeta><wp:meta_key>_wp_attached_file</wp:meta_key><wp:meta_value>2026/10/data.csv</wp:meta_value></wp:postmeta>')]
            r=post('wordpress.import',{'current_password':PASSWORD,'import_comments':'on'},{'wxr':('export.xml',wxr(rollback_rows),'text/xml'),'uploads':('uploads.zip',upload_zip,'application/zip')},view='tools')
            after=state();files_after={p.name:p.read_bytes() for p in (storage/'media').iterdir() if p.is_file()}
            ck('plugin rejection rolls back imported rows and removes newly written files',r.status_code==413 and before['contents']==after['contents'] and before['media']==after['media'] and files_before==files_after)
            (work/'comments-fixture.json').write_text(json.dumps(saved_comments))
            cli("$vv->store->change(function(&$s){$s['plugin_data']['comments']=json_decode(file_get_contents('comments-fixture.json'),true);});")
            clear_limits()
            before=state();portable=post('backup.export',view='tools').content
            r=post('backup.restore',{'current_password':PASSWORD,'confirm':'RESTAURER'},{'backup':('portable.zip',portable,'application/zip')},view='tools')
            after=state()
            ck('portable restore retains comments and WordPress provenance',r.status_code==200 and before['plugin_data']['comments']==after['plugin_data']['comments'] and all(after['contents'][cid].get('wp_key')==c.get('wp_key') for cid,c in before['contents'].items()) and all(after['media'][mid].get('wp_key')==m.get('wp_key') and after['media'][mid].get('wp_id')==m.get('wp_id') for mid,m in before['media'].items()))
            # Optional WordPress Porter stays independent of the CMS database.
            porter=work/'app/content/plugins/wp-porter'
            result=subprocess.run([php,str(porter/'bin/examples.php'),str(work/'wp-examples')],capture_output=True,text=True,env=env)
            assert result.returncode==0,result.stderr
            source=next((work/'wp-examples').rglob('*plugin-static*.zip'))
            result=subprocess.run([php,str(porter/'bin/porter.php'),'convert',str(source),str(work/'ported.zip')],capture_output=True,text=True,env=env)
            ck('bundled WordPress Porter still converts its supported plugin subset',result.returncode==0 and (work/'ported.zip').exists())
            manifest={'api':1,'id':'future-module','type':'plugin','name':'Future module','version':'1.0.0','requires_cms':'99.0.0'}
            r=post('package.install',{'type':'plugin','current_password':PASSWORD},{'package':('future.zip',archive({'future-module/cms.json':json.dumps(manifest),'future-module/plugin.php':"<?php defined('VV_ROOT') || exit;"}),'application/zip')},view='plugins')
            ck('native module minimum CMS compatibility is checked',r.status_code==400 and not (work/'app/content/plugins/future-module').exists())
            r=post('plugin.save',{'op':'purge','confirm':'PURGE','current_password':'wrong'},view='plugin-comments')
            ck('purging comments requires the current owner password',r.status_code==403 and len(state()['plugin_data']['comments']['rows'])>0)
            r=post('plugin.save',{'op':'purge','confirm':'PURGE','current_password':PASSWORD},view='plugin-comments')
            ck('owner can explicitly purge all comment data',r.status_code==200 and not state()['plugin_data']['comments']['rows'])
        report={'version':'1.2.0','runtime':subprocess.check_output([php,'-r','echo PHP_VERSION;'],text=True),'checks':len(checks),'passed':checks,'fixture':'Temporary synthetic data; removed after run'}
        if args.report:args.report.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
        print('PASS',len(checks),'release 1.2 checks',flush=True)
if __name__=='__main__':main()
