#!/usr/bin/env python3
"""Hosting preflight, residual-copy denial and subdirectory HTTP regression."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
from run import SOURCE, CMS_VERSION, server, read_state
from http_client import Session


def fingerprint(directory):
    return {p.relative_to(directory).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest()
            for p in directory.rglob('*') if p.is_file()}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', default=os.environ.get('PHP_BIN', 'php'))
    parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    checks = []
    def ck(name, condition):
        if not condition:
            raise AssertionError(name)
        checks.append(name)
        print('PASS', name, flush=True)
    php = shutil.which(args.php)
    with tempfile.TemporaryDirectory(prefix='vv-maintenance-') as temporary:
        root = Path(temporary)
        empty = root / 'not-created'
        env = {**os.environ, 'VV_STORAGE_PATH': str(empty)}
        report = subprocess.run([php, str(SOURCE / 'bin/console.php'), 'doctor', '--json'], env=env,
                                text=True, capture_output=True, check=True)
        doctor = json.loads(report.stdout)
        ck('doctor works before installation without creating storage', not empty.exists() and doctor['cms_version'] == CMS_VERSION)
        ck('doctor reports mandatory modules and explicitly unverified HTTP privacy', doctor['passed'] and any(c['id'] == 'http_private' and c['status'] == 'manual' for c in doctor['checks']))
        ck('doctor output omits private absolute storage path', str(empty) not in report.stdout)
        for mount in ['', 'cms']:
            label = 'root' if not mount else 'subdirectory'
            work = root / label
            shutil.copytree(SOURCE, work / 'app', ignore=shutil.ignore_patterns('.git', 'storage', 'node_modules', '__pycache__', 'dist', 'build', 'test-results'))
            for relative in ['files/core/bootstrap.php', 'files/tests/probe.txt', 'storage/cms.sqlite', 'storage/logs/error.txt']:
                target = work / 'app' / relative
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_text('PRIVATE_RESIDUAL_MARKER', encoding='utf-8')
            with server(php, work, work / 'state', mount=mount) as values:
                base = values['VV_TEST_BASE']
                owner, guest = Session(), Session()
                install = owner.get(base + '/install.php')
                token = re.search(r'name="csrf" value="([a-f0-9]+)"', install.text).group(1)
                key = (work / 'state/setup-key.php').read_text().split('\n', 1)[1]
                result = owner.post(base + '/install.php', data={'csrf': token, 'setup_key': key, 'name': 'Hosting fixture',
                    'email': 'owner@example.test', 'password': 'Hosting_fixture_121!', 'password_confirm': 'Hosting_fixture_121!'})
                ck(label + ' installation preserves the expected mount and version', result.status_code == 200 and CMS_VERSION in result.text and '/admin.php' in result.text)
                ck(label + ' public index and local CSS work', guest.get(base + '/index.php').status_code == 200 and guest.get(base + '/assets/site.css').status_code == 200)
                ck(label + ' hosting panel is owner-only', 'hosting-check' in owner.get(base + '/admin.php?view=tools').text and guest.get(base + '/admin.php?view=tools', allow_redirects=False).status_code == 303)
                for relative in ['files/core/bootstrap.php', 'files/tests/probe.txt', 'core/bootstrap.php', 'storage/cms.sqlite', 'storage/logs/error.txt']:
                    response = guest.get(base + '/' + relative)
                    ck(label + ' denies ' + relative, response.status_code in [403, 404] and 'PRIVATE_RESIDUAL_MARKER' not in response.text)
            before = fingerprint(work / 'state')
            result = subprocess.run([php, str(work / 'app/bin/console.php'), 'doctor', '--json'],
                                    env={**os.environ, 'VV_STORAGE_PATH': str(work / 'state')}, text=True, capture_output=True, check=True)
            ck(label + ' doctor leaves database sessions media and logs byte-identical', fingerprint(work / 'state') == before and json.loads(result.stdout)['passed'])
            ck(label + ' diagnostic contains no owner identity or password', 'owner@example.test' not in result.stdout and 'Hosting_fixture_121!' not in result.stdout)
    summary = {'checks': len(checks), 'passed': checks, 'cms_version': CMS_VERSION,
               'runtime': subprocess.check_output([php, '-r', 'echo PHP_VERSION;'], text=True)}
    if args.report:
        args.report.write_text(json.dumps(summary, indent=2) + '\n', encoding='utf-8')
    print('PASS', len(checks), 'hosting and mount checks')


if __name__ == '__main__':
    main()
