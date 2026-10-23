#!/usr/bin/env python3
"""Guarded GitHub Main 0.9.25 -> Live Demo synchronization; code only."""
import argparse, datetime, fcntl, hashlib, json, os, shutil, stat, subprocess, sys, tempfile, zipfile
from pathlib import Path

REPO=Path("/root/Barama-Live-Main-0925")
ZIP=Path("/root/Barama-Live-Main-0925-source.zip")
SITE=Path("/www/wwwroot/baramarealestate.tonsamith.com")
BACKUPS=Path("/root/Barama-Live-Main-0925-Backups")
HOST="baramarealestate.tonsamith.com"
COMMIT="64650e22bf5eb5f363eef47929150561de60d9b5"
ZIP_SHA="e9a7cba5d6b8eadc2b3af5eb6a29ea184dce57c5cef5c6f917da467b39301480"
OLD="0.9.22-dev"
NEW="0.9.25"
META="application/storage/app/installation/installed.php"
PHP="/www/server/php/85/bin/php"
CONFIRM="SYNC_GITHUB_MAIN_0925_TO_LIVE_ONLY"
ROLLBACK_CONFIRM="ROLLBACK_GITHUB_MAIN_0925_LIVE_ONLY"

def require(ok, reason):
    if not ok: raise RuntimeError(reason)
def sha(data): return hashlib.sha256(data).hexdigest()
def digest(path):
    h=hashlib.sha256()
    with open(path,"rb") as f:
        for part in iter(lambda:f.read(1048576),b""): h.update(part)
    return h.hexdigest()
def run(argv, timeout=30):
    p=subprocess.run(argv,stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=timeout,check=False)
    require(p.returncode==0,"COMMAND_FAILED_"+Path(argv[0]).name)
    return p.stdout
def allowed(rel):
    if not isinstance(rel,str) or not rel or rel.startswith("/") or "\\" in rel or len(rel)>512:
        return False
    if any(x in ("",".","..") for x in rel.split("/")) or any(ord(x)<32 for x in rel):
        return False
    if rel==META or rel==".env" or rel.startswith(("application/storage/","assets/uploads/","assets/media/","assets/images/uploads/","assets/images/user/")):
        return False
    if rel.startswith("application/config/") and rel not in ("application/config/update-feed-v2.json","application/config/update-publisher-v2-keys.json"):
        return False
    return True
def target(rel, make=False):
    require(allowed(rel) or rel==META,"UNAUTHORIZED_PATH_"+rel)
    p=SITE
    for comp in rel.split("/")[:-1]:
        p=p/comp
        require(not p.is_symlink(),"SYMLINK_PARENT_"+rel)
        if not p.exists():
            if make: p.mkdir(mode=0o755)
            else: continue
        require(p.is_dir(),"NOT_A_DIRECTORY_"+rel)
    out=SITE/rel
    require(not out.is_symlink() and (not out.exists() or out.is_file()),"UNSAFE_DESTINATION_"+rel)
    return out
def render_metadata(path):
    code=r"""define("BARAMA_BOOTSTRAP",true);$d=require $argv[1];
if(!is_array($d)||($d["version"]??null)!==$argv[2])exit(5);
$d["version"]=$argv[3];
echo "<?php\n\ndeclare(strict_types=1);\n\nif (!defined('BARAMA_BOOTSTRAP')) {\n http_response_code(404);\n exit;\n}\n\nreturn ".var_export($d,true).";\n";"""
    return run([PHP,"-r",code,str(path),OLD,NEW])
def meta_version():
    code='define("BARAMA_BOOTSTRAP",true);$d=require $argv[1];if(!is_array($d)||!is_string($d["version"]??null))exit(6);echo $d["version"];'
    return run([PHP,"-r",code,str(SITE/META)]).decode().strip()
def health():
    p=subprocess.run(["curl","-ksS","--noproxy","*","--resolve",HOST+":443:127.0.0.1","--connect-timeout","6","--max-time","20","-o","/dev/null","-w","%{http_code}","https://"+HOST+"/"],capture_output=True,timeout=27)
    require(p.returncode==0 and p.stdout.strip()==b"200","LIVE_HTTP_NOT_200")
