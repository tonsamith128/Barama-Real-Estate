#!/usr/bin/env python3
"""Read-only profile policy for FUTURE managed-VPS One-click workers.

Not a root worker, installer, provisioning program, update authorization or
runtime configuration loader. Never use this module to enable an update.
"""
from __future__ import annotations
import hashlib
import json
import os
from pathlib import Path
import re
import stat

SCHEMA = 'barama-oneclick-portable-profile-candidate-v1'
EXPECTED = frozenset({
    'schema', 'profile_id', 'mode', 'site_root', 'site_device', 'site_inode',
    'domain', 'installation_id', 'product_slug', 'channel',
    'bridge_sha256', 'delivery_sha256', 'trust_dir', 'vault_dir',
    'auto_install_enabled',
})
PROFILE_ID = re.compile(r'inst-[a-f0-9]{24}\Z')
INSTALL_ID = re.compile(r'[a-f0-9]{32}\Z')
DOMAIN = re.compile(r'(?=.{4,253}\Z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}\Z')
SHA256 = re.compile(r'[a-f0-9]{64}\Z')
CHANNELS = frozenset({'stable', 'beta'})
MAX_PROFILE = 4096

class Denied(ValueError):
    """A non-sensitive denial code for a malformed profile."""


def check(ok: bool, code: str) -> None:
    if not ok:
        raise Denied(code)


def unique_keys(pairs):
    out = {}
    for key, value in pairs:
        check(key not in out, 'DUPLICATE_JSON_FIELD')
        out[key] = value
    return out


def canonical_path(value: object, code: str) -> str:
    check(isinstance(value, str) and 1 < len(value) <= 255, code)
    check(value.startswith('/') and not value.startswith('//'), code)
    check('\x00' not in value and '\\' not in value and value != '/', code)
    check(value == os.path.normpath(value), code)
    check(all(part not in ('', '.', '..') for part in value[1:].split('/')), code)
    return value


def decode_profile(payload: bytes) -> dict:
    check(isinstance(payload, bytes) and 0 < len(payload) <= MAX_PROFILE, 'PROFILE_SIZE_INVALID')
    try:
        v = json.loads(payload.decode('utf-8'), object_pairs_hook=unique_keys)
    except (UnicodeError, json.JSONDecodeError) as exc:
        raise Denied('INVALID_JSON') from exc
    check(type(v) is dict and frozenset(v) == EXPECTED, 'PROFILE_SCHEMA_FIELDS_INVALID')
    check(v['schema'] == SCHEMA, 'PROFILE_SCHEMA_INVALID')
    check(type(v['profile_id']) is str and PROFILE_ID.fullmatch(v['profile_id']) is not None, 'PROFILE_ID_INVALID')
    check(v['mode'] == 'managed_vps_candidate', 'PROFILE_MODE_INVALID')
    check(v['auto_install_enabled'] is False, 'PROFILE_INSTALL_MUST_STAY_DISABLED')
    check(v['product_slug'] == 'barama-real-estate', 'PROFILE_PRODUCT_INVALID')
    check(type(v['installation_id']) is str and INSTALL_ID.fullmatch(v['installation_id']) is not None, 'INSTALL_ID_INVALID')
    check(type(v['domain']) is str and DOMAIN.fullmatch(v['domain']) is not None, 'DOMAIN_INVALID')
    check(type(v['channel']) is str and v['channel'] in CHANNELS, 'CHANNEL_INVALID')
    for field in ('bridge_sha256', 'delivery_sha256'):
        check(type(v[field]) is str and SHA256.fullmatch(v[field]) is not None, 'SOURCE_DIGEST_INVALID')
    for field in ('site_device', 'site_inode'):
        check(type(v[field]) is int and 0 < v[field] < 2**63, 'SITE_IDENTITY_INVALID')
    site = canonical_path(v['site_root'], 'SITE_PATH_INVALID')
    # Phase 1 is limited to managed aaPanel-style VPS roots. Alternate layouts
    # require a separately reviewed profile schema; never infer from APP_URL.
    check(site.startswith('/www/wwwroot/') and site.count('/') >= 3, 'SITE_ROOT_UNSUPPORTED')
    check(site != '/www/wwwroot/baramastaging.tonsamith.com', 'STAGING_PROFILE_FORBIDDEN')
    check(site != '/www/wwwroot/baramarealestate.tonsamith.com', 'PRODUCTION_PROFILE_FORBIDDEN')
    ident = v['profile_id']
    trust = canonical_path(v['trust_dir'], 'TRUST_DIR_INVALID')
    vault = canonical_path(v['vault_dir'], 'VAULT_DIR_INVALID')
    check(trust == '/etc/barama/oneclick/instances/' + ident + '/trust', 'TRUST_DIR_NOT_ROOT_CONTROLLED')
    check(vault == '/var/lib/barama/oneclick/' + ident + '/recovery', 'VAULT_DIR_NOT_DEDICATED')
    check(not trust.startswith(site + '/') and not vault.startswith(site + '/'), 'PRIVATE_DIR_UNDER_WEBSITE')
    return v


