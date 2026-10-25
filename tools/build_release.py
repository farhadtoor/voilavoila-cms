#!/usr/bin/env python3
"""Build a clean installable ZIP from the current committed source (no upload)."""
import argparse
import hashlib
from pathlib import Path
import re
import subprocess
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]


def git(*args):
    return subprocess.check_output(['git', '-C', str(ROOT), *args])


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--version', required=True)
    args = parser.parse_args()
    if not re.fullmatch(r'\d+\.\d+\.\d+', args.version):
        parser.error('Use a numeric major.minor.patch version')
    actual = re.search(r"define\('VV_VERSION',\s*'([^']+)'\)", (ROOT / 'core/bootstrap.php').read_text()).group(1)
    if args.version != actual:
        parser.error('Requested version differs from VV_VERSION: ' + actual)
    if git('status', '--porcelain').strip():
        parser.error('Commit or remove pending source changes before packaging')
    subprocess.run([sys.executable, str(ROOT / 'tools/check_repo.py')], check=True)
    archive = ROOT / 'dist' / ('VoilaVoila-CMS-' + args.version + '.zip')
    if archive.exists():
        parser.error('Output already exists. Keep published archives immutable; choose a new output/version.')
    files = {}
    for raw in git('ls-files', '-z').split(b'\0'):
        if not raw:
            continue
        relative = Path(raw.decode('utf-8'))
        path = ROOT / relative
        if path.is_symlink() or not path.is_file():
            parser.error('Invalid tracked entry: ' + str(relative))
        if relative.parts[0] in {'.git', 'dist', 'build', 'test-results'}:
            parser.error('Unexpected tracked generated entry: ' + str(relative))
        files[relative.as_posix()] = path.read_bytes()
    files.pop('MANIFEST-SHA256.txt', None)
    files['MANIFEST-SHA256.txt'] = ''.join(hashlib.sha256(body).hexdigest() + '  ' + name + '\n'
                                         for name, body in sorted(files.items())).encode()
    archive.parent.mkdir(exist_ok=True)
    with zipfile.ZipFile(archive, 'x', compression=zipfile.ZIP_DEFLATED) as output:
        for name, body in sorted(files.items()):
            output.writestr('voila-cms/' + name, body)
    checksum = archive.with_suffix('.sha256')
    checksum.write_text(hashlib.sha256(archive.read_bytes()).hexdigest() + '  ' + archive.name + '\n')
    print('Created locally:', archive, '\nChecksum:', checksum)
    print('No upload or release publication was performed.')


if __name__ == '__main__':
    main()
