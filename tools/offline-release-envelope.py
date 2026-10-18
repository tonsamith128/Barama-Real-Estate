#!/usr/bin/env python3
"""Offline Barama Publisher V2 Ed25519 envelope signer. Never publishes or installs."""
import argparse
import base64
import datetime as dt
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import tempfile
import zipfile

PREFIX = b'TONSAMITH-RELEASE-MANIFEST-V2\n'
KEY_RE = re.compile(r'[a-z0-9][a-z0-9-]{2,90}\Z')
VER_RE = re.compile(r'[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?\Z')
ID_RE = re.compile(r'[A-Za-z0-9][A-Za-z0-9._-]{1,100}\Z')
SPKI_HEADER = bytes.fromhex('302a300506032b6570032100')
MAX_ZIP = 536870912

class Refused(Exception):
    pass

def require_regular(path, max_bytes, private=False):
    p = Path(path).expanduser().absolute()
    if p.is_symlink() or not p.is_file():
        raise Refused('FILE_MISSING_OR_SYMLINK')
    st = p.stat()
    if not stat.S_ISREG(st.st_mode) or st.st_size <= 0 or st.st_size > max_bytes:
        raise Refused('FILE_SIZE_OR_TYPE_INVALID')
    if st.st_mode & 0o022:
        raise Refused('FILE_GROUP_OR_WORLD_WRITABLE')
    if private and st.st_mode & 0o077:
        raise Refused('PRIVATE_KEY_PERMISSIONS_MUST_BE_0600')
    return p

