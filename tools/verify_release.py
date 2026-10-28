#!/usr/bin/env python3
"""Verify a native source release against its SHA-256 manifest without extracting it."""
import argparse,hashlib,json,re,zipfile
from pathlib import Path,PurePosixPath

def verify(files):
    if 'MANIFEST-SHA256.txt' not in files or 'BUILD-INFO.json' not in files:raise ValueError('Missing release metadata')
    info=json.loads(files['BUILD-INFO.json'])
    if info.get('version')!='1.5.0' or info.get('private_data_included') is not False:raise ValueError('Unexpected release identity')
    manifest={}
    for line in files['MANIFEST-SHA256.txt'].decode().splitlines():
        m=re.fullmatch(r'([a-f0-9]{64})  (.+)',line)
        if not m or m[2] in manifest:raise ValueError('Malformed manifest')
        manifest[m[2]]=m[1]
    if set(manifest)!=set(files)-{'MANIFEST-SHA256.txt'}:raise ValueError('Manifest file set differs')
    for n,h in manifest.items():
        if hashlib.sha256(files[n]).hexdigest()!=h:raise ValueError('Hash differs: '+n)
    for n in files:
        parts=PurePosixPath(n).parts
        if n.startswith('/') or '..'in parts or 'storage'in parts or '\\'in n or '\x00'in n:raise ValueError('Unsafe/private path: '+n)
        if n in {'wp.php','wp-assets.php'} or n.startswith(('content/plugins/wp-runtime/','content/plugins/wp-porter/')):raise ValueError('Retired runtime code present')
    return {'version':info['version'],'files':len(files),'uncompressed_bytes':sum(map(len,files.values())),'verified':True}

if __name__=='__main__':
    a=argparse.ArgumentParser(description=__doc__);g=a.add_mutually_exclusive_group(required=True);g.add_argument('--zip',type=Path);g.add_argument('--directory',type=Path);args=a.parse_args()
    if args.zip:
        with zipfile.ZipFile(args.zip)as z:
            rows=z.infolist()
            if len(rows)>2000 or sum(r.file_size for r in rows)>67108864:raise ValueError('Release exceeds bounds')
            if len({r.filename.casefold()for r in rows})!=len(rows):raise ValueError('Duplicate paths')
            if any(r.flag_bits&1 or ((r.external_attr>>16)&0o170000)==0o120000 for r in rows):raise ValueError('Special entry')
            if z.testzip()is not None:raise ValueError('CRC error')
            files={r.filename:z.read(r)for r in rows if not r.is_dir()}
    else:
        names=[m.group(1)for line in(args.directory/'MANIFEST-SHA256.txt').read_text().splitlines()if(m:=re.fullmatch('[a-f0-9]{64}  (.+)',line))]
        files={n:(args.directory/n).read_bytes()for n in names};files['MANIFEST-SHA256.txt']=(args.directory/'MANIFEST-SHA256.txt').read_bytes()
    print(json.dumps(verify(files),indent=2))
