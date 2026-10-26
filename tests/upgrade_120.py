#!/usr/bin/env python3
"""Verify a real 1.1.0 archive can be upgraded to current source without data loss."""
import argparse, hashlib, json, os, re, shutil, subprocess, tempfile, zipfile
from pathlib import Path
from run import SOURCE, server, read_state, CMS_VERSION
from http_client import Session

def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--previous-zip',type=Path,required=True);p.add_argument('--php',default=os.environ.get('PHP_BIN','php'));p.add_argument('--report',type=Path);args=p.parse_args()
    php=shutil.which(args.php);assert php
    expected_sha='e56a1ddc98f24e1d33fd11713d0ff014a74fc7b7201a0af3ffaf3df1a3172029'
    assert hashlib.sha256(args.previous_zip.read_bytes()).hexdigest()==expected_sha,'Unexpected original 1.1.0 archive'
    checks=[]
    def ck(n,ok):
        assert ok,n
        checks.append(n);print('PASS',n,flush=True)
    with tempfile.TemporaryDirectory(prefix='vv-upgrade-120-') as tmp:
        work=Path(tmp)
        with zipfile.ZipFile(args.previous_zip) as z:
            for item in z.infolist():
                relative=Path(item.filename)
                assert relative.parts[0]=='voila-cms' and '..' not in relative.parts
                target=work/'app'/Path(*relative.parts[1:])
                if item.is_dir():target.mkdir(parents=True,exist_ok=True)
                else:target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(z.read(item))
        s=Session();storage=work/'state';password='Upgrade_120_validation!'
        with server(php,work,storage) as env:
            base=env['VV_TEST_BASE'];r=s.get(base+'/install.php');token=re.search(r'name="csrf" value="([a-f0-9]+)"',r.text).group(1);key=(storage/'setup-key.php').read_text().split('\n',1)[1]
            r=s.post(base+'/install.php',data={'csrf':token,'setup_key':key,'name':'Existing 1.1 site','email':'owner@example.test','password':password,'password_confirm':password})
            ck('original 1.1.0 archive installs',r.status_code==200 and '1.1.0' in r.text)
            code="$vv->store->change(function(&$s){$s['plugin_data']['atelier']=['note'=>'Existing private note'];$s['settings']['theme']='profil-graphite';$s['plugins'][]='pass-link';});"
            subprocess.run([php,'-r',"require 'app/core/bootstrap.php';"+code],cwd=work,env=env,check=True)
            before=read_state(storage);database=(storage/'cms.sqlite').read_bytes()
        shutil.copytree(SOURCE,work/'app',dirs_exist_ok=True,ignore=shutil.ignore_patterns('.git','storage','__pycache__','dist','node_modules','test-results','build'))
        with server(php,work,storage) as env:
            after=read_state(storage)
            ck('upgrade preserves owner credentials secret and recovery hash',all(before[k]==after[k] for k in ['owner','secret']))
            ck('upgrade preserves exact posts pages and revisions',before['contents']==after['contents'])
            ck('upgrade preserves existing plugin data settings and activation choices',all(before[k]==after[k] for k in ['settings','plugin_data','plugins']))
            ck('upgrade does not generate a new setup key',not (storage/'setup-key.php').exists())
            ck('existing owner session remains usable on 1.2',s.get(env['VV_TEST_BASE']+'/admin.php').status_code==200 and CMS_VERSION in s.get(env['VV_TEST_BASE']+'/admin.php').text) if base==env['VV_TEST_BASE'] else ck('existing owner can sign in on 1.2',_login(s,env['VV_TEST_BASE'],password))
            ck('new optional plugins are available and remain inactive','comments' not in after['plugins'] and 'wp-porter' not in after['plugins'] and (work/'app/content/plugins/comments/plugin.php').is_file() and (work/'app/content/plugins/wp-porter/plugin.php').is_file())
            import sqlite3
            with sqlite3.connect(storage/'cms.sqlite') as db:
                ck('upgrade keeps SQLite schema and integrity',db.execute('PRAGMA user_version').fetchone()[0]==1 and db.execute('PRAGMA integrity_check').fetchone()[0]=='ok')
    report={'checks':len(checks),'passed':checks,'source_sha256':expected_sha,'runtime':subprocess.check_output([php,'-r','echo PHP_VERSION;'],text=True)}
    if args.report:args.report.parent.mkdir(parents=True,exist_ok=True);args.report.write_text(json.dumps(report,indent=2)+'\n')
def _login(session,base,password):
    r=session.get(base+'/login.php');m=re.search(r'name="csrf" value="([a-f0-9]+)"',r.text)
    if not m:return False
    r=session.post(base+'/login.php',data={'csrf':m.group(1),'password':password})
    return r.status_code==200 and CMS_VERSION in r.text and '/login.php' not in r.url
if __name__=='__main__':main()
