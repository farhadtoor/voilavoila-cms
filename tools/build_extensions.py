#!/usr/bin/env python3
"""Build native addon ZIPs and an offline WordPress/WooCommerce input pack.
Large third-party inputs stay outside source Git history. No code is executed.
"""
import argparse,hashlib,json,re,stat,subprocess,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def digest(path):return hashlib.sha256(path.read_bytes()).hexdigest()
def package(path,files):
 if path.exists():raise FileExistsError('Refusing to overwrite '+str(path))
 with zipfile.ZipFile(path,'x',zipfile.ZIP_DEFLATED) as z:
  for name,data in sorted(files.items()):
   i=zipfile.ZipInfo(name,(2026,10,26,0,0,0));i.create_system=3;i.external_attr=(stat.S_IFREG|0o644)<<16;i.compress_type=zipfile.ZIP_DEFLATED;z.writestr(i,data)
 return path

def main():
 p=argparse.ArgumentParser(description=__doc__)
 for name in ['wordpress-zip','verification-bundle','woocommerce-zip','woocommerce-checksums','output']:p.add_argument('--'+name,required=True,type=Path)
 a=p.parse_args();out=a.output.absolute();out.mkdir(parents=True,exist_ok=True)
 if subprocess.check_output(['git','-C',str(ROOT),'status','--porcelain']).strip():p.error('Commit source before packaging')
 pin=(ROOT/'content/plugins/wp-runtime/Bundled.php').read_text();wp_sha=re.search(r"ZIP_SHA256='([a-f0-9]{64})'",pin).group(1);verify_sha=re.search(r"VERIFICATION_SHA256='([a-f0-9]{64})'",pin).group(1)
 if digest(a.wordpress_zip)!=wp_sha or digest(a.verification_bundle)!=verify_sha:p.error('Official offline inputs differ from pinned bytes')
 wc=json.loads(a.woocommerce_checksums.read_text());seen=set()
 if str(wc.get('version'))!='11.2.1' or wc.get('plugin')!='woocommerce':p.error('This bundle recipe pins WooCommerce 11.2.1')
 with zipfile.ZipFile(a.woocommerce_zip) as z:
  for i in z.infolist():
   if i.is_dir():continue
   if not i.filename.startswith('woocommerce/'):p.error('Invalid WooCommerce root')
   name=i.filename[len('woocommerce/'):];data=z.read(i)
   if name in seen or name not in wc['files'] or hashlib.sha256(data).hexdigest()!=wc['files'][name]['sha256']:p.error('WooCommerce manifest mismatch')
   seen.add(name)
 if seen!=wc['files'].keys():p.error('Incomplete official WooCommerce package')
 files={};addons=[]
 for id,name in [('wp-runtime','VoilaVoila-WP-Engine-0.2.0.zip'),('otp','VoilaVoila-OTP-1.0.0.zip')]:
  folder=ROOT/'content/plugins'/id;entries={id+'/'+f.relative_to(folder).as_posix():f.read_bytes() for f in folder.rglob('*') if f.is_file()};result=package(out/name,entries);addons.append({'name':name,'id':id,'sha256':digest(result),'files':len(entries)})
  if id=='wp-runtime':files['native-plugin/'+name]=result.read_bytes()
 files['private-inputs/wordpress-7.1.3.zip']=a.wordpress_zip.read_bytes();files['private-inputs/wordpress-verification.php']=a.verification_bundle.read_bytes();files['private-inputs/woocommerce-11.2.1.zip']=a.woocommerce_zip.read_bytes();files['private-inputs/woocommerce-checksums.json']=a.woocommerce_checksums.read_bytes()
 for f in [ROOT/'EXTENSIONS-ENGINE-OTP-FR-EN.md',ROOT/'docs/WP-RUNTIME.fr.md',ROOT/'docs/WP-RUNTIME.md']:
  files['documentation/'+f.name]=f.read_bytes()
 for f in (ROOT/'docs/examples').iterdir():
  if f.is_file():files['documentation/examples/'+f.name]=f.read_bytes()
 with zipfile.ZipFile(a.wordpress_zip) as z:files['licenses/WORDPRESS-LICENSE.txt']=z.read('wordpress/license.txt')
 with zipfile.ZipFile(a.woocommerce_zip) as z:files['licenses/WOOCOMMERCE-LICENSE.txt']=z.read('woocommerce/license.txt')
 files['THIRD-PARTY-NOTICES.txt']=b'WordPress 7.1.3 and WooCommerce 11.2.1 are redistributed as original complete official source ZIPs, under their GPL terms. Their original license and notices remain inside each ZIP and are also copied into licenses/. Native VoilaVoila integration code is MIT, with branding rights reserved as stated in its NOTICE. No WordPress or WooCommerce endorsement is claimed.\nSources: https://wordpress.org/ and https://wordpress.org/plugins/woocommerce/\n'
 files['START-HERE-FR-EN.md']=('''# WordPress Engine 0.2 — complete offline pack / pack hors ligne

FR : installez le petit ZIP de native-plugin/ dans le CMS. Placez private-inputs/ hors de la racine web ; ce sont de vrais paquets officiels WordPress et WooCommerce, pas des stubs. Configurez le pool privé et SQL selon documentation/WP-RUNTIME.fr.md, préparez WordPress, puis installez WooCommerce et activez le profil complet. Ne téléversez PAS ce gros pack comme module CMS. Le CMS natif fonctionne sans lui. Les versions tierces doivent être maintenues et votre paiement/hébergement qualifié.

EN: install the small ZIP in native-plugin/ into CMS. Place private-inputs/ outside webroot; these are genuine official WordPress/WooCommerce source packages. Configure the private pool and SQL using documentation/WP-RUNTIME.md, prepare WordPress, then install WooCommerce and enable full compatibility. Do NOT upload this entire large pack as a native module. Native CMS works independently. Maintain third-party versions and qualify actual hosting/payments.

Authenticator OTP is a separate plugin requiring the CMS 1.3.1 enforcement hook. It does not require WordPress.
''').encode()
 manifest={'format':1,'native_engine':'0.2.0','wordpress':{'version':'7.1.3','sha256':wp_sha,'source':'https://wordpress.org/wordpress-7.1.3.zip'},'woocommerce':{'version':wc['version'],'sha256':digest(a.woocommerce_zip),'verified_files':len(seen),'source':'https://downloads.wordpress.org/plugin/woocommerce.11.2.1.zip'},'source_commit':subprocess.check_output(['git','-C',str(ROOT),'rev-parse','HEAD'],text=True).strip()}
 files['BUNDLE-INFO.json']=(json.dumps(manifest,indent=2)+'\n').encode();files['SHA256SUMS.txt']=''.join(hashlib.sha256(data).hexdigest()+'  '+name+'\n' for name,data in sorted(files.items())).encode()
 heavy=package(out/'VoilaVoila-WP-Engine-0.2.0-Complete.zip',files)
 report={'addons':addons,'offline_pack':{'name':heavy.name,'sha256':digest(heavy),'bytes':heavy.stat().st_size},'third_party':manifest};(out/'EXTENSIONS-BUILD-INFO.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
if __name__=='__main__':main()
