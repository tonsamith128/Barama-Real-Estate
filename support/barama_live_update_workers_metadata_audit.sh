#!/usr/bin/env bash
# Read-only, metadata-only inspection of live-only Barama update workers.
# Does NOT execute target PHP, print their contents, or access MySQL.
set -Eeuo pipefail
set +x
umask 077

ROOT="${1:-/www/wwwroot/baramarealestate.tonsamith.com}"
if [ ! -d "$ROOT/application" ] || [ -L "$ROOT" ] || [ -L "$ROOT/application" ]; then
  echo "AUDIT_BLOCKED=INVALID_OR_SYMLINK_ROOT"
  exit 2
fi
command -v python3 >/dev/null || { echo "AUDIT_BLOCKED=PYTHON3_REQUIRED"; exit 3; }

echo "========== BARAMA LIVE-ONLY UPDATE WORKERS / METADATA AUDIT =========="
python3 - "$ROOT" <<'PY'
from pathlib import Path
import hashlib
import os
import re
import sys
from datetime import datetime, timezone

root = Path(sys.argv[1])
targets = {
    'application/app/Services/Updates/ProductionHostWorkerV1.php':
        '2a91d14f192956b07c5bdfddc8c1a270ede57d4076b58c515333dfe2fc2f0812',
    'application/app/Services/Updates/PortableUpdateApplyV1.php':
        '024273b3a9881ae9a8818e9cde0cc1a2ec2a2ee1e734753773c56225695b1be6',
    'application/resources/views/workspace/barama-updater-completed-receipt-v100.php':
        'ded9be53d404ea99acdd37c1825fea8eb0541a6ed9738d49b67c5fcbb87a8356',
}
scopes = [
    'application/app', 'application/bootstrap', 'application/routes',
    'application/resources/views', 'application/support', 'support', 'tools',
]
extensions = {'.php', '.sh', '.js', '.json', '.yml', '.yaml', '.conf'}
skip_dirs = {'.git', 'storage', 'vendor', 'node_modules', 'cache', 'logs', '.well-known'}
sources = []
for relative in scopes:
    directory = root / relative
    if not directory.is_dir() or directory.is_symlink():
        continue
    for current, dirs, files in os.walk(directory, followlinks=False):
        dirs[:] = sorted(d for d in dirs if d not in skip_dirs
                         and not (Path(current) / d).is_symlink())
        for name in sorted(files):
            path = Path(current) / name
            if path.is_symlink() or path.suffix.lower() not in extensions:
                continue
            try:
                if path.stat().st_size <= 4_000_000:
                    sources.append(path)
            except OSError:
                continue
entry = root / 'index.php'
if entry.is_file() and not entry.is_symlink():
    sources.append(entry)

sensitive_categories = {
    'PROCESS_EXECUTION': r'\b(?:shell_exec|exec|system|passthru|proc_open|popen)\s*\(',
    'FILE_MUTATION': r'\b(?:file_put_contents|unlink|rename|copy|mkdir|rmdir|chmod|chown|move_uploaded_file)\s*\(',
    'NETWORK_REQUESTS': r'\b(?:curl_exec|fsockopen|stream_socket_client)\s*\(',
    'DATABASE_OPERATIONS': r'(?:\bPDO\b|Database::connection|->(?:prepare|query|exec)\s*\()',
    'DYNAMIC_INCLUDE': r'\b(?:require|include)(?:_once)?\s*(?:\(|\$)',
    'ARCHIVE_OPERATIONS': r'\b(?:ZipArchive|PharData|gzopen|unzip)\b',
    'HASH_OR_SIGNATURE': r'\b(?:hash_hmac|hash_file|hash_equals|openssl_verify|sodium_crypto_sign_verify_detached)\s*\(',
}
print(f'SCANNED_FIRST_PARTY_REFERENCE_FILES={len(sources)}')
print('TARGETS_EXECUTED=NO')
print('DATABASE=NOT_CONNECTED')
print('SOURCE_CONTENTS=NOT_PRINTED')
for relative, expected in targets.items():
    path = root / relative
    print(f'=== TARGET {relative} ===')
    if path.is_symlink() or not path.is_file():
        print('STATE=MISSING_OR_SYMLINK')
        continue
    try:
        raw = path.read_bytes()
        stat = path.stat()
    except OSError:
        print('STATE=UNREADABLE')
        continue
    sha = hashlib.sha256(raw).hexdigest()
    print('SHA256_MATCH_PREVIOUS_AUDIT=' + ('YES' if sha == expected else 'NO'))
    if sha != expected:
        print('ANALYSIS_SKIPPED=FINGERPRINT_CHANGED')
        continue
    print('STATE=VERIFIED_UNCHANGED')
    print(f'SIZE_BYTES={len(raw)}')
    print('MODIFIED_UTC=' + datetime.fromtimestamp(
        stat.st_mtime, timezone.utc).isoformat())
    code = raw.decode('utf-8', errors='replace')
    # Only recognize an exact, known declaration: a PHP docblock may
    # contain prose such as "class is", which must NEVER become a search token.
    expected_classes = {
        'ProductionHostWorkerV1.php': 'ProductionHostWorkerV1',
        'PortableUpdateApplyV1.php': 'PortableUpdateApplyV1',
    }
    expected_class = expected_classes.get(path.name)
    classes = []
    if expected_class and re.search(
        r'\bclass\s+' + re.escape(expected_class) + r'\b', code
    ):
        classes.append(expected_class)
    functions = sorted(set(re.findall(
        r'\bfunction\s+([A-Za-z_][A-Za-z_0-9]*)\s*\(', code
    )))
    print('EXPECTED_DECLARED_CLASSES=' + (','.join(classes) or 'NONE_DETECTED'))
    print('POSSIBLE_DECLARED_FUNCTIONS=' + (','.join(functions[:30]) or 'NONE_DETECTED'))
    if len(functions) > 30:
        print(f'UNLISTED_POSSIBLE_FUNCTION_COUNT={len(functions)-30}')
    for category, pattern in sensitive_categories.items():
        print(category + '_LEXICAL_MATCHES=' + str(len(
            re.findall(pattern, code, flags=re.IGNORECASE)
        )))
    # Scan only approved first-party source paths. Do not inspect logs, config
    # secrets or runtime storage, and do not print matched lines.
    needles = {path.name, path.stem, *classes}
    # A defensive minimum prevents short generic words from ever inflating
    # reference counts, even if a future parser produces spurious symbols.
    needles = sorted(token for token in needles if len(token) >= 12)
    references = set()
    for candidate in sources:
        if candidate == path:
            continue
        try:
            data = candidate.read_bytes()
        except OSError:
            continue
        if any(token.encode('utf-8') in data for token in needles):
            references.add(candidate.relative_to(root).as_posix())
    print('REFERENCE_SEARCH_TOKENS=' + ','.join(needles))
    print(f'EXACT_LITERAL_REFERENCE_FILE_COUNT={len(references)}')
    for reference in sorted(references)[:30]:
        print('REFERENCE_FILE=' + reference)
    if len(references) > 30:
        print(f'UNLISTED_REFERENCE_COUNT={len(references)-30}')
print('DISCLAIMER=Exact_literal_references_are_not_a_complete_runtime_dependency_graph')
print('DECLARATION_PARSER=Known_class_names_only;_function_names_are_lexical_hints')
print('DYNAMIC_CLASS_LOADING_AND_EXTERNAL_CRON_CALLS=NOT_EXCLUDED')
print('SOURCE_CONTENTS=NOT_PRINTED')
print('LIVE_FILES=UNCHANGED')
PY
echo "========== METADATA AUDIT COMPLETE =========="
