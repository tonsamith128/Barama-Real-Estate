#!/usr/bin/env python3
import base64
import hashlib
import importlib.util
import json
import os
import pathlib
import tempfile
import unittest

SRC = pathlib.Path(__file__).parent / 'publisher-trust-handoff.py'
spec = importlib.util.spec_from_file_location('handoff', SRC)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)

class HandoffTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = pathlib.Path(self.temp.name)
        self.parent = self.root / 'private'
        self.parent.mkdir()
        self.dest = self.parent / 'verified' / 'update-publisher-v2-keys.json'
        self.public = bytes(range(32))
        self.keyfp = hashlib.sha256(self.public).hexdigest()
        self.obj = {'format':'tonsamith-publisher-keys-v2', 'keys': {
            'publisher-key-2026':{'public':base64.b64encode(self.public).decode(), 'status':'active'}}}
        self.raw = json.dumps(self.obj, separators=(',',':')).encode()+b'\n'
        self.digest = hashlib.sha256(self.raw).hexdigest()
    def tearDown(self): self.temp.cleanup()
    def test_audit_valid(self):
        out=m.catalog_details(self.raw)
        self.assertEqual(out['sha256'], self.digest)
        self.assertEqual(out['active'], 1)
        self.assertEqual(out['public_hashes'], [self.keyfp])
    def test_invalid_digest_rejected_and_nothing_created(self):
        with self.assertRaisesRegex(m.Stop, 'CATALOG_SHA256_MISMATCH'):
            m.prepare(self.raw,str(self.dest),'0'*64,self.keyfp,m.CONFIRM)
        self.assertFalse(self.dest.exists())
    def test_invalid_public_fp_rejected(self):
        with self.assertRaisesRegex(m.Stop,'ACTIVE_PUBLIC_KEY_SHA256_MISMATCH'):
            m.prepare(self.raw,str(self.dest),self.digest,'a'*64,m.CONFIRM)
    def test_confirm_needed(self):
        with self.assertRaisesRegex(m.Stop, 'OWNER_CONFIRMATION_REQUIRED'):
            m.prepare(self.raw,str(self.dest),self.digest,self.keyfp,'')
    def test_public_only_create(self):
        out=m.prepare(self.raw,str(self.dest),self.digest,self.keyfp,m.CONFIRM)
        self.assertEqual(out['active'],1)
        self.assertEqual(self.dest.read_bytes(),self.raw)
        self.assertEqual(self.dest.stat().st_mode&0o777,0o600)
        self.assertEqual(self.dest.parent.stat().st_mode&0o777,0o700)
    def test_no_clobber_even_if_matching(self):
        m.prepare(self.raw,str(self.dest),self.digest,self.keyfp,m.CONFIRM)
        with self.assertRaisesRegex(m.Stop,'DESTINATION_ALREADY_EXISTS'):
            m.prepare(self.raw,str(self.dest),self.digest,self.keyfp,m.CONFIRM)
    def test_no_private_fields(self):
        self.obj['keys']['publisher-key-2026']['private']='should-not-be-there'
        with self.assertRaisesRegex(m.Stop,'CATALOG_NONPUBLIC_FIELD_PRESENT'):
            m.catalog_details(json.dumps(self.obj).encode())
    def test_revoked_only(self):
        self.obj['keys']['publisher-key-2026']['status']='revoked'
        with self.assertRaisesRegex(m.Stop,'CATALOG_HAS_NO_ACTIVE_KEY'):
            m.catalog_details(json.dumps(self.obj).encode())
    def test_duplicate_json_fields(self):
        raw=b'{"format":"tonsamith-publisher-keys-v2","format":"x","keys":{}}'
        with self.assertRaisesRegex(m.Stop,'CATALOG_INVALID_JSON_OR_DUPLICATE_KEYS'):
            m.catalog_details(raw)
    def test_symlink_source_rejected(self):
        file=self.root/'src.json'; file.write_bytes(self.raw)
        link=self.root/'link.json';link.symlink_to(file)
        with self.assertRaisesRegex(m.Stop,'SYMLINK_PATH_UNTRUSTED'):
            m.secure_read(str(link),strict_owner=False)
    def test_group_writable_source_rejected(self):
        file=self.root/'src.json'; file.write_bytes(self.raw); file.chmod(0o660)
        with self.assertRaisesRegex(m.Stop,'CATALOG_GROUP_OR_WORLD_WRITABLE'):
            m.secure_read(str(file),strict_owner=False)
    def test_source_unchanged(self):
        file=self.root/'src.json'; file.write_bytes(self.raw); file.chmod(0o640)
        before=file.read_bytes()
        raw=m.secure_read(str(file),strict_owner=False)
        m.prepare(raw,str(self.dest),self.digest,self.keyfp,m.CONFIRM)
        self.assertEqual(before,file.read_bytes())
    def test_no_bad_base64(self):
        self.obj['keys']['publisher-key-2026']['public']='not_base64'
        with self.assertRaisesRegex(m.Stop,'CATALOG_KEY_ENCODING_INVALID'):
            m.catalog_details(json.dumps(self.obj).encode())
    def test_destination_symlink_prevented(self):
        target=self.root/'other';target.mkdir()
        (self.parent/'verified').symlink_to(target, target_is_directory=True)
        with self.assertRaisesRegex(m.Stop,'SYMLINK_PATH_UNTRUSTED'):
            m.prepare(self.raw,str(self.dest),self.digest,self.keyfp,m.CONFIRM)

if __name__ == '__main__':
    unittest.main(verbosity=2)
