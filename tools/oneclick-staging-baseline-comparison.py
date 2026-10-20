#!/usr/bin/env python3
"""READ ONLY: compare reviewed publisher source inventory against installed staging files.

Never writes a recipe, ZIP, backup, signing key, site file or database.
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import sys

STAGING = Path("/www/wwwroot/baramastaging.tonsamith.com")
FIXTURE = re.compile(r"/tmp/barama-release-baseline-fixture-[a-f0-9]{16}/site\Z")
HEX64 = re.compile(r"[a-f0-9]{64}\Z")
HEX40 = re.compile(r"[a-f0-9]{40}\Z")
VER = re.compile(r"[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?\Z")
SAFE = re.compile(r"[A-Za-z0-9_./-]{1,512}\Z")
BLOCK = {"storage","config","vendor","node_modules","uploads","private",".git",".env",".htaccess",".user.ini"}

class Refused(Exception):
    pass

def allowed(path):
    if not isinstance(path,str) or not SAFE.fullmatch(path) or path == "VERSION":
        return False
    seg=path.split("/")
    if any(not s or s in (".","..") or s.startswith(".") or s.lower() in BLOCK for s in seg):
        return False
    return (path in ("index.php","updater/index.php","installer/index.php")
      or (path.startswith(("application/app/","application/routes/","application/resources/views/")) and path.endswith(".php"))
      or (path.startswith("assets/css/") and path.endswith(".css"))
      or (path.startswith("assets/js/") and path.endswith(".js")))

def read_regular(path,maxsize):
    if path.is_symlink() or not path.is_file():
        raise Refused("UNTRUSTED_OR_MISSING_FILE")
    fd=os.open(path,os.O_RDONLY|getattr(os,"O_NOFOLLOW",0))
    try:
        s=os.fstat(fd)
        if not stat.S_ISREG(s.st_mode) or s.st_size<1 or s.st_size>maxsize:
            raise Refused("FILE_TYPE_OR_SIZE_INVALID")
        data=b""
        while len(data)<=maxsize:
            chunk=os.read(fd,min(1024*1024,maxsize+1-len(data)))
            if not chunk:break
            data+=chunk
        if len(data)!=s.st_size:
            raise Refused("FILE_CHANGED_DURING_READ")
        return data
    finally:
        os.close(fd)

def sha_regular(path,maxsize):
    if path.is_symlink() or not path.is_file():
        raise Refused("UNTRUSTED_OR_MISSING_FILE")
    fd=os.open(path,os.O_RDONLY|getattr(os,"O_NOFOLLOW",0))
    try:
        before=os.fstat(fd)
        if not stat.S_ISREG(before.st_mode) or before.st_size<0 or before.st_size>maxsize:
            raise Refused("FILE_TYPE_OR_SIZE_INVALID")
        size=0
        digest=hashlib.sha256()
        while True:
            chunk=os.read(fd,1024*1024)
            if not chunk: break
            digest.update(chunk)
            size+=len(chunk)
            if size>maxsize: raise Refused("FILE_TOO_LARGE")
        after=os.fstat(fd)
        if size!=before.st_size or after.st_size!=before.st_size or after.st_mtime_ns!=before.st_mtime_ns:
            raise Refused("FILE_CHANGED_DURING_HASH")
        return digest.hexdigest()
    finally:
        os.close(fd)

def main():
    parser=argparse.ArgumentParser(description="Staging read-only baseline digests, no release approval")
    parser.add_argument("--inventory",required=True,help="Exact JSON output from read-only source inventory")
    parser.add_argument("--site-root",default=str(STAGING),
                        help="Only exact staging root; /tmp random site permitted in fixture QA")
    args=parser.parse_args()
    site=Path(args.site_root)
    isfixture=bool(FIXTURE.fullmatch(str(site)))
    if site!=STAGING and not isfixture:
        raise Refused("STAGING_SITE_ONLY")
    if isfixture and os.getenv("BARAA_RELEASE_BASELINE_FIXTURE")!="DISPOSABLE_SITE_ONLY":
        raise Refused("DISPOSABLE_FIXTURE_EXPLICIT_ENV_REQUIRED")
    if site.is_symlink() or not site.is_dir() or site.resolve()!=site:
        raise Refused("STAGING_ROOT_UNTRUSTED")
    manifestpath=Path(args.inventory).absolute()
    raw=read_regular(manifestpath,1024*1024)
    def unique(pairs):
        obj={}
        for name,value in pairs:
            if name in obj:raise ValueError("DUPLICATE_JSON_FIELD")
            obj[name]=value
        return obj
    try:
        m=json.loads(raw.decode("utf-8"),object_pairs_hook=unique)
    except (UnicodeError,ValueError) as ex:
        raise Refused("SOURCE_INVENTORY_INVALID") from ex
    fields={"schema","product_slug","source_commit","source_version","proposed_target_version",
            "status","source_files","baseline_hashes_verified","publisher_signature_created",
            "owner_consent_created","recovery_checkpoint_verified","release_ready"}
    if not isinstance(m,dict) or set(m)!=fields or m["schema"]!="barama-v2-release-source-inventory-v1" \
       or m["product_slug"]!="barama-real-estate" or not isinstance(m["source_commit"],str) \
       or not HEX40.fullmatch(m["source_commit"]) or m["status"]!="REVIEW_ONLY_NOT_INSTALLABLE" \
       or any(m[k] is not False for k in ("baseline_hashes_verified","publisher_signature_created",
               "owner_consent_created","recovery_checkpoint_verified","release_ready")) \
       or not isinstance(m["source_version"],str) or not VER.fullmatch(m["source_version"]) \
       or not isinstance(m["proposed_target_version"],str) or not VER.fullmatch(m["proposed_target_version"]) \
       or not isinstance(m["source_files"],list) or not 1<=len(m["source_files"])<=8000:
        raise Refused("SOURCE_INVENTORY_UNTRUSTED")
    vpath=site/"VERSION"
    installed=read_regular(vpath,128).decode("utf-8").strip()
    if installed!=m["source_version"]:
        raise Refused("STAGING_VERSION_DIFFERS_FROM_REVIEWED_SOURCE")
    previous=set()
    entries=[]
    for item in m["source_files"]:
        if not isinstance(item,dict) or set(item)!={"path","source_sha256","bytes",
                "installed_sha256","installed_baseline"}:
            raise Refused("INVENTORY_ROW_INVALID")
        rel=item["path"]
        if not allowed(rel) or rel.lower() in previous or not isinstance(item["source_sha256"],str) \
           or not HEX64.fullmatch(item["source_sha256"]) or not isinstance(item["bytes"],int) \
           or not 1<=item["bytes"]<=10000000 or item["installed_sha256"] is not None \
           or item["installed_baseline"]!="NOT_CHECKED_AGAINST_STAGING":
            raise Refused("INVENTORY_ROW_INVALID")
        previous.add(rel.lower())
        target=site
        for part in rel.split("/"):
            target=target/part
            if target.is_symlink():
                raise Refused("INSTALLED_PATH_SYMLINK")
        if not target.is_file():
            entries.append({"path":rel,"source_sha256":item["source_sha256"],
               "bytes":item["bytes"],"installed_sha256":None,"staging_status":"FILE_ABSENT_BUILDER_UNSUPPORTED"})
            continue
        digest=sha_regular(target,2147483648)
        entries.append({"path":rel,"source_sha256":item["source_sha256"],
            "bytes":item["bytes"],"installed_sha256":digest,
            "staging_status":("NO_CONTENT_CHANGE" if digest==item["source_sha256"]
                              else "DIFFERENT_SOURCE_CONTENT_REQUIRES_OWNER_REVIEW")})
    output={"schema":"barama-staging-source-baseline-comparison-v1",
       "source_inventory_sha256":hashlib.sha256(raw).hexdigest(),
       "source_commit":m["source_commit"],"installed_version":installed,
       "proposed_target_version":m["proposed_target_version"],
       "files":entries,"status":"HASHES_READ_ONLY_NOT_SIGNED_OR_APPROVED",
       "database_accessed":False,"website_writes":False,
       "recipe_approved":False,"install_enabled":False}
    print(json.dumps(output,indent=2))
if __name__=="__main__":
    try:main()
    except (Refused,OSError,ValueError,UnicodeError) as e:
        print("STOP="+str(e),file=sys.stderr)
        sys.exit(1)
