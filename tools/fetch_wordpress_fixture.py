#!/usr/bin/env python3
"""Fetch the current official en_US WordPress ZIP and verify every official file.
Writes an operator-owned guarded verification bundle; never publish these inputs.
"""
import argparse, hashlib, json, re, stat, time, urllib.parse, urllib.request, zipfile
from pathlib import Path
GUARD=b'<?php http_response_code(404); exit; ?>\n'
def fetch(url,limit):
    if urllib.parse.urlparse(url).hostname not in {'api.wordpress.org','wordpress.org','downloads.wordpress.org'} or not url.startswith('https://'):raise ValueError('Untrusted official download URL')
    with urllib.request.urlopen(url,timeout=60) as response:
        if not response.url.startswith('https://') or urllib.parse.urlparse(response.url).hostname not in {'api.wordpress.org','wordpress.org','downloads.wordpress.org'}:raise ValueError('Untrusted redirect')
        data=response.read(limit+1)
        if len(data)>limit:raise ValueError('Official response exceeds bound')
        return data

def main():
    ap=argparse.ArgumentParser(description=__doc__);ap.add_argument('--directory',type=Path,required=True);a=ap.parse_args();a.directory.mkdir(parents=True,exist_ok=True,mode=0o700)
    source='https://api.wordpress.org/core/version-check/1.7/?locale=en_US'
    offers=json.loads(fetch(source,1048576))['offers'];offer=next(x for x in offers if x.get('response')=='upgrade' and re.fullmatch(r'\d+\.\d+(?:\.\d+)?',x.get('current','')))
    version=offer['current'];url=offer['download'];archive=a.directory/('wordpress-'+version+'.zip')
    if archive.exists():raise FileExistsError('Choose a fresh directory; fixtures are immutable')
    data=fetch(url,47185920);archive.write_bytes(data);archive.chmod(0o600)
    checksum_url='https://api.wordpress.org/core/checksums/1.0/?'+urllib.parse.urlencode({'version':version,'locale':'en_US'})
    checksums=json.loads(fetch(checksum_url,1048576))['checksums'];seen=set();total=0
    if not isinstance(checksums,dict) or len(checksums)>8000:raise ValueError('Invalid official manifest')
    with zipfile.ZipFile(archive) as z:
        if len(z.infolist())>8000:raise ValueError('Too many entries')
        names=set()
        for e in z.infolist():
            name=e.filename
            if name.casefold() in names or not name.startswith('wordpress/') or any(p in {'.','..',''} for p in name.rstrip('/').split('/')) or '\\' in name or re.search(r'[\x00-\x1f:]',name):raise ValueError('Unsafe official ZIP path')
            names.add(name.casefold());kind=stat.S_IFMT(e.external_attr>>16)
            if kind not in {0,stat.S_IFDIR if e.is_dir() else stat.S_IFREG} or e.flag_bits&1:raise ValueError('Unsafe ZIP type')
            if e.is_dir():continue
            total+=e.file_size
            if e.file_size>16777216 or total>167772160:raise ValueError('ZIP size bound')
            relative=name[10:];contents=z.read(e)
            if relative not in checksums or hashlib.md5(contents).hexdigest()!=checksums[relative]:raise ValueError('Official checksum mismatch: '+relative)
            seen.add(relative)
    if seen!=checksums.keys():raise ValueError('Incomplete official ZIP')
    bundle={'format':1,'version':version,'verified_at':int(time.time()),'zip_sha256':hashlib.sha256(data).hexdigest(),'source':source,'manifest_source':checksum_url,'checksums':checksums}
    private=a.directory/'wordpress-verification.php';private.write_bytes(GUARD+json.dumps(bundle,separators=(',',':')).encode());private.chmod(0o600)
    print(json.dumps({'version':version,'zip':str(archive.absolute()),'bundle':str(private.absolute()),'sha256':bundle['zip_sha256'],'verified_files':len(seen)}))
if __name__=='__main__':main()
