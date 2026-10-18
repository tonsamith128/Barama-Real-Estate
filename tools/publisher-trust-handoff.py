#!/usr/bin/env python3
"""Barama Phase 4D3: publisher *public-only* catalog handoff.

Never signs, publishes, downloads or installs an application update.
Never touches /www/wwwroot/baramastaging.tonsamith.com or a database.

The human operator must verify the supplied expected fingerprints through a
trustworthy, independent/out-of-band channel. This program cannot establish
independence by itself and must not be treated as proof of publisher identity.
"""
import argparse
import base64
import hashlib
import json
import os
import re
import stat
import sys

PUBLISHER_ROOT = '/www/wwwroot/tonsamith.com'
SOURCE = PUBLISHER_ROOT + '/storage/private/commercial-updates/v2/keys.json'
ROOT_DEST = '/root/verified-publisher'
DEST = ROOT_DEST + '/update-publisher-v2-keys.json'
CONFIRM = 'PREPARE_PUBLIC_ONLY_CATALOG_AFTER_INDEPENDENT_VERIFICATION'
KEY_RE = re.compile(r'[a-z0-9][a-z0-9-]{2,90}\Z')
HEX_RE = re.compile(r'[a-f0-9]{64}\Z')
MAX_BYTES = 65536

class Stop(Exception):
    pass


def unique_pairs(items):
    obj = {}
    for k, v in items:
        if k in obj:
            raise ValueError('Duplicate JSON field')
        obj[k] = v
    return obj


def no_symlink_components(path):
    if not os.path.isabs(path):
        raise Stop('PATH_NOT_ABSOLUTE')
    current = ''
    for part in path.strip('/').split('/'):
        current += '/' + part
        if os.path.islink(current):
            raise Stop('SYMLINK_PATH_UNTRUSTED')


def secure_read(path, strict_owner=True):
    no_symlink_components(path)
    flags = os.O_RDONLY | getattr(os, 'O_NOFOLLOW', 0)
    if hasattr(os, 'O_CLOEXEC'):
        flags |= os.O_CLOEXEC
    try:
        fd = os.open(path, flags)
    except OSError:
        raise Stop('CATALOG_MISSING_OR_UNREADABLE')
    try:
        s = os.fstat(fd)
        if not stat.S_ISREG(s.st_mode) or s.st_size <= 0 or s.st_size > MAX_BYTES:
            raise Stop('CATALOG_SIZE_OR_TYPE_UNSAFE')
        if s.st_mode & 0o022:
            raise Stop('CATALOG_GROUP_OR_WORLD_WRITABLE')
        if strict_owner and s.st_uid != 0:
            raise Stop('CATALOG_NOT_ROOT_OWNED')
        chunks = []
        remain = MAX_BYTES + 1
        while remain > 0:
            chunk = os.read(fd, min(remain, 8192))
            if not chunk:
                break
            chunks.append(chunk)
            remain -= len(chunk)
        raw = b''.join(chunks)
        if len(raw) != s.st_size or len(raw) > MAX_BYTES:
            raise Stop('CATALOG_CHANGED_DURING_READ')
        return raw
    finally:
        os.close(fd)


def catalog_details(raw):
    try:
        data = json.loads(raw.decode('utf-8'), object_pairs_hook=unique_pairs)
    except (UnicodeError, ValueError):
        raise Stop('CATALOG_INVALID_JSON_OR_DUPLICATE_KEYS')
    if not isinstance(data, dict) or list(data) != ['format', 'keys']:
        raise Stop('CATALOG_FORMAT_INVALID')
    if data['format'] != 'tonsamith-publisher-keys-v2':
        raise Stop('CATALOG_FORMAT_INVALID')
    keys = data['keys']
    if not isinstance(keys, dict) or not 1 <= len(keys) <= 32:
        raise Stop('CATALOG_KEYS_INVALID')
    public_hashes = []
    active = []
    for key_id, value in keys.items():
        if not isinstance(key_id, str) or not KEY_RE.fullmatch(key_id):
            raise Stop('CATALOG_KEY_ID_INVALID')
        if not isinstance(value, dict) or list(value) != ['public', 'status']:
            raise Stop('CATALOG_NONPUBLIC_FIELD_PRESENT')
        status = value['status']
        encoded = value['public']
        if status not in ('active', 'retired', 'revoked') or not isinstance(encoded, str):
            raise Stop('CATALOG_KEY_ROW_INVALID')
        try:
            public = base64.b64decode(encoded, validate=True)
        except (ValueError, base64.binascii.Error):
            raise Stop('CATALOG_KEY_ENCODING_INVALID')
        if len(public) != 32 or base64.b64encode(public).decode('ascii') != encoded:
            raise Stop('CATALOG_ED25519_PUBLIC_KEY_INVALID')
        fp = hashlib.sha256(public).hexdigest()
        public_hashes.append(fp)
        if status == 'active':
            active.append(fp)
    if not active:
        raise Stop('CATALOG_HAS_NO_ACTIVE_KEY')
    return {
        'sha256': hashlib.sha256(raw).hexdigest(),
        'total': len(keys),
        'active': len(active),
        'public_hashes': public_hashes,
        'active_hashes': active,
    }


