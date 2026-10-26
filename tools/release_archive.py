"""Bounded validation and extraction of CMS release archives (Python stdlib)."""
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import stat
import tempfile
import zipfile

MAX_FILES = 4000
MAX_FILE_BYTES = 8 * 1024 * 1024
MAX_TOTAL_BYTES = 64 * 1024 * 1024
GENERATED = {'MANIFEST-SHA256.txt', 'BUILD-INFO.json'}
REQUIRED = {'index.php', 'admin.php', 'install.php', 'login.php', 'media.php', 'router.php',
            '.htaccess', '.user.ini', 'core/bootstrap.php', 'core/App.php', 'core/Store.php',
            'core/Security.php', 'core/Media.php', 'core/Logger.php', 'tests/run.py',
            'tools/check_repo.py', 'content/themes/profil-or/index.php',
            'content/themes/profil-or/cms.json', 'storage/.htaccess', 'storage/index.php'}


class ReleaseError(ValueError):
    pass


def version_of(files):
    try:
        source = files['core/bootstrap.php'].decode('utf-8')
    except (KeyError, UnicodeError) as exc:
        raise ReleaseError('Missing or invalid core/bootstrap.php') from exc
    found = re.search(r"define\('VV_VERSION',\s*'(\d+\.\d+\.\d+)'\)", source)
    if not found:
        raise ReleaseError('Numeric VV_VERSION not found')
    return found.group(1)


def unsafe_source_path(name):
    path = PurePosixPath(name)
    if not name or name.startswith('/') or '\\' in name or re.search(r'[\x00-\x1f\x7f:]', name):
        return True
    if any(part in {'', '.', '..'} for part in name.split('/')):
        return True
    if any(part in {'.git', '.venv', 'node_modules', '__pycache__'} for part in path.parts):
        return True
    if path.parts[0] in {'wordpress','wp-quarantine'} or path.name in {'engine-config.php','wp-connector.php','wordpress-verification.php'} or path.suffix in {'.sql'} or name.endswith('.sql.gz') or path.parts[0].startswith('wordpress-') and path.suffix=='.zip':
        return True
    if path.parts[0] in {'dist', 'build', 'test-results', 'playwright-report', 'files'}:
        return True
    if path.parts[0] == 'storage' and name not in {'storage/.htaccess', 'storage/index.php'}:
        return True
    if path.name.startswith('.env') and path.name != '.env.example':
        return True
    return bool(re.search(r'\.(?:sqlite(?:3)?|db)(?:-|$)', path.name))


def validate_files(files):
    missing = sorted(REQUIRED - files.keys())
    if missing:
        raise ReleaseError('Missing release files: ' + ', '.join(missing))
    for name, data in files.items():
        if unsafe_source_path(name):
            raise ReleaseError('Private or unsafe release path: ' + name)
        if name.endswith('/core/bootstrap.php') and name != 'core/bootstrap.php':
            raise ReleaseError('Misplaced duplicate CMS tree: ' + name)
        if len(data) > MAX_FILE_BYTES:
            raise ReleaseError('Release entry too large: ' + name)
    if len(files) > MAX_FILES or sum(map(len, files.values())) > MAX_TOTAL_BYTES:
        raise ReleaseError('Release exceeds bounded size')
    if tuple(map(int, version_of(files).split('.'))) >= (1,3,0):
        required_130={'wp.php','wp-assets.php','assets/voilavoila-logo.png','assets/france.svg','assets/wp-bridge.js','content/plugins/forms/Forms.php','content/plugins/forms/Admin.php','content/plugins/forms/plugin.php','content/plugins/forms/cms.json','content/plugins/wp-runtime/Runtime.php','content/plugins/wp-runtime/FastCGI.php','content/plugins/wp-runtime/plugin.php','content/plugins/wp-runtime/cms.json'}
        required_130.update('content/plugins/wp-runtime/engine/'+n+'.template.txt' for n in ['Gateway','Bridge','Authorize','CLI','Console','wp-config'])
        if required_130-files.keys(): raise ReleaseError('Missing 1.3 release files: '+', '.join(sorted(required_130-files.keys())))
    if tuple(map(int, version_of(files).split('.'))) >= (1,3,1):
        required_131={'core/SecondFactor.php','content/plugins/otp/Auth.php','content/plugins/otp/Totp.php','content/plugins/otp/Admin.php','content/plugins/otp/cms.json','content/plugins/otp/plugin.php','content/plugins/otp/otp.js','content/plugins/otp/vendor/qrcodegen.js','content/plugins/wp-runtime/Bundled.php','content/plugins/wp-runtime/engine/Sync.template.txt'}
        if required_131-files.keys(): raise ReleaseError('Missing authentication/engine release files: '+', '.join(sorted(required_131-files.keys())))
    # A partial copy of the core must fail before an installation is attempted.
    bootstrap = files['core/bootstrap.php'].decode('utf-8')
    for dependency in re.findall(r"require\s+__DIR__\s*\.\s*'/([^']+\.php)'", bootstrap):
        if 'core/' + dependency not in files:
            raise ReleaseError('Missing bootstrap dependency: core/' + dependency)
    return version_of(files)


