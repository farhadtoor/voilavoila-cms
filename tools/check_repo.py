#!/usr/bin/env python3
"""Pre-publication hygiene and local Markdown-link checks; not a secret scanner."""
import argparse
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
SKIP = {'.git', '.venv', 'node_modules', '__pycache__', 'dist', 'build', 'test-results'}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--allow-template', action='store_true', help='Allow unconfigured distribution templates')
    args = parser.parse_args()
    errors, count = [], 0
    for path in ROOT.rglob('*'):
        rel = path.relative_to(ROOT)
        if any(part in SKIP for part in rel.parts) or not path.is_file():
            continue
        count += 1
        if path.is_symlink():
            errors.append(f'Symlink: {rel}')
        if rel.parts[0] == 'storage' and rel.as_posix() not in ['storage/.htaccess', 'storage/index.php']:
            errors.append(f'Runtime storage must not be published: {rel}')
        if re.search(r'\.(sqlite(?:3)?|db)(?:-|$)', path.name) or path.name == '.env' or path.name.startswith('.env.') and path.name != '.env.example':
            errors.append(f'Private database/configuration file: {rel}')
        if path.suffix not in {'.md', '.yml', '.yaml'} and path.name not in {'NOTICE', 'CODEOWNERS'}:
            continue
        source = path.read_text(encoding='utf-8')
        if not args.allow_template and any(token in source for token in ['GITHUB_OWNER', 'GITHUB_REPOSITORY', 'GITHUB_MAINTAINER', 'MAINTAINER_CONTACT']):
            errors.append(f'Configure project placeholders: {rel}')
        if path.suffix == '.md':
            # Ignore external links, anchors and fenced code samples.
            source = re.sub(r'```.*?```', '', source, flags=re.S)
            for link in re.findall(r'\[[^\]]*\]\(([^\s)]+)\)', source):
                if '://' in link or link.startswith(('#', 'mailto:')):
                    continue
                destination = link.split('#', 1)[0]
                if destination and not (path.parent / destination).exists():
                    errors.append(f'Broken local Markdown link: {rel} -> {link}')
    if errors:
        print('\n'.join('FAIL ' + error for error in errors), file=sys.stderr)
        return 1
    print(f'PASS {count} repository files: no runtime data, configured links, valid local Markdown targets')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
