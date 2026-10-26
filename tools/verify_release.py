#!/usr/bin/env python3
"""Verify every release byte before optional extraction into an empty directory."""
import argparse
import json
from pathlib import Path
import sys
from release_archive import ReleaseError, read_archive, read_installed, extract_verified


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('archive', type=Path, nargs='?')
    parser.add_argument('--directory', type=Path, help='Read-only verification of manifest-declared installed code')
    parser.add_argument('--expected-version')
    parser.add_argument('--expected-commit')
    parser.add_argument('--extract', type=Path)
    parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    if bool(args.archive) == bool(args.directory) or args.directory and args.extract:
        parser.error('Choose one ZIP archive or --directory; extraction is for ZIPs only')
    try:
        if args.directory:
            report = read_installed(args.directory, args.expected_version, args.expected_commit)
        else:
            files, report = read_archive(args.archive, args.expected_version, args.expected_commit)
            if args.extract:
                extract_verified(files, args.extract)
        if args.report:
            args.report.parent.mkdir(parents=True, exist_ok=True)
            args.report.write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
        print('PASS release:', json.dumps(report))
    except (ReleaseError, OSError) as exc:
        print('FAIL release: ' + str(exc), file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
