#!/usr/bin/env python3
"""Run isolated integration/migration checks with Python stdlib and PHP."""
import argparse
import contextlib
import copy
import hashlib
import json
import os
import re
from pathlib import Path
import shutil
import socket
import sqlite3
import subprocess
import sys
import tempfile
import time
import urllib.request
import zipfile

SOURCE = Path(__file__).resolve().parents[1]
CMS_VERSION = re.search(r"define\('VV_VERSION',\s*'([^']+)'\)", (SOURCE / 'core/bootstrap.php').read_text()).group(1)
GUARD = b'<?php http_response_code(404); exit; ?>\n'


def read_state(storage):
    with sqlite3.connect(storage / 'cms.sqlite') as db:
        db.row_factory = sqlite3.Row
        state = json.loads(db.execute('SELECT data FROM cms_state').fetchone()[0])
        state['contents'] = {}
        for row in db.execute('SELECT * FROM contents ORDER BY created_at,id'):
            c = dict(row)
            c.update(json.loads(c.pop('extra')))
            c['order'] = c.pop('sort_order')
            c['tags'] = json.loads(c['tags'])
            c['pinned'], c['menu'] = bool(c['pinned']), bool(c['menu'])
            c['revisions'] = []
            state['contents'][c['id']] = c
        for row in db.execute('SELECT * FROM revisions ORDER BY content_id,position'):
            state['contents'][row['content_id']]['revisions'].append(json.loads(row['data']))
        return state


def legacy_fixture(work):
    state = read_state(work / 'state')
    state['schema'], state['limits'] = 1, {}
    first = next(c for c in state['contents'].values() if c['type'] == 'post')
    revision = copy.deepcopy(first)
    revision.pop('revisions', None)
    revision['title_fr'] = 'Version antérieure à préserver'
    first['revisions'], first['version'] = [revision], first['version'] + 1
    for i, status in enumerate(['draft', 'private', 'trash', 'published']):
        c = copy.deepcopy(first)
        c.update(id=f'{i+101:024x}', slug='migration-' + status, status=status,
                 revisions=[], publish_at=4102444800 if status == 'published' else 1000000000)
        state['contents'][c['id']] = c
    storage = work / 'legacy-pristine'
    (storage / 'media').mkdir(parents=True)
    binaries = {}
    for mid, metadata in state['media'].items():
        binaries[mid] = (work / 'state/media' / metadata.pop('stored_name')).read_bytes()
        metadata.pop('extension', None)
        (storage / 'media' / (mid + '.php')).write_bytes(GUARD + binaries[mid])
    raw = json.dumps(state, ensure_ascii=False, separators=(',', ':')).encode()
    (storage / 'state.php').write_bytes(GUARD + raw)
    (work / 'migration-expected.json').write_text(json.dumps(state, ensure_ascii=False), encoding='utf-8')
    data = {'format': 'voilavoila-cms', 'schema': 1,
            **{key: state[key] for key in ['settings', 'contents', 'media', 'plugin_data']}}
    with zipfile.ZipFile(work / 'legacy-backup.zip', 'w', zipfile.ZIP_DEFLATED) as archive:
        archive.writestr('data.json', json.dumps(data, ensure_ascii=False))
        for mid, binary in binaries.items():
            archive.writestr('media/' + mid + '.bin', binary)
    shutil.copytree(storage, work / 'migration-state')


