# Runs only against a temporary synthetic fixture.
from pathlib import Path
import shutil,subprocess,os,json,sqlite3
root=Path.cwd();dest=root/'failure-state';shutil.rmtree(dest,ignore_errors=True);shutil.copytree('legacy-pristine',dest)
s=json.loads((dest/'state.php').read_text().split('\n',1)[1]);mid=next(iter(s['media']));p=dest/'media'/f'{mid}.php';raw=p.read_bytes();p.unlink();source=(dest/'state.php').read_bytes()
def run():return subprocess.run([os.environ['VV_TEST_PHP'],'app/bin/console.php','migrate'],cwd=root,env={**os.environ,'VV_STORAGE_PATH':str(dest)},capture_output=True,text=True)
r=run();assert r.returncode!=0 and (dest/'state.php').read_bytes()==source
with sqlite3.connect(dest/'cms.sqlite') as db:assert db.execute('PRAGMA user_version').fetchone()[0]==0
p.write_bytes(raw);r=run();assert r.returncode==0,r.stderr
with sqlite3.connect(dest/'cms.sqlite') as db:assert db.execute('SELECT COUNT(*) FROM contents').fetchone()[0]==len(s['contents'])
report={'checks':2,'passed':['incomplete legacy migration rolls back and preserves source','corrected legacy migration can be retried successfully']};(root/'migration-failure-results.json').write_text(json.dumps(report,indent=2));print('PASS migration rollback and retry')
