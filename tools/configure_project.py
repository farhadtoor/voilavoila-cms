#!/usr/bin/env python3
"""Personalize GitHub documentation locally; never contacts GitHub or commits."""
import argparse, re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--github',required=True,help='OWNER/REPOSITORY');p.add_argument('--maintainer',required=True,help='GitHub login without @');p.add_argument('--contact',required=True,help='Working private-report contact or URL');a=p.parse_args()
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9-]{0,38}/[A-Za-z0-9_.-]{1,100}',a.github):p.error('Use OWNER/REPOSITORY')
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9-]{0,38}',a.maintainer):p.error('Invalid maintainer login')
    if len(a.contact)>300 or re.search(r'[\x00-\x1f`<>]',a.contact) or not (a.contact.startswith('https://') or re.fullmatch(r'[^@\s]+@[^@\s]+\.[^@\s]+',a.contact)):p.error('Use a real HTTPS contact URL or email')
    owner,repo=a.github.split('/');values={'GITHUB_OWNER':owner,'GITHUB_REPOSITORY':repo,'GITHUB_MAINTAINER':a.maintainer,'MAINTAINER_CONTACT':a.contact};changed=[]
    for path in ROOT.rglob('*'):
        rel=path.relative_to(ROOT)
        if not path.is_file() or any(part in {'.git','storage','dist','build','node_modules','__pycache__'} for part in rel.parts) or path.is_symlink():continue
        if path.suffix not in {'.md','.yml','.yaml'} and path.name not in {'CODEOWNERS','NOTICE'}:continue
        s=path.read_text();out=s
        for old,new in values.items():out=out.replace(old,new)
        if s!=out:path.write_text(out);changed.append(str(rel))
    print('Configured',len(changed),'documentation/policy files for',a.github)
    print('No remote, commit, message or publication was created.')
if __name__=='__main__':main()