def make_manifest(files):
    return ''.join(hashlib.sha256(data).hexdigest() + '  ' + name + '\n'
                   for name, data in sorted(files.items()) if name != 'MANIFEST-SHA256.txt').encode()


def metadata_of(files, actual, expected_commit=None):
    try:
        info = json.loads(files['BUILD-INFO.json'])
        if not isinstance(info, dict) or info.get('format') != 1 or info.get('version') != actual or not isinstance(info.get('commit'), str) or not re.fullmatch(r'[a-f0-9]{40,64}', info['commit']):
            raise ReleaseError('Invalid build metadata')
    except (KeyError, ValueError, TypeError) as exc:
        raise ReleaseError('Missing or invalid BUILD-INFO.json') from exc
    if expected_commit is not None and info['commit'] != expected_commit:
        raise ReleaseError('Unexpected source commit')
    return info


def read_installed(directory, expected_version=None, expected_commit=None):
    root = Path(directory).resolve(strict=True)
    manifest = root / 'MANIFEST-SHA256.txt'
    if manifest.is_symlink() or not manifest.is_file() or manifest.stat().st_size > 1024 * 1024:
        raise ReleaseError('Missing or invalid installed manifest')
    files = {'MANIFEST-SHA256.txt': manifest.read_bytes()}
    seen = set()
    total = 0
    try:
        lines = files['MANIFEST-SHA256.txt'].decode('utf-8').splitlines()
    except UnicodeError as exc:
        raise ReleaseError('Invalid manifest encoding') from exc
    if len(lines) > MAX_FILES:
        raise ReleaseError('Too many installed files')
    for line in lines:
        found = re.fullmatch(r'([a-f0-9]{64})  (.+)', line)
        if not found:
            raise ReleaseError('Malformed manifest line')
        digest, name = found.groups()
        if unsafe_source_path(name) or name.casefold() in seen or name == 'MANIFEST-SHA256.txt':
            raise ReleaseError('Unsafe or duplicate installed manifest path')
        seen.add(name.casefold())
        path = root / name
        if any(parent.is_symlink() for parent in [path, *path.parents] if parent != root and root in parent.parents):
            raise ReleaseError('Installed symlink: ' + name)
        if not path.is_file() or path.stat().st_size > MAX_FILE_BYTES:
            raise ReleaseError('Missing or oversized installed file: ' + name)
        data = path.read_bytes(); total += len(data)
        if total > MAX_TOTAL_BYTES or hashlib.sha256(data).hexdigest() != digest:
            raise ReleaseError('Changed installed file: ' + name)
        files[name] = data
    if (root / 'files').is_symlink() or (root / 'files').exists() and any(p.is_file() or p.is_symlink() for p in (root / 'files').rglob('*')):
        raise ReleaseError('Residual correction directory: files/')
    actual = validate_files(files)
    if expected_version is not None and actual != expected_version:
        raise ReleaseError('Unexpected installed CMS version')
    info = metadata_of(files, actual, expected_commit)
    return {'version': actual, 'source_commit': info['commit'], 'files': len(files),
            'manifest': 'all declared application files verified',
            'scope': 'Runtime data and additional custom module files are outside the release manifest'}


