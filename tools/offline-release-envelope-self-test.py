#!/usr/bin/env python3
"""Disposable Ed25519 signing fixtures. No real publisher keys or server access."""
import base64
import hashlib
import json
import os
from pathlib import Path
import subprocess
import tempfile
import zipfile

script = Path(__file__).with_name('offline-release-envelope.py')
with tempfile.TemporaryDirectory(prefix='barama-offline-envelope-test-') as tmp:
    root = Path(tmp)
    key = root/'fixture-private.pem'
    catalog = root/'keys.json'
    archive = root/'release.zip'
    output = root/'envelope.json'
    subprocess.run(['openssl','genpkey','-algorithm','ED25519','-out',str(key)],
                   check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    key.chmod(0o600)
    der = subprocess.check_output(['openssl','pkey','-in',str(key),'-pubout','-outform','DER'],
                                  stderr=subprocess.DEVNULL)
    assert len(der) == 44
    raw = der[-32:]
    catalog.write_text(json.dumps({'format':'tonsamith-publisher-keys-v2','keys':{
        'fixture-publisher-key':{'public':base64.b64encode(raw).decode(),'status':'active'}}}))
    with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
        z.writestr('VERSION','0.9.23')
        z.writestr('assets/offline-test.css','body{color:blue}')
    argv = ['python3',str(script),'--private-key',str(key),'--catalog',str(catalog),
            '--archive',str(archive),'--version','0.9.23',
            '--release-id','fixture-release-0.9.23','--key-id','fixture-publisher-key',
            '--expect-catalog-sha256',hashlib.sha256(catalog.read_bytes()).hexdigest(),
            '--expect-public-key-sha256',hashlib.sha256(raw).hexdigest(),'--output',str(output)]
    # Explicitly clear SSH flags for disposable test operation only.
    env = os.environ.copy()
    for name in ('SSH_CONNECTION','SSH_CLIENT','GITHUB_ACTIONS'):
        env.pop(name, None)
    r = subprocess.run(argv,capture_output=True,text=True,env=env)
    assert r.returncode == 0, r.stderr
    signed = json.loads(output.read_text())
    m = signed['manifest']
    canonical = json.dumps(m,sort_keys=True,separators=(',',':'),ensure_ascii=False).encode()
    msg = root/'canonical.bin'
    sig = root/'signature.bin'
    pub = root/'public.pem'
    msg.write_bytes(b'TONSAMITH-RELEASE-MANIFEST-V2\n'+canonical)
    sig.write_bytes(base64.b64decode(signed['signature']['value']))
    subprocess.run(['openssl','pkey','-in',str(key),'-pubout','-out',str(pub)],
                   check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    assert subprocess.run(['openssl','pkeyutl','-verify','-pubin','-inkey',str(pub),
                           '-rawin','-in',str(msg),'-sigfile',str(sig)],
                          capture_output=True).returncode == 0
    assert m['package_sha256'] == hashlib.sha256(archive.read_bytes()).hexdigest()
    assert output.stat().st_mode & 0o777 == 0o600
    print('DISPOSABLE_ED25519_SIGN_AND_VERIFY=PASS')
    print('CANONICAL_MANIFEST_AND_ARCHIVE_SHA256=PASS')
    # CI verifies interoperability with the actual, unmodified PHP application verifier.
    repo = Path(__file__).resolve().parent.parent
    feed = repo/'application/app/Services/Updates/TrustedReleaseFeed.php'
    inspector = repo/'application/app/Services/Updates/SignedReleaseInspector.php'
    if feed.is_file() and inspector.is_file():
        site = root/'site'
        conf = site/'application/config'
        conf.mkdir(parents=True)
        (site/'VERSION').write_text('0.9.22-dev\n')
        (conf/'update-publisher-v2-keys.json').write_bytes(catalog.read_bytes())
        php = r'''
$site=$argv[1]; define('BASE_PATH',$site); define('APP_PATH',$site.'/application');
require $argv[4]; require $argv[5];
$env=json_decode(file_get_contents($argv[2]),true,16,JSON_THROW_ON_ERROR);
$v=\Barama\Services\Updates\TrustedReleaseFeed::verifyEnvelope($env,'0.9.22-dev','stable');
$report=\Barama\Services\Updates\SignedReleaseInspector::inspectPaths($argv[2],$argv[3],'0.9.22-dev');
if ($v!=='0.9.23' || $report['version']!=='0.9.23' || $report['files']!==2) exit(1);
echo 'BARAMA_REAL_PHP_ED25519_AND_ARCHIVE=PASS'.PHP_EOL;
'''
        check = subprocess.run(['php','-r',php,str(site),str(output),str(archive),str(feed),str(inspector)],
                               capture_output=True,text=True)
        assert check.returncode == 0, check.stderr
        print('REAL_PHP_SIGNED_RELEASE_INSPECTOR=PASS')
    else:
        if os.environ.get('GITHUB_ACTIONS') == 'true':
            raise AssertionError('CI_REQUIRES_REAL_PHP_VERIFIER')
    assert subprocess.run(argv,capture_output=True,env=env).returncode != 0
    catalog.write_text(json.dumps({'format':'tonsamith-publisher-keys-v2','keys':{
        'fixture-publisher-key':{'public':base64.b64encode(b'0'*32).decode(),'status':'active'}}}))
    assert subprocess.run(argv[:-1]+[str(root/'another.json')],capture_output=True,env=env).returncode != 0
    print('FAIL_CLOSED_KEY_MISMATCH_AND_NO_OVERWRITE=PASS')
    catalog.write_text(json.dumps({'format':'tonsamith-publisher-keys-v2','keys':{
        'fixture-publisher-key':{'public':base64.b64encode(raw).decode(),'status':'active'}}}))
    with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
        z.writestr('VERSION','0.9.23')
        z.writestr('application/config/license.php','unsafe')
    assert subprocess.run(argv[:-1]+[str(root/'evil.json')],capture_output=True,env=env).returncode != 0
    print('PROTECTED_ARCHIVE_PATH_REJECTED=PASS')
    print('REAL_PUBLISHER_KEYS_ACCESSED=NO')
