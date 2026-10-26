#!/usr/bin/env python3
import argparse,os,re,shutil,subprocess,tempfile
from pathlib import Path
from run import SOURCE,server,read_state
from http_client import Session
p=argparse.ArgumentParser(description='Real browser QR and OTP sign-in journey');p.add_argument('--php',default='php');p.add_argument('--report',type=Path,required=True);a=p.parse_args();a.report.absolute().parent.mkdir(parents=True,exist_ok=True)
with tempfile.TemporaryDirectory(prefix='vv-browser-otp-') as tmp:
 work=Path(tmp);shutil.copytree(SOURCE,work/'app',ignore=shutil.ignore_patterns('.git','storage','node_modules','__pycache__','dist','test-results','build'))
 with server(a.php,work,work/'state') as env:
  base=env['VV_TEST_BASE'];owner=Session();page=owner.get(base+'/install.php');csrf=re.search('name="csrf" value="([a-f0-9]+)"',page.text).group(1);key=(work/'state/setup-key.php').read_text().split('\n',1)[1];owner.post(base+'/install.php',data={'csrf':csrf,'setup_key':key,'name':'Browser OTP','email':'owner@example.test','password':'OTP_browser_owner_131!','password_confirm':'OTP_browser_owner_131!'})
  page=owner.get(base+'/admin.php?view=plugins');csrf=re.search('name="csrf" value="([a-f0-9]+)"',page.text).group(1);owner.post(base+'/admin.php?view=plugins',data={'csrf':csrf,'action':'plugin.toggle','id':'otp'})
  state=read_state(work/'state');private=next(c['id'] for c in state['contents'].values() if c['type']=='page')
  code='require "app/core/bootstrap.php";$vv->store->change(function(&$s){$s["contents"]["'+private+'"]["status"]="draft";});';subprocess.run([a.php,'-r',code],cwd=work,env={**os.environ,'VV_STORAGE_PATH':str(work/'state')},check=True)
  subprocess.run(['node',str(SOURCE/'tests/browser_otp.cjs')],env={**os.environ,'VV_TEST_BASE':base,'VV_OTP_BROWSER_REPORT':str(a.report.absolute()),'VV_OTP_PRIVATE_ID':private},check=True,timeout=180)