@contextlib.contextmanager
def server(php, work, storage, mount=''):
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    document_root = work / 'app'
    router = document_root / 'router.php'
    if mount:
        if not re.fullmatch(r'[a-z][a-z0-9-]{0,30}', mount):
            raise ValueError('Test mount must be one safe URL segment')
        document_root = work / 'web'
        mounted = document_root / mount
        shutil.copytree(work / 'app', mounted, ignore=shutil.ignore_patterns('node_modules', 'test-results', '__pycache__'))
        router = mounted / 'router.php'
    base = f'http://127.0.0.1:{port}' + ('/' + mount if mount else '')
    env = {**os.environ, 'VV_STORAGE_PATH': str(storage), 'VV_TEST_BASE': base}
    env.pop('VV_TRUSTED_PROXIES', None)
    with (work / ('php-' + storage.name + '.log')).open('w', encoding='utf-8') as log:
        # The CLI server ignores .user.ini. Match the documented CMS limits so
        # multipart requests reach the application, including its 8 MiB checks.
        process = subprocess.Popen([php, '-d', 'memory_limit=256M',
                                    '-d', 'upload_max_filesize=64M', '-d', 'post_max_size=70M',
                                    '-S', f'127.0.0.1:{port}', '-t', str(document_root),
                                    str(router)], cwd=work, env=env, stdout=log, stderr=log)
        try:
            for _ in range(100):
                if process.poll() is not None:
                    raise RuntimeError('PHP server stopped. Check PHP extensions.')
                try:
                    urllib.request.urlopen(base + '/install.php', timeout=2).close()
                    break
                except Exception:
                    time.sleep(0.05)
            else:
                raise RuntimeError('Local PHP server did not become ready.')
            yield env
        except BaseException:
            # Temporary fixtures are deleted after the run. Keep failure evidence
            # in CI output; these logs contain only this run's synthetic data.
            for path in [work / ('php-' + storage.name + '.log'), storage / 'logs/error.txt']:
                if path.is_file():
                    tail = '\n'.join(path.read_text(encoding='utf-8', errors='replace').splitlines()[-30:])[-6000:]
                    print(f'\n--- Synthetic test log: {path.name} ---\n{tail}', file=sys.stderr, flush=True)
            raise
        finally:
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', default=os.environ.get('PHP_BIN', 'php'))
    parser.add_argument('--report', type=Path, help='Optional JSON summary outside private test storage')
    args = parser.parse_args()
    php = shutil.which(args.php)
    if not php:
        parser.error('PHP not found. Use --php /absolute/path/to/php')
    version = subprocess.check_output([php, '-r', 'echo PHP_VERSION;'], text=True)
    preflight = "foreach(['dom','mbstring','fileinfo','gd','zip','pdo_sqlite'] as $e) if(!extension_loaded($e)){fwrite(STDERR,'Missing extension: '.$e.PHP_EOL);exit(1);} if(!function_exists('imagejpeg')||!function_exists('imagewebp')){fwrite(STDERR,'GD JPEG and WebP support required for tests'.PHP_EOL);exit(1);}"
    subprocess.run([php, '-r', preflight], check=True)
    with tempfile.TemporaryDirectory(prefix='vv-cms-tests-') as temp:
        work = Path(temp)
        shutil.copytree(SOURCE, work / 'app', ignore=shutil.ignore_patterns('.git', 'storage', '__pycache__', '.venv', 'node_modules', 'dist', 'build', 'test-results'))
        (work / 'fixtures').mkdir()
        fixture_code = "$i=imagecreatetruecolor(200,120);$c=imagecolorallocate($i,198,164,98);imagefill($i,0,0,$c);imagepng($i,'fixtures/image.png');imagejpeg($i,'fixtures/image.jpeg');imagewebp($i,'fixtures/image.webp');unset($i);"
        subprocess.run([php, '-r', fixture_code], cwd=work, check=True)
        env = {**os.environ, 'VV_TEST_PHP': php, 'VV_TEST_PHP_VERSION': 'PHP ' + version, 'VV_TEST_CMS_VERSION': CMS_VERSION,
               'PYTHONIOENCODING': 'utf-8', 'PYTHONDONTWRITEBYTECODE': '1'}
        with server(php, work, work / 'state') as server_env:
            subprocess.run([sys.executable, str(SOURCE / 'tests/integration.py')], cwd=work,
                           env={**env, **server_env}, check=True)
        legacy_fixture(work)
        with server(php, work, work / 'migration-state') as server_env:
            subprocess.run([sys.executable, str(SOURCE / 'tests/features.py')], cwd=work,
                           env={**env, **server_env}, check=True)
        subprocess.run([sys.executable, str(SOURCE / 'tests/migration_failure.py')], cwd=work,
                       env=env, check=True)
        subprocess.run([sys.executable, str(SOURCE / 'tests/release_120.py'), '--php', php,
                        '--report', str(work / 'release-120-results.json')], env=env, check=True)
        subprocess.run([sys.executable, str(SOURCE / 'tests/maintenance_121.py'), '--php', php,
                        '--report', str(work / 'maintenance-results.json')], env=env, check=True)
        reports = [json.loads((work / name).read_text(encoding='utf-8')) for name in
                   ['integration-results.json', 'features-results.json', 'migration-failure-results.json', 'release-120-results.json', 'maintenance-results.json']]
        summary = {'runtime': 'PHP ' + version, 'checks': sum(r['checks'] for r in reports),
                   'suites': reports, 'storage': 'Temporary synthetic data; removed after run'}
        if args.report:
            args.report.resolve().parent.mkdir(parents=True, exist_ok=True)
            args.report.write_text(json.dumps(summary, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
        print('PASS:', summary['checks'], 'isolated HTTP/migration checks on PHP', version)


if __name__ == '__main__':
    main()
