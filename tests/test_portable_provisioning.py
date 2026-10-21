import json, os, pathlib, stat, sys, tempfile, unittest
from unittest.mock import patch
HERE = pathlib.Path(__file__).resolve().parent
sys.path.insert(0,str(HERE.parent / 'tools' / 'oneclick'))
import portable_provisioning as w
import portable_profile as p
ID='inst-'+'ab'*12

def candidate():
    return {'schema':p.SCHEMA,'profile_id':ID,'mode':'managed_vps_candidate',
            'site_root':'/www/wwwroot/disposable-test.example',
            'site_device':1,'site_inode':1,'domain':'disposable-test.example',
            'installation_id':'cd'*16,'product_slug':'barama-real-estate','channel':'stable',
            'bridge_sha256':'11'*32,'delivery_sha256':'22'*32,
            'trust_dir':'/etc/barama/oneclick/instances/'+ID+'/trust',
            'vault_dir':'/var/lib/barama/oneclick/'+ID+'/recovery',
            'auto_install_enabled':False}

class ProvisioningSandbox(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory(prefix=w.SANDBOX_PREFIX)
        self.addCleanup(self.tmp.cleanup)
        self.root=pathlib.Path(self.tmp.name)
        self.root.chmod(0o700)
    def provision(self):
        return w.provision_fixture(self.root,candidate())
    def assertStop(self,callback,msg):
        with self.assertRaisesRegex((p.Denied,OSError),msg):callback()
    def test_installed_fixture_audit(self):
        result=self.provision(); self.assertEqual(w.audit_fixture(self.root,result)['customer_worker'],'NOT_INSTALLED')
    def test_private_permissions(self):
        r=self.provision()
        self.assertEqual(stat.S_IMODE(r['profile'].stat().st_mode),0o600)
        for k in ('trust','vault','site'):self.assertEqual(stat.S_IMODE(r[k].stat().st_mode),0o700)
    def test_no_worker_install(self):
        r=self.provision();self.assertFalse(r['service_installed']);self.assertFalse(r['update_enabled']);self.assertFalse(r['worker_executable_installed'])
    def test_no_secrets_recorded(self):
        r=self.provision();raw=r['profile'].read_text()
        for marker in ('activation_token','password','private_key','api_secret'):self.assertNotIn(marker,raw)
    def test_trust_and_vault_empty(self):
        r=self.provision();self.assertEqual(list(r['trust'].iterdir()),[]);self.assertEqual(list(r['vault'].iterdir()),[])
    def test_double_provisioning_denied(self):
        self.provision();self.assertStop(self.provision,'PROFILE_ALREADY_PROVISIONED')
    def test_profile_mutation_detected(self):
        r=self.provision();r['profile'].write_text('{}\n');self.assertStop(lambda:w.audit_fixture(self.root,r),'PROFILE_HASH_CHANGED')
    def test_profile_symlink_detected(self):
        r=self.provision();p=r['profile'];p.unlink();p.symlink_to(r['site']);self.assertStop(lambda:w.audit_fixture(self.root,r),'PROFILE_PATH_UNTRUSTED')
    def test_recovery_vault_symlink_denied(self):
        r=self.provision();r['vault'].rmdir();r['vault'].symlink_to(r['trust']);self.assertStop(lambda:w.audit_fixture(self.root,r),'FIXTURE_DIR_UNTRUSTED')
    def test_recovery_vault_nonstrict_mode_denied(self):
        r=self.provision();r['vault'].chmod(0o755);self.assertStop(lambda:w.audit_fixture(self.root,r),'FIXTURE_DIR_UNTRUSTED')
    def test_trust_nonempty_denied(self):
        r=self.provision();(r['trust']/'malicious.json').write_text('x');self.assertStop(lambda:w.audit_fixture(self.root,r),'PRIVATE_DIR_NOT_EMPTY')
    def test_site_directory_swap_denied(self):
        r=self.provision();old=r['site'].with_name('old-site');r['site'].rename(old);r['site'].mkdir(mode=0o700)
        self.assertStop(lambda:w.audit_fixture(self.root,r),'SITE_IDENTITY_CHANGED')
    def test_arbitrary_root_denied(self):
        self.assertStop(lambda:w.provision_fixture(pathlib.Path('/etc'),candidate()),'NOT_A_DISPOSABLE_FIXTURE')
    def test_similar_named_root_under_nontmp_denied(self):
        self.assertStop(lambda:w.sandbox_root(pathlib.Path('/root')/self.root.name),'NOT_A_DISPOSABLE_FIXTURE')
    def test_root_symlink_denied(self):
        target=self.root.with_name(self.root.name+'-link');target.symlink_to(self.root, target_is_directory=True)
        self.addCleanup(target.unlink);self.assertStop(lambda:w.sandbox_root(target),'FIXTURE_ROOT_UNSAFE')
    def test_root_permissive_denied(self):
        self.root.chmod(0o755);self.assertStop(lambda:w.provision_fixture(self.root,candidate()),'FIXTURE_ROOT_UNSAFE')
    def test_unknown_profile_keys_denied(self):
        c=candidate();c['service_enabled']=True;self.assertStop(lambda:w.provision_fixture(self.root,c),'PROFILE_SCHEMA_FIELDS_INVALID')
    def test_enabling_install_denied(self):
        c=candidate();c['auto_install_enabled']=True;self.assertStop(lambda:w.provision_fixture(self.root,c),'PROFILE_INSTALL_MUST_STAY_DISABLED')
    def test_staging_denied(self):
        c=candidate();c['site_root']='/www/wwwroot/baramastaging.tonsamith.com';self.assertStop(lambda:w.provision_fixture(self.root,c),'STAGING_PROFILE_FORBIDDEN')
    def test_production_denied(self):
        c=candidate();c['site_root']='/www/wwwroot/baramarealestate.tonsamith.com';self.assertStop(lambda:w.provision_fixture(self.root,c),'PRODUCTION_PROFILE_FORBIDDEN')
    def test_rogue_private_trust_denied(self):
        c=candidate();c['trust_dir']='/www/wwwroot/customer/trust';self.assertStop(lambda:w.provision_fixture(self.root,c),'TRUST_DIR_NOT_ROOT_CONTROLLED')
    def test_malformed_path_denied(self):
        c=candidate();c['site_root']='/www/wwwroot/../foo';self.assertStop(lambda:w.provision_fixture(self.root,c),'SITE_PATH_INVALID')
    def test_preexisting_profile_path_is_denied(self):
        c=candidate();path=w.fixture_path(self.root,'/etc/barama/oneclick/profiles/'+ID+'.json');w.private_mkdir(self.root,'etc/barama/oneclick/profiles');path.write_text('cannot overwrite')
        self.assertStop(lambda:w.provision_fixture(self.root,c),'PROFILE_ALREADY_PROVISIONED')
    def test_privilege_flag_tamper_denied(self):
        r=self.provision();r['service_installed']=True;self.assertStop(lambda:w.audit_fixture(self.root,r),'SIMULATION_ESCALATION_DENIED')
    def test_source_no_actual_worker_or_subprocess(self):
        s=(HERE.parent/'tools'/'oneclick'/'portable_provisioning.py').read_text()
        for token in ('systemctl','subprocess','ssh','sudo','exec(',"Path('/etc/systemd"):self.assertNotIn(token,s)
    def test_no_path_escaping(self):
        for v in ('/../../etc','//etc/barama','/etc/../root'):
            self.assertStop(lambda:w.fixture_path(self.root,v),'FIXTURE_PATH_INVALID')

if __name__=='__main__':unittest.main()
