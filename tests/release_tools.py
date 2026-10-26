#!/usr/bin/env python3
"""End-to-end release builder, ZIP boundary and extraction-failure checks."""
import argparse
import copy
import hashlib
import json
from pathlib import Path
import shutil
import stat
import subprocess
import sys
import tempfile
import zipfile

SOURCE = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(SOURCE / 'tools'))
from release_archive import ReleaseError, make_manifest, read_archive, read_installed, extract_verified


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    checks = []
    def ck(name, condition):
        if not condition:
            raise AssertionError(name)
        checks.append(name)
        print('PASS', name, flush=True)
    with tempfile.TemporaryDirectory(prefix='vv-release-tools-') as temporary:
        work = Path(temporary)
        fixture = work / 'source'
        shutil.copytree(SOURCE, fixture, ignore=shutil.ignore_patterns('.git', 'storage', '__pycache__', 'node_modules', 'dist', 'build', 'test-results'))
        (fixture / 'storage').mkdir()
        for name in ['.htaccess', 'index.php']:
            shutil.copyfile(SOURCE / 'storage' / name, fixture / 'storage' / name)
        def git(*arguments):
            return subprocess.run(['git', '-c', 'commit.gpgsign=false', '-c', 'user.name=Release fixture',
                '-c', 'user.email=release@example.test', *arguments], cwd=fixture, check=True, capture_output=True)
        git('init', '-q'); git('add', '.'); git('commit', '-qm', 'Synthetic release fixture')
        commit = git('rev-parse', 'HEAD').stdout.decode().strip()
        import re
        version = re.search(r"define\('VV_VERSION',\s*'([^']+)'\)", (fixture / 'core/bootstrap.php').read_text()).group(1)
        def build(output, requested=version):
            return subprocess.run([sys.executable, str(fixture / 'tools/build_release.py'), '--version', requested,
                '--output', str(output)], cwd=fixture, text=True, capture_output=True)
        archive = work / 'release.zip'
        result = build(archive)
        ck('build committed application and verify it without PHP or private storage', result.returncode == 0)
        files, report = read_archive(archive, version, commit)
        ck('release uses a flat layout and a complete checked manifest', report['layout'] == 'application files at ZIP root' and report['source_commit'] == commit)
        ck('release contains only storage protection files', {n for n in files if n.startswith('storage/')} == {'storage/.htaccess', 'storage/index.php'})
        ck('release has no misplaced correction directory', not any(n.startswith('files/') for n in files))
        digest = hashlib.sha256(archive.read_bytes()).hexdigest()
        ck('published archive cannot be overwritten', build(archive).returncode != 0 and hashlib.sha256(archive.read_bytes()).hexdigest() == digest)
        second = work / 'same-commit.zip'
        ck('same committed source produces byte-identical archives', build(second).returncode == 0 and second.read_bytes() == archive.read_bytes())
        (fixture / 'README.md').write_text((fixture / 'README.md').read_text() + '\nUncommitted fixture\n')
        ck('builder refuses uncommitted source', build(work / 'dirty.zip').returncode != 0 and not (work / 'dirty.zip').exists())
        git('restore', 'README.md')
        ck('builder refuses a different requested version', build(work / 'wrong-version.zip', '99.0.0').returncode != 0)
        extracted = work / 'installed'
        extract_verified(files, extracted)
        ck('verified extraction retains the exact PHP and media entry bytes', all((extracted / name).read_bytes() == data for name, data in files.items()))
        ck('installed-code verification accepts the complete extracted application', read_installed(extracted)['source_commit'] == commit)
        (extracted / 'core/App.php').write_bytes(files['core/App.php'] + b'\nCHANGED')
        try:
            read_installed(extracted)
        except ReleaseError:
            ck('installed-code verification detects a partial or modified deployment', True)
        else:
            raise AssertionError('Modified installed PHP accepted')
        (extracted / 'core/App.php').write_bytes(files['core/App.php'])
        (extracted / 'personal-marker.txt').write_text('KEEP')
        try:
            extract_verified(files, extracted)
        except ReleaseError:
            ck('extraction refuses an existing site without modifying its files', (extracted / 'personal-marker.txt').read_text() == 'KEEP')
        else:
            raise AssertionError('Existing site unexpectedly overwritten')
        def package(name, payload, mode=None, duplicate=None):
            path = work / (hashlib.sha256(name.encode()).hexdigest()[:16] + '.zip')
            with zipfile.ZipFile(path, 'w', zipfile.ZIP_STORED) as z:
                for entry, data in payload.items():
                    item = zipfile.ZipInfo(entry)
                    item.create_system = 3
                    item.external_attr = (mode if mode and entry == 'probe.txt' else stat.S_IFREG | 0o644) << 16
                    z.writestr(item, data)
                if duplicate:
                    z.writestr(duplicate, b'duplicate')
            return path
        def reject(name, payload, **kwargs):
            # Recompute the manifest so path/metadata checks cannot rely on a
            # checksum error masking the scenario being exercised.
            payload = copy.deepcopy(payload)
            payload['MANIFEST-SHA256.txt'] = make_manifest(payload)
            path = package(name, payload, **kwargs)
            try:
                read_archive(path)
            except ReleaseError:
                ck(name, True)
            else:
                raise AssertionError('Accepted malformed release: ' + name)
        for path in ['../escape.php', '/absolute.php', 'bad\\path.php', 'drive:probe.php', 'storage/cms.sqlite', '.env', 'files/tests/probe.py', 'engine-config.php', 'wp-connector.php', 'wordpress-verification.php', 'wordpress/wp-load.php', 'wp-quarantine/index.php', 'backup.sql']:
            reject('reject unsafe path ' + path, {**files, path: b'blocked'})
        reject('reject duplicate filename under different case', files, duplicate='INDEX.PHP')
        reject('reject symlink entry', {**files, 'probe.txt': b'/outside'}, mode=stat.S_IFLNK | 0o777)
        incomplete = dict(files); incomplete.pop('core/Hosting.php')
        reject('reject a partial core before extraction', incomplete)
        incomplete130 = dict(files); incomplete130.pop('content/plugins/wp-runtime/engine/Gateway.template.txt')
        reject('reject missing 1.3 worker template', incomplete130)
        malformed = dict(files); malformed['BUILD-INFO.json'] = b'[]'
        reject('reject malformed build metadata', malformed)
        reject('reject an oversized source entry', {**files, 'large.txt': b'x' * (8 * 1024 * 1024 + 1)})
        corrupt = dict(files); corrupt['index.php'] += b'\nTAMPER'
        try:
            read_archive(package('bad-manifest', corrupt))
        except ReleaseError:
            ck('reject changed bytes against the original manifest', True)
        else:
            raise AssertionError('Manifest did not detect corruption')
        try:
            read_archive(archive, expected_commit='f' * 40)
        except ReleaseError:
            ck('reject archive from a different source commit', True)
        else:
            raise AssertionError('Wrong source commit accepted')
        wrapped = {'voila-cms/' + key: value for key, value in files.items()}
        ck('verifier also accepts a single legacy-style wrapper', read_archive(package('wrapped', wrapped))[0] == files)
        bad = fixture / 'files/core/bootstrap.php'; bad.parent.mkdir(parents=True); bad.write_bytes(files['core/bootstrap.php'])
        git('add', '.'); git('commit', '-qm', 'Misplaced correction fixture')
        ck('repository checks and builder reject committed duplicate core trees', build(work / 'misplaced.zip').returncode != 0 and not (work / 'misplaced.zip').exists())
    summary = {'checks': len(checks), 'passed': checks, 'scope': 'Isolated synthetic release archives; no upload or deployment'}
    if args.report:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(json.dumps(summary, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print('PASS', len(checks), 'release integrity and packaging checks')


if __name__ == '__main__':
    main()
