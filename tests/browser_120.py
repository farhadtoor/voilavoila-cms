#!/usr/bin/env python3
"""Optional Playwright smoke test against disposable CMS state."""
import argparse,json,os,re,shutil,subprocess,tempfile
from pathlib import Path
from run import SOURCE,server
from http_client import Session
from release_120 import docx
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--php',default=os.environ.get('PHP_BIN','php'));p.add_argument('--node',default='node');p.add_argument('--browser-path');p.add_argument('--report',type=Path);p.add_argument('--qa-dir',type=Path);a=p.parse_args()
 php=shutil.which(a.php);assert php
 with tempfile.TemporaryDirectory(prefix='vv-browser-120-') as tmp:
  work=Path(tmp);shutil.copytree(SOURCE,work/'app',ignore=shutil.ignore_patterns('.git','storage','dist','node_modules','__pycache__','build','test-results'))
  (work/'report.docx').write_bytes(docx());(work/'Ressources & tarif.csv').write_text('name,value\nsample,42\n')
  with server(php,work,work/'state') as env:
   s=Session();base=env['VV_TEST_BASE'];r=s.get(base+'/install.php');token=re.search(r'name="csrf" value="([a-f0-9]+)"',r.text).group(1);key=(work/'state/setup-key.php').read_text().split('\n',1)[1]
   r=s.post(base+'/install.php',data={'csrf':token,'setup_key':key,'name':'Browser CMS','email':'owner@example.test','password':'Browser_validation_120!','password_confirm':'Browser_validation_120!'});assert r.status_code==200
   code="require 'app/core/bootstrap.php';$vv->store->change(function(&$s){$s['plugins'][]='comments';});"
   subprocess.run([php,'-r',code],cwd=work,env=env,check=True)
   env={**env,'VV_BROWSER_WORK':str(work)}
   if a.browser_path:env['VV_BROWSER_EXECUTABLE']=str(Path(a.browser_path).resolve())
   subprocess.run([a.node,str(SOURCE/'tests/browser_120.cjs')],env=env,check=True)
  report=json.loads((work/'browser-report.json').read_text())
  if a.report:a.report.parent.mkdir(parents=True,exist_ok=True);a.report.write_text(json.dumps(report,indent=2)+'\n')
  if a.qa_dir:shutil.copytree(work/'qa',a.qa_dir,dirs_exist_ok=True)
if __name__=='__main__':main()
