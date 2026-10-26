#!/usr/bin/env python3
"""Run the supplied Chromium journey against temporary synthetic CMS data."""
import argparse
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
from run import SOURCE, CMS_VERSION, server
from http_client import Session
from release_120 import docx


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', default=os.environ.get('PHP_BIN', 'php'))
    parser.add_argument('--node', default='node')
    parser.add_argument('--mount', default='cms', help='Test the installation in a subdirectory; empty string tests root')
    parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    php, node = shutil.which(args.php), shutil.which(args.node)
    if not php or not node:
        parser.error('PHP and Node are required for browser development tests')
    with tempfile.TemporaryDirectory(prefix='vv-browser-') as temporary:
        work = Path(temporary)
        shutil.copytree(SOURCE, work / 'app', ignore=shutil.ignore_patterns('.git', 'storage', 'node_modules', '__pycache__', 'dist', 'build', 'test-results'))
        (work / 'report.docx').write_bytes(docx())
        (work / 'Ressources & tarif.csv').write_text('item,price\nExample,42\n', encoding='utf-8')
        with server(php, work, work / 'state', mount=args.mount) as env:
            base = env['VV_TEST_BASE']
            owner = Session()
            page = owner.get(base + '/install.php')
            token = re.search(r'name="csrf" value="([a-f0-9]+)"', page.text).group(1)
            key = (work / 'state/setup-key.php').read_text().split('\n', 1)[1]
            installed = owner.post(base + '/install.php', data={'csrf': token, 'setup_key': key,
                'name': 'Browser fixture', 'email': 'owner@example.test', 'password': 'Browser_validation_120!',
                'password_confirm': 'Browser_validation_120!'})
            if installed.status_code != 200:
                raise AssertionError('Browser fixture installation failed')
            page = owner.get(base + '/admin.php?view=plugins')
            token = re.search(r'name="csrf" value="([a-f0-9]+)"', page.text).group(1)
            activated = owner.post(base + '/admin.php?view=plugins', data={'csrf': token, 'action': 'plugin.toggle', 'id': 'comments'})
            if activated.status_code != 200:
                raise AssertionError('Browser comments activation failed')
            subprocess.run([node, str(SOURCE / 'tests/browser_120.cjs')], cwd=work,
                           env={**env, 'VV_BROWSER_WORK': str(work)}, timeout=240, check=True)
            report = json.loads((work / 'browser-report.json').read_text())
            report.update(cms_version=CMS_VERSION, mount=args.mount,
                          php=subprocess.check_output([php, '-r', 'echo PHP_VERSION;'], text=True))
        if args.report:
            args.report.parent.mkdir(parents=True, exist_ok=True)
            args.report.write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
        print('PASS', report['checks'], 'Chromium checks on extracted application; synthetic storage removed')


if __name__ == '__main__':
    main()