def profile_path(profile_id: str) -> Path:
    check(type(profile_id) is str and PROFILE_ID.fullmatch(profile_id) is not None, 'PROFILE_ID_INVALID')
    return Path('/etc/barama/oneclick/profiles') / (profile_id + '.json')


def root_anchored_file(path: Path, base: Path = Path('/etc/barama/oneclick/profiles')) -> bytes:
    """Safe for root-only preflight. Refuses symlinks and nonroot writable chain."""
    check(os.geteuid() == 0, 'ROOT_ONLY_PREFLIGHT')
    check(path.parent == base and path.name.endswith('.json'), 'PROFILE_LOCATION_INVALID')
    check(profile_path(path.name[:-5]) == path, 'PROFILE_FILENAME_INVALID')
    for p in (Path('/'), Path('/etc'), Path('/etc/barama'),
              Path('/etc/barama/oneclick'), base):
        st = p.lstat()
        check(stat.S_ISDIR(st.st_mode) and st.st_uid == 0 and (st.st_mode & 0o022) == 0,
              'PROFILE_PARENT_UNTRUSTED')
    fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW | getattr(os, 'O_CLOEXEC', 0))
    try:
        st = os.fstat(fd)
        check(stat.S_ISREG(st.st_mode) and st.st_uid == 0 and (st.st_mode & 0o022) == 0
              and 0 < st.st_size <= MAX_PROFILE, 'PROFILE_FILE_UNTRUSTED')
        with os.fdopen(os.dup(fd), 'rb') as f:
            raw = f.read(MAX_PROFILE + 1)
        check(0 < len(raw) <= MAX_PROFILE, 'PROFILE_SIZE_INVALID')
        return raw
    finally:
        os.close(fd)


def validate_site_identity(profile: dict) -> None:
    """Read-only inode and symlink check; does not trust app config/DB secrets."""
    site = Path(profile['site_root'])
    cursor = Path('/')
    for part in site.parts[1:]:
        cursor /= part
        st = cursor.lstat()
        check(stat.S_ISDIR(st.st_mode) and not stat.S_ISLNK(st.st_mode), 'SITE_ANCESTOR_UNSAFE')
    st = site.lstat()
    check(st.st_dev == profile['site_device'] and st.st_ino == profile['site_inode'],
          'SITE_DEVICE_INODE_CHANGED')


def read_profile(profile_id: str) -> dict:
    """Root preflight only. No process spawning, service control or writes."""
    p = profile_path(profile_id)
    raw = root_anchored_file(p)
    profile = decode_profile(raw)
    check(profile['profile_id'] == profile_id, 'PROFILE_ID_BINDING_MISMATCH')
    validate_site_identity(profile)
    return profile


def digest_profile(payload: bytes) -> str:
    """Diagnostic fingerprint (NOT update authorization)."""
    return hashlib.sha256(payload).hexdigest()
