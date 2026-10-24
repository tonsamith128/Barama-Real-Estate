#!/usr/bin/env python3
"""Offline author-only Barama Demo Content signer.

On a trusted Mac with an existing encrypted Ed25519 private key:
  python3 tools/demo-package-offline-sign-v100.py \
    --source /PRIVATE/BARAMA_DEMO_EXPORT \
    --key /PRIVATE/ENCRYPTED_ED25519_KEY.pem \
    --key-id publisher-key-id \
    --output /PRIVATE/barama-live-demo-v1.zip

Needs Python 'cryptography'. The passphrase is requested interactively,
never accepted on CLI or logged. Private key never leaves this machine.
"""
from __future__ import annotations
import argparse, datetime as dt, getpass, hashlib, json, os, re, stat, sys, zipfile
from pathlib import Path

PREFIX = b"TONSAMITH-BARAMA-DEMO-V1\n"
MAX_FILE = 8 * 1024 * 1024
MAX_TOTAL = 64 * 1024 * 1024

def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--source", required=True)
    ap.add_argument("--key", required=True)
    ap.add_argument("--key-id", required=True)
    ap.add_argument("--output", required=True)
    args = ap.parse_args()
    if not re.fullmatch(r"[a-z0-9][a-z0-9-]{2,90}", args.key_id):
        raise SystemExit("STOP=KEY_ID_INVALID")
    source = Path(args.source).resolve(strict=True)
    key_path = Path(args.key).resolve(strict=True)
    output = Path(args.output)
    if source.is_symlink() or not source.is_dir() or key_path.is_symlink() or not key_path.is_file():
        raise SystemExit("STOP=SOURCE_OR_KEY_UNSAFE")
    if (key_path.stat().st_mode & 0o077) != 0:
        raise SystemExit("STOP=KEY_FILE_NOT_PRIVATE_0600")
    if output.exists() or output.is_symlink():
        raise SystemExit("STOP=OUTPUT_ALREADY_EXISTS")
    payload = source / "content.json"
    if payload.is_symlink() or not payload.is_file() or payload.stat().st_size > MAX_FILE:
        raise SystemExit("STOP=CONTENT_INVALID")
    data = json.loads(payload.read_text(encoding="utf-8"))
    if data.get("schema") != "barama-demo-content-v1":
        raise SystemExit("STOP=CONTENT_SCHEMA_MISMATCH")
    members = [("content.json", payload)]
    asset_root = source / "assets"
    if asset_root.is_dir() and not asset_root.is_symlink():
        for asset in sorted(asset_root.iterdir()):
            if asset.is_symlink() or not asset.is_file():
                raise SystemExit("STOP=UNSAFE_ASSET")
            if not re.fullmatch(r"[a-f0-9]{64}\.(?:jpg|png|webp)", asset.name):
                raise SystemExit("STOP=UNEXPECTED_ASSET_FILENAME")
            members.append(("assets/" + asset.name, asset))
    if len(members) > 499:
        raise SystemExit("STOP=PACKAGE_FILE_LIMIT")
    hashes = {}
    total = 0
    for name, path in members:
        blob = path.read_bytes()
        if len(blob) > MAX_FILE:
            raise SystemExit("STOP=INDIVIDUAL_FILE_LIMIT")
        total += len(blob)
        if total > MAX_TOTAL - 2048:
            raise SystemExit("STOP=DEMO_PACKAGE_TOO_LARGE")
        digest = hashlib.sha256(blob).hexdigest()
        if name.startswith("assets/") and not name.startswith("assets/" + digest + "."):
            raise SystemExit("STOP=ASSET_FILENAME_DIGEST_MISMATCH")
        hashes[name] = digest

    from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey
    from cryptography.hazmat.primitives.serialization import load_pem_private_key
    secret = key_path.read_bytes()
    try:
        private = load_pem_private_key(secret, password=getpass.getpass("Publisher key passphrase: ").encode())
    except (ValueError, TypeError):
        raise SystemExit("STOP=PRIVATE_KEY_UNREADABLE_OR_WRONG_PASSPHRASE")
    if not isinstance(private, Ed25519PrivateKey):
        raise SystemExit("STOP=SIGNING_KEY_NOT_ED25519")

    issued = dt.datetime.now(dt.timezone.utc)
    manifest = {
        "schema": "tonsamith-barama-demo-v1",
        "publisher": "tonsamith.com",
        "product_slug": "barama-real-estate",
        "package_id": "barama-live-demo-v1",
        "key_id": args.key_id,
        "minimum_version": "0.9.24",
        "expires_at": (issued + dt.timedelta(days=120)).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "files": hashes,
    }
    canonical = json.dumps(dict(sorted(manifest.items())), ensure_ascii=False,
                           separators=(",", ":")).encode("utf-8")
    signature = private.sign(PREFIX + canonical)
    import base64
    envelope = json.dumps({
        "manifest": manifest,
        "signature": {
            "algorithm": "Ed25519",
            "key_id": args.key_id,
            "value": base64.b64encode(signature).decode("ascii"),
        }
    }, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
    previous_umask = os.umask(0o077)
    try:
        with zipfile.ZipFile(output, "x", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
            archive.writestr("envelope.json", envelope)
            for name, path in members:
                archive.write(path, arcname=name)
        os.chmod(output, 0o600)
    finally:
        os.umask(previous_umask)
    if output.stat().st_size > MAX_TOTAL:
        output.unlink()
        raise SystemExit("STOP=SIGNED_ZIP_LIMIT")
    print("SIGNED_DEMO_PACKAGE=CREATED_OFFLINE")
    print("PACKAGE_SHA256=" + hashlib.sha256(output.read_bytes()).hexdigest())
    print("PACKAGE_FILES=" + str(len(members) + 1))
    print("PUBLISHER_UPLOAD=NOT_PERFORMED")
    print("PRIVATE_KEY_EXPORTED=NO")


if __name__ == "__main__":
    main()
