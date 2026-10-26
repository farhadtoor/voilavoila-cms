#!/usr/bin/env python3
"""Build a clean installable ZIP from the current committed source (no upload)."""
import argparse
import hashlib
import datetime
import stat
from pathlib import Path
import re
import subprocess
import sys
import zipfile
from release_archive import GENERATED, ReleaseError, make_manifest, validate_files, read_archive

ROOT = Path(__file__).resolve().parents[1]


def git(*args):
    return subprocess.check_output(['git', '-C', str(ROOT), *args])


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--version', help='Defaults to the committed CMS version')
    parser.add_argument('--allow-template', action='store_true', help='Explicitly build unconfigured distribution template')
    parser.add_argument('--output', type=Path, help='New ZIP path; existing files are never overwritten')
    args = parser.parse_args()
    actual = re.search(r"define\('VV_VERSION',\s*'([^']+)'\)", (ROOT / 'core/bootstrap.php').read_text()).group(1)
    if args.version is None:
        args.version = actual
    if not re.fullmatch(r'\d+\.\d+\.\d+', args.version):
        parser.error('Use a numeric major.minor.patch version')
    if args.version != actual:
        parser.error('Requested version differs from VV_VERSION: ' + actual)
    if git('status', '--porcelain').strip():
        parser.error('Commit or remove pending source changes before packaging')
    subprocess.run([sys.executable, str(ROOT / 'tools/check_repo.py')] + (['--allow-template'] if args.allow_template else []), check=True)
    archive = args.output.resolve() if args.output else ROOT / 'dist' / ('VoilaVoila-CMS-' + args.version + '.zip')
    if archive.exists() or archive.with_suffix('.sha256').exists():
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
        # Read committed bytes, not a changing deployment directory.
        files[relative.as_posix()] = git('show', 'HEAD:' + relative.as_posix())
    for generated in GENERATED:
        files.pop(generated, None)
    try:
        validate_files(files)
    except ReleaseError as exc:
        parser.error(str(exc))
    commit = git('rev-parse', 'HEAD').decode().strip()
    import json
    files['BUILD-INFO.json'] = (json.dumps({'format': 1, 'version': actual, 'commit': commit}, sort_keys=True, indent=2) + '\n').encode()
    files['MANIFEST-SHA256.txt'] = make_manifest(files)
    archive.parent.mkdir(exist_ok=True)
    epoch = int(git('show', '-s', '--format=%ct', 'HEAD').strip())
    stamp = datetime.datetime.fromtimestamp(max(epoch, 315532800), datetime.timezone.utc)
    with zipfile.ZipFile(archive, 'x', compression=zipfile.ZIP_DEFLATED) as output:
        for name, body in sorted(files.items()):
            # Flat layout: copy the extracted contents directly to the site/repo.
            info = zipfile.ZipInfo(name, stamp.timetuple()[:6])
            info.compress_type = zipfile.ZIP_DEFLATED
            info.create_system = 3
            info.external_attr = (stat.S_IFREG | (0o755 if name == 'Lancer-Mac.command' else 0o644)) << 16
            output.writestr(info, body)
    checksum = archive.with_suffix('.sha256')
    checksum.write_text(hashlib.sha256(archive.read_bytes()).hexdigest() + '  ' + archive.name + '\n')
    read_archive(archive, actual, commit)
    print('Created locally:', archive, '\nChecksum:', checksum)
    print('No upload or release publication was performed.')


if __name__ == '__main__':
    main()