def unique_obj(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise Refused('DUPLICATE_CATALOG_FIELD')
        result[key] = value
    return result

def file_sha256(path):
    h = hashlib.sha256()
    with path.open('rb') as file:
        for chunk in iter(lambda: file.read(1024 * 1024), b''):
            h.update(chunk)
    return h.hexdigest()

def public_from_catalog(path, key_id, expected_catalog, expected_public):
    p = require_regular(path, 65536)
    if file_sha256(p) != expected_catalog:
        raise Refused('INDEPENDENT_CATALOG_SHA256_MISMATCH')
    try:
        obj = json.loads(p.read_text('utf-8'), object_pairs_hook=unique_obj)
    except (ValueError, UnicodeError) as ex:
        raise Refused('PUBLIC_CATALOG_INVALID') from ex
    if not isinstance(obj, dict) or list(obj) != ['format', 'keys'] or obj['format'] != 'tonsamith-publisher-keys-v2':
        raise Refused('PUBLIC_CATALOG_INVALID')
    keys = obj['keys']
    if not isinstance(keys, dict) or not 1 <= len(keys) <= 32:
        raise Refused('PUBLIC_CATALOG_INVALID')
    for kid, entry in keys.items():
        if not isinstance(kid, str) or not KEY_RE.fullmatch(kid) or not isinstance(entry, dict) or list(entry) != ['public', 'status']:
            raise Refused('PUBLIC_CATALOG_INVALID')
        if entry['status'] not in ('active', 'retired', 'revoked') or not isinstance(entry['public'], str):
            raise Refused('PUBLIC_CATALOG_INVALID')
        try:
            raw = base64.b64decode(entry['public'], validate=True)
        except ValueError as ex:
            raise Refused('PUBLIC_CATALOG_INVALID') from ex
        if len(raw) != 32 or base64.b64encode(raw).decode() != entry['public']:
            raise Refused('PUBLIC_CATALOG_INVALID')
    if key_id not in keys or keys[key_id]['status'] != 'active':
        raise Refused('KEY_ID_NOT_ACTIVE_IN_PUBLIC_CATALOG')
    raw = base64.b64decode(keys[key_id]['public'])
    if hashlib.sha256(raw).hexdigest() != expected_public:
        raise Refused('INDEPENDENT_PUBLIC_KEY_SHA256_MISMATCH')
    return raw

def run_openssl(*args):
    try:
        r = subprocess.run(['openssl', *args], stdout=subprocess.PIPE,
                           stderr=subprocess.DEVNULL, check=False, timeout=20)
    except (OSError, subprocess.TimeoutExpired) as ex:
        raise Refused('OPENSSL_UNAVAILABLE') from ex
    if r.returncode != 0:
        raise Refused('OPENSSL_OPERATION_FAILED')
    return r.stdout

def inspect_zip(path, version):
    p = require_regular(path, MAX_ZIP)
    if not zipfile.is_zipfile(p):
        raise Refused('RELEASE_ZIP_INVALID')
    entries = set()
    files = 0
    total = 0
    got_version = None
    with zipfile.ZipFile(p) as z:
        for item in z.infolist():
            name = item.filename
            folder = name.endswith('/')
            n = name[:-1] if folder else name
            if not n or len(n) > 512 or not re.fullmatch(r'[A-Za-z0-9_.\-/]+', n) or any(
                piece in ('', '.', '..') or piece.startswith('.') for piece in n.split('/')):
                raise Refused('RELEASE_ENTRY_UNSAFE')
            fold = n.lower()
            if fold in entries:
                raise Refused('RELEASE_ENTRY_DUPLICATE')
            entries.add(fold)
            allowdir = folder and n in ('application','application/app','application/routes',
                'application/database','application/database/migrations','application/resources',
                'application/resources/views','assets','updater','installer')
            allowed = (allowdir or n == 'VERSION' or n.startswith((
                'application/app/','application/routes/','application/database/migrations/',
                'application/resources/views/','assets/')) or n in (
                'index.php','updater/index.php','installer/index.php'))
            if not allowed or any(piece.lower() in ('storage','config','vendor','node_modules',
                'uploads','private','.git','.env','.htaccess','.user.ini') for piece in n.split('/')):
                raise Refused('RELEASE_PROTECTED_PATH')
            if re.search(r'\.(pem|key|p12|pfx|sql|sqlite|bak|log)$',n,re.I):
                raise Refused('RELEASE_PROTECTED_PATH')
            if folder:
                ftype = (item.external_attr >> 16) & 0o170000
                if ftype not in (0, 0o040000):
                    raise Refused('RELEASE_SPECIAL_DIRECTORY')
                if re.search(r'\.(php|js|css|json)$',n,re.I):
                    raise Refused('RELEASE_FAKE_DIRECTORY')
                continue
            ftype = (item.external_attr >> 16) & 0o170000
            if ftype not in (0, 0o100000):
                raise Refused('RELEASE_SPECIAL_FILE')
            if item.flag_bits & 1 or item.compress_type not in (zipfile.ZIP_STORED, zipfile.ZIP_DEFLATED):
                raise Refused('RELEASE_ZIP_METHOD_UNSAFE')
            if item.file_size > 2147483648 or (item.compress_size == 0 and item.file_size > 0) or (item.compress_size and item.file_size > 200 * item.compress_size):
                raise Refused('RELEASE_ZIP_BOMB')
            total += item.file_size
            if total > 2147483648:
                raise Refused('RELEASE_ZIP_BOMB')
            if n == 'VERSION':
                if item.file_size > 48:
                    raise Refused('RELEASE_VERSION_MISMATCH')
                got_version = z.read(item).decode('utf-8').strip()
            else:
                files += 1
        if len(entries) < 2 or len(entries) > 8000 or files < 1 or got_version != version:
            raise Refused('RELEASE_VERSION_MISMATCH')
    return p

def sign_envelope(args):
    if os.environ.get('SSH_CONNECTION') or os.environ.get('SSH_CLIENT') or os.environ.get('GITHUB_ACTIONS'):
        raise Refused('REMOTE_OR_CI_ENVIRONMENT_REJECTED')
    if not KEY_RE.fullmatch(args.key_id) or not ID_RE.fullmatch(args.release_id) or not VER_RE.fullmatch(args.version):
        raise Refused('RELEASE_METADATA_INVALID')
    if not VER_RE.fullmatch(args.min_php) or (args.minimum_app_version and not VER_RE.fullmatch(args.minimum_app_version)):
        raise Refused('RELEASE_METADATA_INVALID')
    if not all(re.fullmatch(r'[a-f0-9]{64}', x) for x in (args.expect_catalog_sha256,args.expect_public_key_sha256)):
        raise Refused('INDEPENDENT_FINGERPRINTS_REQUIRED')
    pub = public_from_catalog(args.catalog, args.key_id, args.expect_catalog_sha256, args.expect_public_key_sha256)
    key = require_regular(args.private_key, 16384, private=True)
    archive = inspect_zip(args.archive, args.version)
    output = Path(args.output).expanduser().absolute()
    if output.exists() or output.is_symlink() or not output.parent.is_dir() or output.parent.is_symlink():
        raise Refused('OUTPUT_MUST_BE_NEW_FILE_IN_EXISTING_DIRECTORY')
    der = run_openssl('pkey','-in',str(key),'-pubout','-outform','DER')
    if der != SPKI_HEADER + pub:
        raise Refused('PRIVATE_KEY_PUBLIC_CATALOG_MISMATCH')
    now = dt.datetime.now(dt.timezone.utc)
    iso = lambda x: x.strftime('%Y-%m-%dT%H:%M:%SZ')
    manifest = {
        'schema':'tonsamith-commercial-release-v2',
        'publisher':'tonsamith.com',
        'key_id':args.key_id,
        'release_id':args.release_id,
        'product_slug':'barama-real-estate',
        'version':args.version,
        'channel':'stable',
        'release_type':'update',
        'release_status':'published',
        'package_sha256':file_sha256(archive),
        'package_bytes':archive.stat().st_size,
        'min_php':args.min_php,
        'minimum_app_version':args.minimum_app_version,
        'migration_policy':'additive',
        'published_at':iso(now - dt.timedelta(minutes=1)),
        'expires_at':iso(now + dt.timedelta(days=7)),
    }
    canonical = json.dumps(manifest,sort_keys=True,separators=(',',':'),ensure_ascii=False).encode('utf-8')
    payload = PREFIX + canonical
    with tempfile.TemporaryDirectory(prefix='barama-offline-sign-') as d:
        message = Path(d)/'message'
        sig = Path(d)/'signature'
        message.write_bytes(payload)
        run_openssl('pkeyutl','-sign','-inkey',str(key),'-rawin','-in',str(message),'-out',str(sig))
        signed = sig.read_bytes()
    if len(signed) != 64:
        raise Refused('INVALID_SIGNATURE_LENGTH')
    envelope = {'manifest':manifest,'signature':{
        'algorithm':'Ed25519','key_id':args.key_id,'value':base64.b64encode(signed).decode('ascii')}}
    flags = os.O_CREAT | os.O_EXCL | os.O_WRONLY | getattr(os, 'O_NOFOLLOW', 0)
    fd = os.open(output,flags,0o600)
    try:
        with os.fdopen(fd,'w',encoding='utf-8') as f:
            json.dump(envelope,f,ensure_ascii=False,separators=(',',':'))
            f.write('\n')
            f.flush()
            os.fsync(f.fileno())
    except BaseException:
        output.unlink(missing_ok=True)
        raise
    print('OFFLINE_ENVELOPE_SIGNED=YES')
    print('PUBLISHER_REQUESTS=NO')
    print('WEBSITE_WRITES=NO')
    print('AUTOMATIC_INSTALL=DISABLED')
    print('PUBLIC_RELEASE_ARCHIVE_SHA256='+manifest['package_sha256'])
    print('SIGNED_VERSION='+args.version)
    print('SIGNATURE_KEY_ID='+args.key_id)
    print('OUTPUT_ENVELOPE='+str(output))

def main():
    p = argparse.ArgumentParser(description='Sign reviewed Barama V2 ZIP offline; never publish')
    p.add_argument('--private-key',required=True)
    p.add_argument('--catalog',required=True)
    p.add_argument('--archive',required=True)
    p.add_argument('--version',required=True)
    p.add_argument('--release-id',required=True)
    p.add_argument('--key-id',required=True)
    p.add_argument('--expect-catalog-sha256',required=True)
    p.add_argument('--expect-public-key-sha256',required=True)
    p.add_argument('--output',required=True)
    p.add_argument('--min-php',default='8.0.0')
    p.add_argument('--minimum-app-version',default='')
    args=p.parse_args()
    sign_envelope(args)

if __name__ == '__main__':
    try:
        main()
    except Refused as e:
        print('STOP='+str(e),file=sys.stderr)
        sys.exit(1)
