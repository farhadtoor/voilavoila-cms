#!/usr/bin/env python3
"""Exercise code upgrade and code+private-state rollback from an explicit fixture."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys
import tempfile
import zipfile
from run import SOURCE, CMS_VERSION, server, read_state
from http_client import Session
sys.path.insert(0, str(SOURCE / 'tools'))
from release_archive import unsafe_source_path, extract_verified


def old_files(archive):
    files = {}
    with zipfile.ZipFile(archive) as z:
        infos = [i for i in z.infolist() if not i.is_dir()]
        assert len(infos) <= 4000 and sum(i.file_size for i in infos) <= 64 * 1024 * 1024
        wrapped = all(i.filename.startswith('voila-cms/') for i in infos)
        for entry in infos:
            name = entry.filename[len('voila-cms/'):] if wrapped else entry.filename
            assert not unsafe_source_path(name) or name.startswith('files/'), 'Private/unsafe fixture path: ' + name
            assert '..' not in Path(name).parts and not name.startswith('/') and '\\' not in name
            assert stat.S_IFMT(entry.external_attr >> 16) in {0, stat.S_IFREG}
            assert entry.file_size <= 8 * 1024 * 1024 and name not in files
            files[name] = z.read(entry)
    return files


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--previous-zip', type=Path, required=True)
    parser.add_argument('--previous-version', required=True)
    parser.add_argument('--php', default=os.environ.get('PHP_BIN', 'php'))
    parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    php = shutil.which(args.php)
    checks = []
    def ck(name, condition):
        if not condition:
            raise AssertionError(name)
        checks.append(name); print('PASS', name, flush=True)
    with tempfile.TemporaryDirectory(prefix='vv-upgrade-release-') as temporary:
        work = Path(temporary)
        extract_verified(old_files(args.previous_zip), work / 'app')
        custom = work / 'app/content/plugins/private-custom-note.txt'
        custom.write_text('CUSTOM FILE TO RETAIN')
        storage = work / 'state'
        owner = Session(); password = 'Upgrade_121_fixture!'
        with server(php, work, storage) as env:
            base = env['VV_TEST_BASE']; page = owner.get(base + '/install.php')
            token = re.search(r'name="csrf" value="([a-f0-9]+)"', page.text).group(1)
            key = (storage / 'setup-key.php').read_text().split('\n', 1)[1]
            result = owner.post(base + '/install.php', data={'csrf': token, 'setup_key': key, 'name': 'Existing site',
                'email': 'owner@example.test', 'password': password, 'password_confirm': password})
            ck('previous ' + args.previous_version + ' application installs', result.status_code == 200 and args.previous_version in result.text)
            command = "require 'app/core/bootstrap.php';$vv->store->change(function(&$s){$s['plugin_data']['atelier']=['note'=>'Existing private note'];$s['settings']['theme']='profil-graphite';});"
            subprocess.run([php, '-r', command], cwd=work, env=env, check=True)
        before = read_state(storage)
        shutil.copytree(storage, work / 'private-before')
        shutil.copytree(work / 'app', work / 'code-before')
        shutil.copytree(SOURCE, work / 'app', dirs_exist_ok=True,
            ignore=shutil.ignore_patterns('.git', 'storage', 'node_modules', '__pycache__', 'dist', 'build', 'test-results'))
        # Simulate removal of known correction copies; no other user file is touched.
        for original in (work / 'code-before/files').rglob('*') if (work / 'code-before/files').exists() else []:
            if original.is_file():
                relative = original.relative_to(work / 'code-before/files')
                active = work / 'code-before' / relative
                if active.is_file() and active.read_bytes() == original.read_bytes():
                    (work / 'app/files' / relative).unlink()
        with server(php, work, storage) as env:
            after = read_state(storage)
            ck('upgrade preserves owner credentials secret and recovery', all(before[k] == after[k] for k in ['owner', 'secret']))
            ck('upgrade preserves pages posts and revisions', before['contents'] == after['contents'])
            ck('upgrade preserves settings plugin data and activation choices', all(before[k] == after[k] for k in ['settings', 'plugin_data', 'plugins']))
            ck('upgrade preserves a custom extension file', custom.read_text() == 'CUSTOM FILE TO RETAIN')
            ck('upgrade does not create a new installation key', not (storage / 'setup-key.php').exists())
            browser = Session(); page = browser.get(env['VV_TEST_BASE'] + '/login.php')
            token = re.search(r'name="csrf" value="([a-f0-9]+)"', page.text).group(1)
            page = browser.post(env['VV_TEST_BASE'] + '/login.php', data={'csrf': token, 'password': password})
            ck('existing owner signs in to the new version', page.status_code == 200 and CMS_VERSION in page.text)
            ck('hosting preflight appears after the upgrade', 'hosting-check' in browser.get(env['VV_TEST_BASE'] + '/admin.php?view=tools').text)
            import sqlite3
            with sqlite3.connect(storage / 'cms.sqlite') as db:
                ck('upgrade keeps the same SQLite schema and integrity', db.execute('PRAGMA user_version').fetchone()[0] == 1 and db.execute('PRAGMA integrity_check').fetchone()[0] == 'ok')
            subprocess.run([php, '-r', "require 'app/core/bootstrap.php';$vv->store->change(function(&$s){$s['plugin_data']['atelier']['note']='After-upgrade change';});"], cwd=work, env=env, check=True)
        shutil.rmtree(work / 'app'); shutil.copytree(work / 'code-before', work / 'app')
        shutil.rmtree(storage); shutil.copytree(work / 'private-before', storage)
        with server(php, work, storage):
            restored = read_state(storage)
            ck('rollback restores both old code and exact compatible private state', all(restored[k] == before[k] for k in ['owner', 'secret', 'contents', 'settings', 'plugin_data', 'plugins']))
            ck('rollback removes post-upgrade changes and retains custom files', restored['plugin_data']['atelier']['note'] == 'Existing private note' and custom.read_text() == 'CUSTOM FILE TO RETAIN')
    summary = {'checks': len(checks), 'passed': checks, 'from_version': args.previous_version, 'to_version': CMS_VERSION,
        'previous_archive_sha256': hashlib.sha256(args.previous_zip.read_bytes()).hexdigest(),
        'runtime': subprocess.check_output([php, '-r', 'echo PHP_VERSION;'], text=True)}
    if args.report:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(json.dumps(summary, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print('PASS', len(checks), 'upgrade and rollback checks from', args.previous_version)


if __name__ == '__main__':
    main()
