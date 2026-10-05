#!/usr/bin/env python3
from pathlib import Path
import sys

root = Path(sys.argv[1] if len(sys.argv) > 1 else '.').resolve()
qa = root / 'tools/qa.php'
if not qa.is_file():
    raise SystemExit(f'ERROR: missing {qa}')

text = qa.read_text(encoding='utf-8')
css_line = "    'assets/css/system-health-v1126.css',\n"
js_line = "    'assets/js/system-health-theme-v1126.js',\n"

if css_line not in text:
    anchor = "    'assets/css/property-detail-flow-v113.css',\n"
    if text.count(anchor) != 1:
        raise SystemExit('ERROR: CSS allowlist anchor is not unique')
    text = text.replace(anchor, anchor + css_line, 1)

if js_line not in text:
    anchor = "    'assets/js/page-builder-v119.js',\n"
    if text.count(anchor) != 1:
        raise SystemExit('ERROR: JS allowlist anchor is not unique')
    text = text.replace(anchor, anchor + js_line, 1)

if text.count(css_line) != 1 or text.count(js_line) != 1:
    raise SystemExit('ERROR: System Status asset allowlist cardinality failed')

qa.write_text(text, encoding='utf-8')
print('PATCH OK: v1.1.26 System Status assets approved in QA allowlists')
