#!/usr/bin/env python3
"""Build immutable 1.5.0 archives from reviewed local source, excluding runtime/private data."""
import argparse,hashlib,json,os,zipfile
from pathlib import Path

def digest(data):return hashlib.sha256(data).hexdigest()

def archive(path,files):
    path.parent.mkdir(parents=True,exist_ok=True)
    tmp=path.with_suffix(path.suffix+'.partial')
    with zipfile.ZipFile(tmp,'w',zipfile.ZIP_DEFLATED,compresslevel=6)as z:
        for name,data in sorted(files.items()):
            i=zipfile.ZipInfo(name,(2026,10,28,12,0,0));i.external_attr=0o100644<<16;i.compress_type=zipfile.ZIP_DEFLATED;z.writestr(i,data)
    with zipfile.ZipFile(tmp)as z:
        if z.testzip()is not None:raise ValueError('Archive failed CRC check')
    os.replace(tmp,path)
    return {'name':path.name,'files':len(files),'bytes':path.stat().st_size,'sha256':digest(path.read_bytes())}

def build(source,output,previous=None,updater=None):
    files={}
    for p in sorted(source.rglob('*')):
        if not p.is_file():continue
        n=p.relative_to(source).as_posix();parts=p.relative_to(source).parts
        if any(s in {'.git','node_modules','__pycache__','storage'}for s in parts)or any(s.startswith('.DS_')for s in parts):continue
        if n.startswith(('content/plugins/test-native/','content/plugins/wp-runtime/','content/plugins/wp-porter/')):continue
        if n in {'BUILD-INFO.json','MANIFEST-SHA256.txt'}or n.endswith(('.pyc','.partial')):continue
        if p.is_symlink():raise ValueError('Symlink in source: '+n)
        files[n]=p.read_bytes()
    if b"define('VV_VERSION', '1.5.0')"not in files['core/bootstrap.php']:raise ValueError('Wrong source version')
    source_hash=digest(''.join(n+' '+digest(b)+'\n'for n,b in files.items()).encode())
    files['BUILD-INFO.json']=(json.dumps({'version':'1.5.0','build_mode':'reviewed-local-source','source_sha256':source_hash,'private_data_included':False},indent=2)+'\n').encode()
    files['MANIFEST-SHA256.txt']=''.join(digest(b)+'  '+n+'\n'for n,b in sorted(files.items())).encode()
    reports=[archive(output/'VoilaVoila-CMS-1.5.0.zip',files)]
    if previous:
        with zipfile.ZipFile(previous)as z:old={n:z.read(n)for n in z.namelist()if not n.endswith('/')}
        changes={n:b for n,b in files.items()if old.get(n)!=b};removed=sorted(set(old)-set(files))
        info={'format':'voilavoila-cms-update','from':'1.4.0','to':'1.5.0','previous_sha256':digest(previous.read_bytes()),'release_sha256':reports[0]['sha256'],'files':{n:digest(b)for n,b in changes.items()},'removed':removed,'policy':'Preserve private storage and custom native files. Review hosting configuration. Retired WordPress code is included in the full private upgrade backup before removal.'}
        payload={'payload/'+n:b for n,b in changes.items()};payload['UPDATE-INFO.json']=(json.dumps(info,ensure_ascii=False,indent=2)+'\n').encode();payload['APPLY-UPDATE.php']=updater.read_bytes();payload['UPGRADE-1.5.0-FR-EN.md']=files['UPGRADE-1.5.0-FR-EN.md']
        payload['UPDATE-MANIFEST-SHA256.txt']=''.join(digest(b)+'  '+n+'\n'for n,b in sorted(payload.items())).encode()
        reports.append(archive(output/'VoilaVoila-CMS-1.4.0-to-1.5.0-Update.zip',payload))
    return reports

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--source',type=Path,default=Path(__file__).resolve().parents[1]);p.add_argument('--output',type=Path,required=True);p.add_argument('--previous',type=Path);p.add_argument('--updater',type=Path);a=p.parse_args()
    if a.previous and not a.updater:a.updater=a.source/'tools/update_140_150.php'
    print(json.dumps(build(a.source.resolve(),a.output.resolve(),a.previous,a.updater),indent=2))
