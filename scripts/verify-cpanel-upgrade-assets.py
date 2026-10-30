#!/usr/bin/env python3
"""Verify the compiled CSS/JS in the exact cPanel upgrade ZIP."""
import json
import sys
from pathlib import Path
from zipfile import ZipFile

def validate(path: Path) -> None:
    assert path.is_file(), 'No cPanel upgrade ZIP'
    root = 'Barama-Laravel-cPanel-PostInstall-Upgrade/'
    with ZipFile(path) as archive:
        assert archive.testzip() is None, 'Corrupt ZIP archive'
        names = set(archive.namelist())
        assert root + 'public/build/manifest.json' in names, 'Missing public/build/manifest.json'
        assert root + 'public/manifest.json' not in names, 'Wrong Vite manifest location'
        manifest = json.loads(archive.read(root + 'public/build/manifest.json'))
        selectors = {
            'resources/css/frontend/app.css': '.home-hero',
            'resources/css/admin/app.css': '.dashboard-layout',
        }
        for source, selector in selectors.items():
            target = manifest[source]['file']
            assert target.startswith('assets/') and '..' not in target
            filename = root + 'public/build/' + target
            assert filename in names, f'Compiled CSS is missing: {filename}'
            assert selector in archive.read(filename).decode(), f'Stale compiled CSS: {selector}'
        for source, entry in manifest.items():
            target = root + 'public/build/' + entry['file']
            assert target in names, f'Broken Vite manifest: {source}'
        assert not any(x.startswith(root + 'public/assets/') for x in names), 'Wrong /public/assets/'
    print('CPANEL_OVERLAY_CSS_JS_MANIFEST_INTEGRITY_PASS')

if __name__ == '__main__':
    validate(Path(sys.argv[1]) if len(sys.argv) > 1 else Path('dist/Barama-Laravel-cPanel-PostInstall-Upgrade.zip'))
