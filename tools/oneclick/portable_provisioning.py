#!/usr/bin/env python3
"""Phase 2A: disposable provisioning contract exercise ONLY.

Never run this on an actual customer/staging application directory.
A future root-owned production provisioner must be separately designed.
"""
from __future__ import annotations
import hashlib
import json
import os
from pathlib import Path
import stat
import tempfile

from portable_profile import decode_profile, Denied, PROFILE_ID

SANDBOX_PREFIX = 'Barama-OneClick-Portable-Fixture-'


def ensure(ok: bool, code: str) -> None:
    if not ok:
        raise Denied(code)


def sandbox_root(root: Path) -> Path:
    root = Path(root)
    temp = Path(tempfile.gettempdir()).resolve(strict=True)
    ensure(root.parent == temp and root.name.startswith(SANDBOX_PREFIX), 'NOT_A_DISPOSABLE_FIXTURE')
    st = root.lstat()
    ensure(stat.S_ISDIR(st.st_mode) and st.st_uid == os.geteuid() and
           (st.st_mode & 0o077) == 0 and not root.is_symlink(), 'FIXTURE_ROOT_UNSAFE')
    ensure(root.resolve(strict=True) == root, 'FIXTURE_ROOT_NOT_CANONICAL')
    return root


def fixture_path(root: Path, absolute: str) -> Path:
    """Map approved fixture layout into one disposable, non-symlink tmp root."""
    root = sandbox_root(root)
    ensure(isinstance(absolute, str) and absolute.startswith('/') and not absolute.startswith('//'),
           'FIXTURE_PATH_INVALID')
    components = absolute[1:].split('/')
    ensure(all(c and c not in ('.', '..') and '\\' not in c and '\x00' not in c for c in components),
           'FIXTURE_PATH_INVALID')
    return root.joinpath(*components)


def private_mkdir(root: Path, relative_path: str) -> Path:
    root = sandbox_root(root)
    target = fixture_path(root, '/' + relative_path)
    cur = root
    for segment in relative_path.split('/'):
        ensure(segment and segment not in ('.', '..'), 'FIXTURE_PATH_INVALID')
        cur = cur / segment
        if cur.exists() or cur.is_symlink():
            st = cur.lstat()
            ensure(stat.S_ISDIR(st.st_mode) and not cur.is_symlink()
                   and st.st_uid == os.geteuid()
                   and (st.st_mode & 0o077) == 0, 'EXISTING_COMPONENT_UNTRUSTED')
        else:
            cur.mkdir(mode=0o700)
    ensure(target == cur, 'FIXTURE_TARGET_CHANGED')
    return target


def write_private(path: Path, raw: bytes) -> None:
    st = path.parent.lstat()
    ensure(stat.S_ISDIR(st.st_mode) and not path.parent.is_symlink()
           and st.st_uid == os.geteuid() and (st.st_mode & 0o077) == 0, 'PROFILE_PARENT_UNSAFE')
    ensure(not path.exists() and not path.is_symlink(), 'PROFILE_EXISTS')
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    try:
        with os.fdopen(fd, 'wb') as output:
            fd = -1
            output.write(raw)
            output.flush()
            os.fsync(output.fileno())
    finally:
        if fd >= 0:
            os.close(fd)


def provision_fixture(root: Path, candidate: dict) -> dict:
    """Creates a disabled *simulation*. Not a production provisioner."""
    root = sandbox_root(root)
    data = json.dumps(candidate, sort_keys=True, separators=(',', ':')).encode()
    profile = decode_profile(data)
    ident = profile['profile_id']
    ensure(PROFILE_ID.fullmatch(ident) is not None, 'PROFILE_ID_INVALID')
    ensure(profile['auto_install_enabled'] is False, 'PROFILE_INSTALL_MUST_STAY_DISABLED')
    # Refuse collision/reuse of an existing profile, recovery vault, trust root.
    profile_file = fixture_path(root, '/etc/barama/oneclick/profiles/' + ident + '.json')
    trust_root = fixture_path(root, profile['trust_dir'])
    vault_root = fixture_path(root, profile['vault_dir'])
    ensure(not any(x.exists() or x.is_symlink() for x in (profile_file,trust_root,vault_root)),
           'PROFILE_ALREADY_PROVISIONED')
    # A synthetic website directory exists *only* below our private fixture.
    # It is never populated with customer code, config or DB credentials.
    fake_site = private_mkdir(root, profile['site_root'].lstrip('/'))
    st = fake_site.lstat()
    profile['site_device'] = st.st_dev
    profile['site_inode'] = st.st_ino
    data = json.dumps(profile, sort_keys=True, separators=(',', ':')).encode() + b'\n'
    decode_profile(data)
    private_mkdir(root, 'etc/barama/oneclick/profiles')
    write_private(profile_file, data)
    private_mkdir(root, profile['trust_dir'].lstrip('/'))
    private_mkdir(root, profile['vault_dir'].lstrip('/'))
    # An inert marker records intentionally missing execution resources.
    return {'profile':profile_file, 'trust':trust_root, 'vault':vault_root, 'site':fake_site,
            'profile_sha256':hashlib.sha256(data).hexdigest(), 'service_installed':False,
            'worker_executable_installed':False,'update_enabled':False}


def audit_fixture(root: Path, descriptor: dict) -> dict:
    root = sandbox_root(root)
    for key in ('trust','vault','site'):
        p = descriptor[key]
        ensure(isinstance(p, Path) and p.is_relative_to(root), 'FIXTURE_ESCAPE_DENIED')
        st = p.lstat()
        ensure(stat.S_ISDIR(st.st_mode) and not p.is_symlink() and st.st_uid == os.geteuid()
               and (st.st_mode & 0o077)==0, 'FIXTURE_DIR_UNTRUSTED')
    p = descriptor['profile']
    ensure(isinstance(p, Path) and p.is_relative_to(root) and not p.is_symlink(), 'PROFILE_PATH_UNTRUSTED')
    st = p.lstat()
    ensure(stat.S_ISREG(st.st_mode) and st.st_uid == os.geteuid()
           and (st.st_mode & 0o077)==0 and st.st_size<=4096, 'PROFILE_FILE_UNTRUSTED')
    data = p.read_bytes()
    ensure(hashlib.sha256(data).hexdigest()==descriptor['profile_sha256'], 'PROFILE_HASH_CHANGED')
    profile = decode_profile(data)
    s = descriptor['site'].lstat()
    ensure(profile['site_device']==s.st_dev and profile['site_inode']==s.st_ino, 'SITE_IDENTITY_CHANGED')
    ensure(not any(any(descriptor[x].iterdir()) for x in ('trust','vault')), 'PRIVATE_DIR_NOT_EMPTY')
    ensure(not descriptor['service_installed'] and not descriptor['worker_executable_installed']
           and not descriptor['update_enabled'], 'SIMULATION_ESCALATION_DENIED')
    return {'profile_id':profile['profile_id'], 'identity_bound':True,
            'trust_private_empty':True, 'vault_private_empty':True,
            'customer_worker':'NOT_INSTALLED', 'auto_install':'DISABLED'}