def load_release():
    require(os.geteuid()==0,"ROOT_REQUIRED")
    require(REPO.is_dir() and not REPO.is_symlink(),"GITHUB_CHECKOUT_MISSING")
    require(run(["git","-C",str(REPO),"rev-parse","HEAD"]).decode().strip()==COMMIT,"GITHUB_COMMIT_MISMATCH")
    require((REPO/"VERSION").read_text().strip()==NEW,"GITHUB_VERSION_MISMATCH")
    require(ZIP.is_file() and not ZIP.is_symlink() and digest(ZIP)==ZIP_SHA,"SOURCE_ZIP_SHA_MISMATCH")
    require(SITE.is_dir() and not SITE.is_symlink() and SITE.resolve()==SITE,"LIVE_ROOT_UNSAFE")
    require(Path(PHP).is_file(),"PHP_BINARY_MISSING")
    raw=run(["git","-C",str(REPO),"ls-tree","-rz","HEAD"])
    tracked={}
    for line in raw.split(b"\0"):
        if not line:continue
        left,rel=line.split(b"\t",1)
        mode,kind,gitsha=left.decode().split()
        tracked[rel.decode()]=(mode,kind,gitsha)
    with zipfile.ZipFile(ZIP) as archive:
        items=[x for x in archive.infolist() if not x.is_dir()]
        require(len(items)==715,"UNEXPECTED_PACKAGE_FILE_COUNT")
        contents={}
        for entry in items:
            rel=entry.filename
            require(rel not in contents and allowed(rel) and rel in tracked,"UNTRUSTED_PACKAGE_PATH_"+rel)
            mode,kind,blob=tracked[rel]
            require(mode=="100644" or mode=="100755","GIT_SOURCE_NOT_REGULAR_"+rel)
            require(kind=="blob" and (entry.external_attr>>16)&0o170000 in (0,stat.S_IFREG),"NONREGULAR_ARCHIVE_FILE_"+rel)
            data=archive.read(entry)
            gitblob=hashlib.sha1(b"blob "+str(len(data)).encode()+b"\0"+data).hexdigest()
            require(gitblob==blob,"ARCHIVE_DIFFERS_FROM_GITHUB_MAIN_"+rel)
            contents[rel]=sha(data)
        require(contents.get("VERSION")==sha(b"0.9.25\n"),"PACKAGE_VERSION_INVALID")
    return contents
def baseline():
    require((SITE/"VERSION").is_file() and not (SITE/"VERSION").is_symlink(),"LIVE_VERSION_UNSAFE")
    version=(SITE/"VERSION").read_text().strip()
    require(version==OLD,"EXPECTED_OLD_VERSION_"+version)
    require((SITE/META).is_file() and not (SITE/META).is_symlink(),"INSTALLATION_METADATA_UNSAFE")
    require(meta_version()==OLD,"INSTALLED_VERSION_MISMATCH")
    vault=SITE.parent/(".barama-private-updates-"+hashlib.sha256(str(SITE).encode()).hexdigest()[:18])
    require(not vault.is_symlink(),"PRIVATE_VAULT_SYMLINK")
    require(not (vault/"active.json").exists(),"UNFINISHED_AUTO_UPDATE")
def write(path,data,mode,uid,gid):
    require(not path.is_symlink(),"WRITE_TARGET_SYMLINK")
    fd,tmp=tempfile.mkstemp(prefix=".barama-live-sync-",dir=path.parent)
    try:
        with os.fdopen(fd,"wb") as f:
            f.write(data);f.flush();os.fsync(f.fileno())
        os.chmod(tmp,mode);os.chown(tmp,uid,gid)
        os.replace(tmp,path)
    finally:
        if os.path.lexists(tmp):os.unlink(tmp)
