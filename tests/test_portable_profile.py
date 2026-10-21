import json
import os
from pathlib import Path
import stat
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'tools' / 'oneclick'))
import portable_profile as p

ID = 'inst-' + 'a1' * 12

def good():
    return {
        'schema': p.SCHEMA, 'profile_id': ID, 'mode': 'managed_vps_candidate',
        'site_root': '/www/wwwroot/customer-example.test', 'site_device': 123456,
        'site_inode': 987654, 'domain': 'customer-example.test',
        'installation_id': 'b2' * 16, 'product_slug': 'barama-real-estate',
        'channel': 'stable', 'bridge_sha256': 'c3' * 32,
        'delivery_sha256': 'd4' * 32,
        'trust_dir': '/etc/barama/oneclick/instances/' + ID + '/trust',
        'vault_dir': '/var/lib/barama/oneclick/' + ID + '/recovery',
        'auto_install_enabled': False,
    }

def encoded(obj):
    return json.dumps(obj, sort_keys=True).encode('utf-8')

class ProfileContract(unittest.TestCase):
    def assertDenied(self, obj, reason):
        with self.assertRaises(p.Denied) as cm:
            p.decode_profile(encoded(obj))
        self.assertEqual(str(cm.exception), reason)

    def test_known_good_candidate(self):
        v=p.decode_profile(encoded(good()));self.assertEqual(v['profile_id'],ID)
        self.assertFalse(v['auto_install_enabled'])
    def test_fields_exact(self):
        for mutation in ('secrets','activation_token','root_command','approved_to_install'):
            obj=good();obj[mutation]='wrong';self.assertDenied(obj,'PROFILE_SCHEMA_FIELDS_INVALID')
        obj=good();obj.pop('site_inode');self.assertDenied(obj,'PROFILE_SCHEMA_FIELDS_INVALID')
    def test_duplicates_denied(self):
        raw=encoded(good()).decode().replace('"schema": ', '"schema": "bad", "schema": ', 1)
        with self.assertRaisesRegex(p.Denied,'DUPLICATE_JSON_FIELD'):p.decode_profile(raw.encode())
    def test_invalid_size(self):
        for content in (b'',b'x'*4097):
            with self.assertRaisesRegex(p.Denied,'PROFILE_SIZE_INVALID'):p.decode_profile(content)
    def test_no_auto_install(self):
        obj=good();obj['auto_install_enabled']=True;self.assertDenied(obj,'PROFILE_INSTALL_MUST_STAY_DISABLED')
    def test_false_string_denied(self):
        obj=good();obj['auto_install_enabled']='false';self.assertDenied(obj,'PROFILE_INSTALL_MUST_STAY_DISABLED')
    def test_mode_denied(self):
        obj=good();obj['mode']='production';self.assertDenied(obj,'PROFILE_MODE_INVALID')
    def test_staging_and_production_denied(self):
        for site in ('/www/wwwroot/baramastaging.tonsamith.com','/www/wwwroot/baramarealestate.tonsamith.com'):
            obj=good();obj['site_root']=site;self.assertDenied(obj,'STAGING_PROFILE_FORBIDDEN' if 'staging' in site else 'PRODUCTION_PROFILE_FORBIDDEN')
    def test_unsafe_site_paths(self):
        for site in ('/www/wwwroot/../etc','/www/wwwroot//foo','/www/wwwroot/foo/','//www/wwwroot/foo','/srv/foo','/www/wwwroot/foo\\bar'):
            obj=good();obj['site_root']=site
            with self.assertRaises(p.Denied):p.decode_profile(encoded(obj))
    def test_unsafe_trust_paths(self):
        for trust in ('/www/wwwroot/customer-example.test/keys','/tmp/keys','/etc/barama/staging/publisher-trust'):
            obj=good();obj['trust_dir']=trust;self.assertDenied(obj,'TRUST_DIR_NOT_ROOT_CONTROLLED')
    def test_unsafe_vault_paths(self):
        for vault in ('/tmp/backup','/root/Barama-OneClick-Recovery/manual-worker','/www/wwwroot/foo/backup'):
            obj=good();obj['vault_dir']=vault;self.assertDenied(obj,'VAULT_DIR_NOT_DEDICATED')
    def test_bad_source_digest(self):
        obj=good();obj['bridge_sha256']='a'*40;self.assertDenied(obj,'SOURCE_DIGEST_INVALID')
    def test_bad_identity(self):
        for v in (0,-1,True,'123'):
            obj=good();obj['site_inode']=v;self.assertDenied(obj,'SITE_IDENTITY_INVALID')
    def test_bad_domain(self):
        for d in ('https://example.test','EXAMPLE.COM','localhost','example.test:8080','a..com'):
            obj=good();obj['domain']=d;self.assertDenied(obj,'DOMAIN_INVALID')
    def test_bad_installation_id(self):
        obj=good();obj['installation_id']='b'*31;self.assertDenied(obj,'INSTALL_ID_INVALID')
    def test_bad_channel(self):
        obj=good();obj['channel']='nightly';self.assertDenied(obj,'CHANNEL_INVALID')
    def test_bad_product(self):
        obj=good();obj['product_slug']='other';self.assertDenied(obj,'PROFILE_PRODUCT_INVALID')
    def test_profile_path(self):
        self.assertEqual(str(p.profile_path(ID)), '/etc/barama/oneclick/profiles/'+ID+'.json')
        for value in ('../../root','staging', 'inst-'+'X'*24):
            with self.assertRaisesRegex(p.Denied,'PROFILE_ID_INVALID'):p.profile_path(value)
    def test_site_inode_binding_disposable_directory(self):
        import tempfile
        with tempfile.TemporaryDirectory() as tmp:
            site = Path(tmp) / 'site'
            site.mkdir()
            obj=good();obj['site_root']=str(site)
            st=site.lstat();obj['site_device']=st.st_dev;obj['site_inode']=st.st_ino
            p.validate_site_identity(obj)
            obj['site_inode']+=1
            with self.assertRaisesRegex(p.Denied,'SITE_DEVICE_INODE_CHANGED'):
                p.validate_site_identity(obj)
    def test_symlink_parent_disallowed(self):
        import tempfile
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);real=root/'real';real.mkdir();alias=root/'alias';alias.symlink_to(real)
            obj=good();obj['site_root']=str(alias);obj['site_device']=real.stat().st_dev;obj['site_inode']=real.stat().st_ino
            with self.assertRaisesRegex(p.Denied,'SITE_ANCESTOR_UNSAFE'):
                p.validate_site_identity(obj)
    def test_private_digest_only_not_authorization(self):
        raw=encoded(good())
        self.assertEqual(len(p.digest_profile(raw)),64)
        self.assertNotIn('token',raw.decode())
    def test_nonroot_profile_read_denied(self):
        with patch('portable_profile.os.geteuid',return_value=1000):
            with self.assertRaisesRegex(p.Denied,'ROOT_ONLY_PREFLIGHT'):
                p.root_anchored_file(p.profile_path(ID))
    def test_not_update_authorization(self):
        obj=good();self.assertFalse(p.decode_profile(encoded(obj))['auto_install_enabled'])
        self.assertFalse(hasattr(p,'run_worker'))
        self.assertFalse(hasattr(p,'install'))

if __name__ == '__main__':
    unittest.main()