def prepare(raw, dest, expected_catalog, expected_active_public, approval):
    if approval != CONFIRM:
        raise Stop('OWNER_CONFIRMATION_REQUIRED')
    if not HEX_RE.fullmatch(expected_catalog) or not HEX_RE.fullmatch(expected_active_public):
        raise Stop('INDEPENDENT_FINGERPRINTS_REQUIRED')
    detail = catalog_details(raw)
    if detail['sha256'] != expected_catalog:
        raise Stop('CATALOG_SHA256_MISMATCH')
    if expected_active_public not in detail['active_hashes']:
        raise Stop('ACTIVE_PUBLIC_KEY_SHA256_MISMATCH')

    directory = os.path.dirname(dest)
    parent = os.path.dirname(directory)
    no_symlink_components(parent)
    if not os.path.isdir(parent):
        raise Stop('DESTINATION_PARENT_MISSING')
    if not os.path.lexists(directory):
        os.mkdir(directory, 0o700)
    no_symlink_components(directory)
    s = os.stat(directory)
    if not stat.S_ISDIR(s.st_mode) or s.st_mode & 0o077:
        raise Stop('PRIVATE_DESTINATION_DIRECTORY_UNSAFE')
    if os.path.lexists(dest):
        raise Stop('DESTINATION_ALREADY_EXISTS')
    # O_EXCL refuses an existing name; no replacement or symlink follows.
    fd = os.open(dest, os.O_CREAT | os.O_EXCL | os.O_WRONLY | getattr(os, 'O_NOFOLLOW', 0), 0o600)
    try:
        os.fchmod(fd, 0o600)
        with os.fdopen(fd, 'wb', closefd=False) as f:
            f.write(raw)
            f.flush()
            os.fsync(fd)
        if os.fstat(fd).st_size != len(raw):
            raise Stop('DESTINATION_WRITE_LENGTH_MISMATCH')
    except BaseException:
        os.unlink(dest)
        raise
    finally:
        os.close(fd)
    # Re-read for an independent round-trip checksum; don't print key values.
    target = secure_read(dest, strict_owner=(os.geteuid() == 0))
    if hashlib.sha256(target).hexdigest() != detail['sha256']:
        raise Stop('DESTINATION_VERIFY_FAILED')
    return detail


def main(argv=None):
    p = argparse.ArgumentParser(description='Publisher public-only catalog audit and guarded private handoff')
    modes = p.add_subparsers(dest='mode', required=True)
    modes.add_parser('audit', help='Read-only. Reports catalog schema and public fingerprints.')
    destp = modes.add_parser('prepare', help='Owner-approved. Creates ONLY /root/verified-publisher public catalog.')
    destp.add_argument('--expected-catalog-sha256', required=True)
    destp.add_argument('--expected-active-public-sha256', required=True)
    destp.add_argument('--confirm', required=True)
    args = p.parse_args(argv)
    if os.name != 'posix' or os.geteuid() != 0:
        raise Stop('ROOT_REQUIRED')
    raw = secure_read(SOURCE)
    data = catalog_details(raw)
    print('MODE=PUBLISHER_PUBLIC_ONLY_' + ('READ_ONLY_AUDIT' if args.mode == 'audit' else 'PRIVATE_HANDOFF'))
    print('CATALOG_SCHEMA=VALID_PUBLIC_ONLY_V2')
    print('KEY_COUNT=' + str(data['total']))
    print('ACTIVE_KEY_COUNT=' + str(data['active']))
    print('CATALOG_SHA256=' + data['sha256'])
    for i, value in enumerate(data['public_hashes'], 1):
        print('PUBLIC_KEY_%d_SHA256=%s' % (i, value))
    if args.mode == 'audit':
        print('INDEPENDENT_ORIGIN_AUTHENTICATED=NO')
        print('ROOT_PRIVATE_COPY_WRITTEN=NO')
    else:
        # The operator, not this host, must obtain expected values from an
        # independent trusted record. Matching same-host hashes is not evidence.
        prepare(raw, DEST, args.expected_catalog_sha256,
                args.expected_active_public_sha256, args.confirm)
        print('ROOT_PRIVATE_COPY_WRITTEN=YES')
        print('ROOT_PRIVATE_CATALOG=' + DEST)
        print('OUT_OF_BAND_ORIGIN_VERIFICATION=OPERATOR_RESPONSIBILITY')
    print('BARAMA_CONFIG_WRITES=NO')
    print('PUBLISHER_SOURCE_WRITES=NO')
    print('HTTP_REQUESTS=NO')
    print('DATABASE_CONNECTIONS=NO')
    print('PRODUCTION_MODIFIED=NO')
    print('AUTO_INSTALL=DISABLED')
    print('RESULT=PASS')

if __name__ == '__main__':
    try:
        main()
    except Stop as e:
        print('STOP=' + str(e), file=sys.stderr)
        sys.exit(1)