def journal(path,info):
    tmp=path/"journal.tmp"
    tmp.write_text(json.dumps(info,indent=2,sort_keys=True)+"\n")
    os.chmod(tmp,0o600)
    os.replace(tmp,path/"journal.json")
def prepare(changes):
    require(BACKUPS.parent.is_dir() and not BACKUPS.parent.is_symlink(),"BACKUP_PARENT_UNSAFE")
    BACKUPS.mkdir(mode=0o700,exist_ok=True)
    require(not BACKUPS.is_symlink() and BACKUPS.stat().st_uid==0 and stat.S_IMODE(BACKUPS.stat().st_mode)==0o700,"BACKUP_ROOT_UNSAFE")
    name=datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ")+"-"+os.urandom(4).hex()
    location=BACKUPS/name
    location.mkdir(mode=0o700)
    original={}
    for rel in [*changes,META]:
        path=target(rel)
        if path.exists():
            st=path.stat()
            old={"exists":True,"sha":digest(path),"mode":stat.S_IMODE(st.st_mode),"uid":st.st_uid,"gid":st.st_gid}
            dest=location/"original"/rel
            dest.parent.mkdir(parents=True,exist_ok=True,mode=0o700)
            shutil.copyfile(path,dest)
            os.chmod(dest,0o600)
            require(digest(dest)==old["sha"],"BACKUP_CORRUPT_"+rel)
        else:
            old={"exists":False,"sha":None,"mode":0o644,"uid":0,"gid":0}
        original[rel]=old
    info={"schema":"barama-live-github0925-recovery-v1","site":str(SITE),"commit":COMMIT,"from":OLD,"to":NEW,"changes":changes,"original":original,"state":"prepared"}
    journal(location,info)
    print("BACKUP_PATH="+str(location),flush=True)
    return location,info
def restore(location,info,expected):
    require(location.parent==BACKUPS and location.is_dir() and not location.is_symlink(),"INVALID_BACKUP_LOCATION")
    for rel in [META,*reversed(info["changes"])]:
        dest=target(rel,make=True)
        original=info["original"][rel]
        if dest.is_file():
            current=digest(dest)
            require(current in (original["sha"],expected.get(rel)),"ROLLBACK_CONCURRENT_CHANGE_"+rel)
        if original["exists"]:
            backup=location/"original"/rel
            require(backup.is_file() and not backup.is_symlink() and digest(backup)==original["sha"],"ROLLBACK_BACKUP_INVALID_"+rel)
            if not dest.exists() or digest(dest)!=original["sha"]:
                write(dest,backup.read_bytes(),original["mode"],original["uid"],original["gid"])
        elif dest.exists():
            dest.unlink()
    info["state"]="rolled_back"
    journal(location,info)
    print("ROLLBACK=COMPLETE",flush=True)
