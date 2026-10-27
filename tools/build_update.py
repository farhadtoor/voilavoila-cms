#!/usr/bin/env python3
"""Build a local reviewed overlay between two verified release archives."""
import argparse,hashlib,json
from pathlib import Path
import zipfile
from release_archive import read_archive

def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--previous',type=Path,required=True);p.add_argument('--current',type=Path,required=True);p.add_argument('--output',type=Path,required=True);a=p.parse_args()
    if a.output.exists():p.error('Keep existing release assets immutable; select a new output path.')
    old,old_report=read_archive(a.previous);new,new_report=read_archive(a.current)
    if old_report['version']!='1.3.1' or new_report['version']!='1.4.0':p.error('This release overlay is for 1.3.1 to 1.4.0 only.')
    changed={name:data for name,data in new.items() if old.get(name)!=data};deleted=sorted(set(old)-set(new))
    info={'format':'voilavoila-cms-update','from':'1.3.1','to':'1.4.0','base_archive_sha256':hashlib.sha256(a.previous.read_bytes()).hexdigest(),'release_archive_sha256':hashlib.sha256(a.current.read_bytes()).hexdigest(),'source_commit':new_report['source_commit'],'changed_files':sorted(changed),'removed_source_files':deleted,'policy':'Reviewed SFTP overlay only. Retain private storage and custom modules. Review locally modified files. No executable updater or remote download.'}
    changed['UPDATE-INFO.json']=(json.dumps(info,ensure_ascii=False,indent=2)+'\n').encode()
    changed['UPDATE-MANIFEST-SHA256.txt']=''.join(hashlib.sha256(data).hexdigest()+'  '+name+'\n' for name,data in sorted(changed.items())).encode()
    a.output.parent.mkdir(parents=True,exist_ok=True)
    with zipfile.ZipFile(a.output,'x',zipfile.ZIP_DEFLATED) as z:
        for name,data in sorted(changed.items()):
            i=zipfile.ZipInfo(name,(2026,10,27,0,0,0));i.compress_type=zipfile.ZIP_DEFLATED;i.external_attr=0o100644<<16;z.writestr(i,data)
    a.output.with_suffix('.sha256').write_text(hashlib.sha256(a.output.read_bytes()).hexdigest()+'  '+a.output.name+'\n')
    print(json.dumps({'file':str(a.output),'files':len(changed),'removed_files':deleted,'bytes':a.output.stat().st_size},indent=2))

if __name__=='__main__':main()