def read_archive(archive, expected_version=None, expected_commit=None):
    archive = Path(archive)
    if archive.stat().st_size > MAX_TOTAL_BYTES:
        raise ReleaseError('Compressed release too large')
    try:
        with zipfile.ZipFile(archive) as package:
            infos = package.infolist()
            if not infos or len(infos) > MAX_FILES + 100:
                raise ReleaseError('Invalid release entry count')
            raw = {}
            seen = set()
            total = 0
            for entry in infos:
                name = entry.filename.rstrip('/') if entry.is_dir() else entry.filename
                if not name or name.startswith('/') or '\\' in name or re.search(r'[\x00-\x1f\x7f:]', name):
                    raise ReleaseError('Unsafe ZIP path')
                if any(part in {'', '.', '..'} for part in name.split('/')) or name.casefold() in seen:
                    raise ReleaseError('Duplicate or traversing ZIP path: ' + name)
                seen.add(name.casefold())
                kind = stat.S_IFMT(entry.external_attr >> 16)
                if kind not in {0, stat.S_IFDIR if entry.is_dir() else stat.S_IFREG} or entry.flag_bits & 1:
                    raise ReleaseError('Special or encrypted ZIP entry: ' + name)
                if entry.is_dir():
                    continue
                total += entry.file_size
                if entry.file_size > MAX_FILE_BYTES or total > MAX_TOTAL_BYTES:
                    raise ReleaseError('Expanded release too large')
                data = package.read(entry)
                if len(data) != entry.file_size:
                    raise ReleaseError('Truncated release entry: ' + name)
                raw[name] = data
    except (zipfile.BadZipFile, RuntimeError, NotImplementedError) as exc:
        raise ReleaseError('Invalid or corrupt ZIP: ' + str(exc)) from exc
    wrapped = all(name.startswith('voila-cms/') for name in raw)
    files = {name[len('voila-cms/'):] if wrapped else name: data for name, data in raw.items()}
    actual = validate_files(files)
    if expected_version is not None and actual != expected_version:
        raise ReleaseError('Unexpected CMS version: ' + actual)
    manifest = files.get('MANIFEST-SHA256.txt')
    if manifest is None or manifest != make_manifest(files):
        raise ReleaseError('Manifest mismatch: missing, modified or unlisted release bytes')
    info = metadata_of(files, actual, expected_commit)
    report = {'version': actual, 'source_commit': info['commit'], 'files': len(files),
              'sha256': hashlib.sha256(archive.read_bytes()).hexdigest(),
              'layout': 'voila-cms wrapper' if wrapped else 'application files at ZIP root',
              'expanded_bytes': sum(map(len, files.values())), 'manifest': 'all files verified'}
    return files, report


def extract_verified(files, destination):
    destination = Path(destination).absolute()
    if destination.is_symlink() or destination.exists() and (not destination.is_dir() or any(destination.iterdir())):
        raise ReleaseError('Extraction requires a new or empty directory')
    destination.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='.vv-extract-', dir=destination.parent) as temporary:
        stage = Path(temporary) / 'application'
        stage.mkdir()
        for name, data in files.items():
            target = stage / name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(data)
            target.chmod(0o755 if name == 'Lancer-Mac.command' else 0o644)
        if destination.exists():
            destination.rmdir()
        os.replace(stage, destination)