def deploy(contents):
    baseline();health()
    changes=[rel for rel,h in contents.items() if not (target(rel).is_file() and digest(target(rel))==h)]
    print("SOURCE_FILES="+str(len(contents))+" FILES_TO_SYNC="+str(len(changes)),flush=True)
    # Generate metadata using the same canonical formatter as rollback.
    new_meta=render_metadata(SITE/META)
    require(b"'version' => '0.9.25'" in new_meta,"NEW_METADATA_GENERATION_INVALID")
    if not changes: require(False,"VERSION_UNEXPECTEDLY_UNCHANGED")
    location,info=prepare(changes)
    try:
        with zipfile.ZipFile(ZIP) as archive:
            for rel in changes:
                if rel=="VERSION":continue
                d=target(rel,make=True)
                old=info["original"][rel]
                require((d.is_file() and digest(d)==old["sha"]) if old["exists"] else not d.exists(),"CONCURRENT_SOURCE_CHANGE_"+rel)
                write(d,archive.read(rel),old["mode"],old["uid"],old["gid"])
                require(digest(d)==contents[rel],"DEPLOYED_SOURCE_SHA_MISMATCH_"+rel)
        meta=target(META)
        old=info["original"][META]
        require(digest(meta)==old["sha"],"CONCURRENT_METADATA_CHANGE")
        write(meta,new_meta,old["mode"],old["uid"],old["gid"])
        if "VERSION" in changes:
            d=target("VERSION")
            old=info["original"]["VERSION"]
            require(digest(d)==old["sha"],"CONCURRENT_VERSION_CHANGE")
            write(d,b"0.9.25\n",old["mode"],old["uid"],old["gid"])
        require(meta_version()==NEW and (SITE/"VERSION").read_text().strip()==NEW,"VERSION_AND_METADATA_MISMATCH")
        for rel,h in contents.items():
            require(target(rel).is_file() and digest(target(rel))==h,"POST_INSTALL_SOURCE_MISMATCH_"+rel)
        health()
        info["state"]="complete";journal(location,info)
        print("DEPLOY_STATUS=PASS")
        print("GITHUB_MAIN_ALIGNED="+COMMIT)
        print("VERSION_AND_INSTALLED_METADATA_ALIGNED=0.9.25")
        print("SOURCE_FILES_VERIFIED=715")
        print("DB_MIGRATIONS_RUN=NO")
        print("MEDIA_LICENSE_RUNTIME_CONFIG_MODIFIED=NO")
        print("AUTO_UPDATER_FUTURE_INSTALL_OPERATIONAL=NOT_CLAIMED")
    except BaseException:
        try:restore(location,info,{**contents,META:sha(new_meta)})
        except BaseException as error:print("MANUAL_RECOVERY_REQUIRED="+str(error),file=sys.stderr)
        raise
def main():
    p=argparse.ArgumentParser()
    group=p.add_mutually_exclusive_group(required=True)
    group.add_argument("--check",action="store_true")
    group.add_argument("--deploy",action="store_true")
    group.add_argument("--rollback",default="")
    p.add_argument("--confirm",default="")
    a=p.parse_args()
    contents=load_release()
    if a.check:
        baseline();health()
        changed=[r for r,h in contents.items() if not (target(r).is_file() and digest(target(r))==h)]
        print("GITHUB_MAIN="+COMMIT)
        print("SOURCE_FILES_VERIFIED="+str(len(contents)))
        print("FILES_REQUIRING_SYNC="+str(len(changed)))
        print("CHANGED_FILE_SAMPLE="+", ".join(changed[:16]))
        print("PREFLIGHT=PASS LIVE_FILES_CHANGED=NO")
        return
    require(a.confirm==(CONFIRM if a.deploy else ROLLBACK_CONFIRM),"EXPLICIT_CONFIRMATION_REQUIRED")
    BACKUPS.mkdir(mode=0o700,exist_ok=True)
    lock=BACKUPS/"production.lock"
    fd=os.open(lock,os.O_CREAT|os.O_RDWR|os.O_NOFOLLOW,0o600)
    try:
        fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
        if a.deploy:deploy(contents)
        else:
            require("/" not in a.rollback and a.rollback not in ("",".",".."),"BACKUP_ID_INVALID")
            path=BACKUPS/a.rollback
            info=json.loads((path/"journal.json").read_text())
            require(info.get("schema")=="barama-live-github0925-recovery-v1" and info.get("commit")==COMMIT and info.get("site")==str(SITE),"ROLLBACK_JOURNAL_INVALID")
            require(meta_version()==NEW,"ROLLBACK_INSTALLED_VERSION_CHANGED")
            old_meta=path/"original"/META
            require(old_meta.is_file() and not old_meta.is_symlink(),"ROLLBACK_METADATA_BACKUP_MISSING")
            restore(path,info,{**contents,META:sha(render_metadata(old_meta))})
            require(meta_version()==OLD and (SITE/"VERSION").read_text().strip()==OLD,"ROLLBACK_VERSION_MISMATCH")
            health()
    finally:
        fcntl.flock(fd,fcntl.LOCK_UN);os.close(fd)
if __name__=="__main__":
    try:main()
    except Exception as e:
        print("STOP="+str(e),file=sys.stderr)
        sys.exit(2)
