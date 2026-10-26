#!/usr/bin/env python3
"""Real HTTP MFA tests plus RFC 6238 SHA-1/256/512 vectors; synthetic owner only."""
import argparse,base64,hashlib,hmac,html,io,json,os,re,shutil,struct,subprocess,tempfile,time,zipfile
from pathlib import Path
from run import SOURCE,server,read_state
from http_client import Session
PASSWORD='Authenticator_owner_131!'
def totp(secret,step=None):
 step=int(time.time()//30) if step is None else step
 digest=hmac.new(base64.b32decode(secret),struct.pack('!Q',step),hashlib.sha1).digest();offset=digest[-1]&15;number=struct.unpack('!I',digest[offset:offset+4])[0]&0x7fffffff;return str(number%1000000).zfill(6)
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--php',default='php');p.add_argument('--report',type=Path);a=p.parse_args();checks=[]
 def ck(name,ok):
  if not ok:raise AssertionError(name)
  checks.append(name);print('PASS',name,flush=True)
 with tempfile.TemporaryDirectory(prefix='vv-otp-') as temp:
  work=Path(temp);shutil.copytree(SOURCE,work/'app',ignore=shutil.ignore_patterns('.git','storage','node_modules','__pycache__','dist','test-results','build'))
  with server(a.php,work,work/'state') as env:
   base=env['VV_TEST_BASE'];owner=Session();other=Session();guest=Session()
   def csrf(r):return re.search(r'name="csrf" value="([a-f0-9]+)"',r.text).group(1)
   def admin(data,view='plugin-otp',files=None):return owner.post(base+'/admin.php?view='+view,data={'action':'plugin.save','csrf':csrf(owner.get(base+'/admin.php?view='+view)),**data},files=files,allow_redirects=False)
   def login(session=owner):
    session.cookies.clear()
    page=session.get(base+'/login.php');return session.post(base+'/login.php',data={'csrf':csrf(page),'password':PASSWORD},allow_redirects=False)
   def factor(code,session=owner):
    page=session.get(base+'/login.php?factor=1');return session.post(base+'/login.php?factor=1',data={'csrf':csrf(page),'otp':code},allow_redirects=False)
   def state():return read_state(work/'state')
   def mutate(code):return subprocess.check_output([a.php,'-r','require '+json.dumps(str(work/'app/core/bootstrap.php'))+';'+code],env={**os.environ,'VV_STORAGE_PATH':str(work/'state')},text=True)
   r=owner.get(base+'/install.php');key=(work/'state/setup-key.php').read_text().split('\n',1)[1];r=owner.post(base+'/install.php',data={'csrf':csrf(r),'setup_key':key,'name':'OTP fixture','email':'owner@example.test','password':PASSWORD,'password_confirm':PASSWORD});native_recovery=re.search(r'<code>([a-f0-9]{32})</code>',r.text).group(1)
   ck('baseline owner signs in before OTP',r.status_code==200 and login(other).headers['Location'].startswith('/admin.php'))
   # Install the actual addon archive through the native module installer.
   folder=work/'app/content/plugins/otp';buffer=io.BytesIO()
   with zipfile.ZipFile(buffer,'w',zipfile.ZIP_DEFLATED) as package:
    for f in folder.rglob('*'):
     if f.is_file():package.writestr('otp/'+f.relative_to(folder).as_posix(),f.read_bytes())
   shutil.move(folder,work/'uninstalled-otp')
   r=admin({'action':'package.install','type':'plugin','current_password':PASSWORD},'plugins',{'package':('otp.zip',buffer.getvalue(),'application/zip')});ck('native installer accepts complete OTP addon',r.status_code==303)
   ck('activate OTP settings without enabling factor',admin({'action':'plugin.toggle','id':'otp'},'plugins').status_code==303 and 'factor' not in state()['owner'])
   ck('enrollment requires current password',admin({'op':'begin','current_password':'wrong'}).status_code==403)
   ck('enrollment starts after reauthentication',admin({'op':'begin','current_password':PASSWORD}).status_code==303)
   page=owner.get(base+'/admin.php?view=plugin-otp');uri=html.unescape(re.search(r'data-uri="([^"]+)"',page.text).group(1));secret=re.search(r'secret=([A-Z2-7]+)',uri).group(1)
   ck('private local QR with interoperable otpauth URI',uri.startswith('otpauth://totp/VoilaVoila%20CMS:') and 'digits=6' in uri and 'period=30' in uri and 'content/plugins/otp/vendor/qrcodegen.js' in page.text)
   ck('secret key file guarded and private',(work/'state/otp/key.php').read_bytes().startswith(b'<?php http_response_code(404); exit; ?>\n') and (work/'state/otp/key.php').stat().st_mode&0o077==0)
   ck('setup never enabled before a valid OTP','factor' not in state()['owner'] and admin({'op':'enable','otp':'000000','ack':'on','current_password':PASSWORD}).status_code==401)
   code=totp(secret);r=admin({'op':'enable','otp':code,'ack':'on','current_password':PASSWORD});ck('enrollment verifies a real authenticator code',r.status_code==303)
   page=owner.get(base+'/admin.php?view=plugin-otp');codes=html.unescape(re.search(r'<pre id="otp-recovery-codes">([^<]+)</pre>',page.text).group(1)).splitlines();ck('ten 128-bit recovery codes shown once',len(codes)==10 and all(re.fullmatch('[A-F0-9]{8}(?:-[A-F0-9]{8}){3}',x) for x in codes))
   ck('recovery list disappears on reload','otp-recovery-codes' not in owner.get(base+'/admin.php?view=plugin-otp').text)
   f=state()['owner']['factor'];ck('only encrypted secret and keyed recovery hashes in SQLite',secret not in json.dumps(f) and all(x.replace('-','') not in json.dumps(f) for x in codes))
   ck('old owner sessions revoked when OTP is enabled',other.get(base+'/admin.php',allow_redirects=False).status_code==303)
   ck('plugin cannot be deactivated while MFA is configured',admin({'action':'plugin.toggle','id':'otp'},'plugins').status_code==409)
   r=admin({'action':'backup.export','current_password':PASSWORD},'tools');data=json.loads(zipfile.ZipFile(io.BytesIO(r.content)).read('data.json'));ck('editorial backup excludes OTP secret hashes and key','owner' not in data and 'factor' not in json.dumps(data) and secret not in json.dumps(data) and 'otp' not in data['plugin_data'])
   r=login(other);ck('password alone produces pending challenge without admin session',r.status_code==303 and 'factor=1' in r.headers['Location'] and other.get(base+'/admin.php',allow_redirects=False).status_code==303)
   private=next(c for c in state()['contents'].values() if c['type']=='page');mutate('$vv->store->change(function(&$s){$s["contents"]["'+private['id']+'"]["status"]="draft";});')
   ck('pending factor cannot read a private preview',other.get(base+'/?preview='+private['id']).status_code==404)
   ck('enrollment OTP cannot be replayed for login',factor(code,other).status_code==401)
   ck('invalid OTP does not authenticate',factor('999999',other).status_code==401)
   ck('one recovery code authenticates after password',factor(codes[0],other).status_code==303 and other.get(base+'/admin.php').status_code==200 and len(state()['owner']['factor']['recovery'])==9)
   login(guest);ck('used recovery code cannot authenticate again',factor(codes[0],guest).status_code==401)
   # TOTP anti-replay works across independent pending sessions.
   mutate('$vv->store->change(function(&$s){$s["owner"]["factor"]["last_step"]=intdiv(time(),30)-2;$s["limits"]=[];});')
   fresh=totp(secret);ck('current six-digit code opens private session',factor(fresh,guest).status_code==303 and guest.get(base+'/admin.php').status_code==200)
   login(other);ck('TOTP replay refused across browser sessions',factor(fresh,other).status_code==401)
   # Native account recovery must still require factor verification.
   recover=Session();page=recover.get(base+'/login.php?mode=recovery');r=recover.post(base+'/login.php?mode=recovery',data={'csrf':csrf(page),'recovery':native_recovery,'password':PASSWORD,'password_confirm':PASSWORD},allow_redirects=False)
   ck('native recovery code cannot bypass OTP',r.status_code==303 and 'factor=1' in r.headers['Location'] and recover.get(base+'/admin.php',allow_redirects=False).status_code==303)
   ck('recovered owner can finish using an OTP recovery code',factor(codes[1],recover).status_code==303)
   owner=recover
   # Binding/timeout, missing key and missing extension all fail closed.
   login(other);mutate('$vv->store->change(function(&$s){$s["owner"]["epoch"]=bin2hex(random_bytes(16));$s["limits"]=[];});')
   ck('pending challenge bound to owner epoch',factor(codes[2],other).status_code==303 and other.get(base+'/admin.php',allow_redirects=False).status_code==303)
   keyfile=work/'state/otp/key.php';keyfile.rename(work/'saved-otp-key.php');login(other);r=factor(codes[2],other);ck('missing encryption key fails closed',r.status_code==503 and other.get(base+'/admin.php',allow_redirects=False).status_code==303);(work/'saved-otp-key.php').rename(keyfile)
   auth=work/'app/content/plugins/otp/Auth.php';auth.rename(auth.with_suffix('.saved'));r=login(other);ck('missing plugin does not bypass factor enforcement',r.status_code==503);auth.with_suffix('.saved').rename(auth)
   # Local safe mode retains enforcement although native plugins are disabled.
   mutate('$vv->store->change(function(&$s){$s["plugins"]=[];$s["limits"]=[];});');r=login(other);ck('safe mode preserves second-factor requirement','factor=1' in r.headers.get('Location',''))
   result=subprocess.run([a.php,str(work/'app/content/plugins/otp/bin/otp.php'),'disable','--confirm=DISABLE-OTP'],env={**os.environ,'VV_STORAGE_PATH':str(work/'state')},capture_output=True);ck('explicit local recovery disables MFA and revokes sessions',result.returncode==0 and 'factor' not in state()['owner'] and login(other).headers['Location'].startswith('/admin.php'))
   # RFC vectors are independent expected values, not implementation-generated fixtures.
   vectors=[(59,'94287082','46119246','90693936'),(1111111109,'07081804','68084774','25091201'),(1111111111,'14050471','67062674','99943326'),(1234567890,'89005924','91819424','93441116'),(2000000000,'69279037','90698825','38618901'),(20000000000,'65353130','77737706','47863826')]
   unit=work/'vectors.php';unit.write_text('<?php define("VV_ROOT",'+json.dumps(str(work/'app'))+');require VV_ROOT."/core/Store.php";require VV_ROOT."/content/plugins/otp/Totp.php";$v=json_decode('+json.dumps(json.dumps(vectors))+',true);$keys=["12345678901234567890","12345678901234567890123456789012","1234567890123456789012345678901234567890123456789012345678901234"];$out=[];foreach($v as $row){foreach(["sha1","sha256","sha512"] as $n=>$alg){$got=\\VV\\Authentication\\Totp::at(\\VV\\Authentication\\Totp::base32($keys[$n]),$row[0],8,$alg);if($got!==$row[$n+1])throw new Exception("RFC mismatch");$out[]=$alg."@".$row[0];}}echo json_encode($out);');result=json.loads(subprocess.check_output([a.php,str(unit)],text=True));checks.extend('RFC 6238 '+x for x in result)
 report={'suite':'OTP authentication and RFC vectors','checks':len(checks),'passed':checks,'php':subprocess.check_output([a.php,'-r','echo PHP_VERSION;'],text=True)}
 if a.report:a.report.parent.mkdir(parents=True,exist_ok=True);a.report.write_text(json.dumps(report,indent=2)+'\n')
 print('PASS',len(checks),'OTP checks')
if __name__=='__main__':main()
