#!/usr/bin/env python3
"""Restore ONLY the verified supporting files excluded by the code-only capture.

Reads the current live/staging webroots or existing customer ZIPs, verifies
each exact SHA-256, and pushes a new GitHub main commit via SSH (no force).
Does not write to production or staging webroots or open DB/license state.
"""
import argparse
import hashlib
from pathlib import Path
import subprocess
import sys
import zipfile

APPROVED = {
    "application/bin/security-center-phase6.php": "828993bc34ba274b3ff4ce4c031c938a2f6cce9d73d0ce33429276557810e2fc",
    "application/resources/data/iso-3166-2-subdivisions-v110.json": "ad5852e6d595c25f62b5c2951038b7ae1d7d96574c0b07c0a216355bdb6088de",
    "application/support/BaramaChangeConfirmationPage.php": "98ed5806b8232d1fd3e0af52d64ad09a6bf9a2b195accb9072ca67208acd8d78",
    "application/support/BaramaSessionExpiredPage.php": "1df96af32879aba0c0b8219c7553c48a21ae48475b940f82e9bf8534c590565a",
    "assets/images/barama-homepage-hero-v1.webp": "6fac578544abb683f39fec25e70c8c6bc18297cc08cb785a0d9dde73ff3eb0de",
    "assets/images/marketplace-asian-agent-fullbody-v116.svg": "f2e0be0b51c9b8261b7b9ea806c7a1b61be5beb8a6fe54cca8bdd8f5da130e23",
    "assets/images/marketplace-asian-agent-real-v115.png": "b34b93fccd1dcd526988950c85efc194168e23e33ce25e72d6fc00f940e1673b",
    "assets/images/marketplace-asian-agent-v114.svg": "a275c3045d0119dae8993cb1508e0983cda56b23f7aeae4d50ce0b8cae34e1f0",
    "assets/images/marketplace-defaults/companies-hero-01.webp": "2c325b0bdefc1f75f0b778dec61388d490fe95813c8e26248fa13cac8e7ec227",
    "assets/images/marketplace-defaults/companies-hero-02.webp": "562c37405de018b4e32f1fb51f9d6d58748e90d05185b9e3006fcd492147ecd3",
    "assets/images/marketplace-defaults/companies-hero-03.webp": "e7daa0dc26e7779e9bb01050f0fce5a896604f813e89497a77b8d7c97e4756e8",
    "assets/images/marketplace-defaults/projects-hero-01.webp": "56a359e829d6e4a0120ac7b2c558a17113605dc1f418743bd598e25abab16ba5",
    "assets/images/marketplace-defaults/projects-hero-02.webp": "0e12a1ba450f4f96778a172edbeab34cd0ba3df6e1cb1bfcc5890ccd32eb8e8b",
    "assets/images/marketplace-defaults/projects-hero-03.webp": "9f1f1390439f10423bb3e3edd4fc8b43e3456104968c6734ab6bcb93be1d0ccf",
    "assets/images/marketplace-defaults/properties-hero-01.webp": "2e8a3f7f7abf5cd46b5de862d0ff7969b1465008df920989fc7348aec4b5bf6f",
    "assets/images/marketplace-defaults/properties-hero-02.webp": "1d819b94921808cea35b53b5fc2d7a839d55d1eaad45ba46d1ba07f6d71f2792",
    "assets/images/marketplace-defaults/properties-hero-03.webp": "07420b395d6ad81b11ceba563d6bd18d7690a73b8f243571894834e6876bdf4b",
    "assets/img/policy-center-trust-dark-v1.0.16.svg": "d52fb6874f56548e315d0d5bf9db9ecd86968f72971aa5657fdc086ebd657679",
    "assets/img/policy-center-trust-v1.0.11.svg": "66496a815195498a7c598a3ee69abca4558835e69e52e4a18b3e63f541a4104e",
    "assets/js/vendor/qrcode-v133.LICENSE.txt": "404447fec13941c9940919e8e915577289cccae3a6447061757c2a4fd72b0815",
    "assets/js/vendor/qrcode-v133.js": "e83bbac6e10c2f60617c62e61b89664e798cfbf88ac9e0839080f72214249cb8",
}

ROOT = Path("/root")
LIVE = Path("/www/wwwroot/baramarealestate.tonsamith.com")
STAGING = Path("/www/wwwroot/baramastaging.tonsamith.com")
DEST = ROOT / "Barama-Missing-Assets-Recovery-20261014"
REPOSITORY = "git@github.com:tonsamith128/Barama-Real-Estate.git"


def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def git(*args: str, cwd: Path = None) -> str:
    result = subprocess.run(
        ["git", *args], cwd=cwd, text=True, capture_output=True
    )
    if result.returncode:
        raise RuntimeError("Git command failed: " + " ".join(args[:3])
                           + "\n" + (result.stderr or result.stdout)[-1800:])
    return result.stdout.strip()


def find_sources() -> tuple[dict[str, bytes], dict[str, str]]:
    remaining = dict(APPROVED)
    found = {}
    origins = {}
    # Always read existing files; never create or modify website paths.
    for root in (LIVE, STAGING):
        for rel, expected in list(remaining.items()):
            source = root / rel
            if source.is_file() and not source.is_symlink():
                content = source.read_bytes()
                if digest(content) == expected:
                    found[rel] = content
                    origins[rel] = str(root)
                    del remaining[rel]

    # Old customer archives may retain assets no longer on disk.
    # Only approved entry names and cryptographic hashes are considered.
    archives = sorted(set(ROOT.glob("Barama*.zip"))
                      | set(STAGING.glob("Barama*.zip"))
                      | set(LIVE.glob("Barama*.zip")))
    for archive in archives:
        if not remaining:
            break
        if not zipfile.is_zipfile(archive):
            continue
        with zipfile.ZipFile(archive) as z:
            names = set(z.namelist())
            for rel, expected in list(remaining.items()):
                if rel not in names:
                    continue
                if z.getinfo(rel).file_size > 10_000_000:
                    continue
                content = z.read(rel)
                if digest(content) == expected:
                    found[rel] = content
                    origins[rel] = str(archive)
                    del remaining[rel]
    if remaining:
        print("MISSING_VERIFIED_SUPPORT_FILES=" + str(len(remaining)))
        for rel in sorted(remaining):
            print("NOT_FOUND=" + rel)
        raise RuntimeError(
            "Cannot safely restore all files from the existing server sources. "
            "Do not push an incomplete update."
        )
    return found, origins


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true",
                        help="Verify the 21 approved files are available")
    parser.add_argument("--push-main", action="store_true",
                        help="Commit verified files to GitHub main via SSH")
    args = parser.parse_args()
    if args.check == args.push_main:
        parser.error("Choose exactly one of --check or --push-main")
    files, origins = find_sources()
    print("VERIFIED_MISSING_SUPPORT_FILES=21/21 PASS")
    print("SOURCES=" + ", ".join(sorted(set(origins.values()))))
    if args.check:
        print("GITHUB_CHANGES=NONE")
        return

    if DEST.exists():
        raise RuntimeError(
            "Private recovery checkout already exists: " + str(DEST)
            + "; do not overwrite it."
        )
    DEST.mkdir(mode=0o700, parents=True)
    repo = DEST / "repo"
    git("clone", "--branch", "main", "--depth", "1", REPOSITORY, str(repo))
    old_head = git("rev-parse", "HEAD", cwd=repo)
    expected_files = set(files)
    for rel, data in files.items():
        target = repo / rel
        if target.exists():
            if not target.is_file() or digest(target.read_bytes()) != APPROVED[rel]:
                raise RuntimeError("GitHub already has differing content: " + rel)
            continue
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(data)

    git("add", "--", *sorted(expected_files), cwd=repo)
    staged = git("diff", "--cached", "--name-only", cwd=repo).splitlines()
    if set(staged) != expected_files:
        raise RuntimeError("Unexpected staged file inventory; refusing push.")
    git("diff", "--cached", "--check", cwd=repo)
    git("config", "user.name", "Ton Samith", cwd=repo)
    git("config", "user.email", "tonsamith128@users.noreply.github.com", cwd=repo)
    git("commit", "-m",
        "fix(package): restore 21 verified assets and support files omitted from source capture",
        cwd=repo)
    git("push", "origin", "HEAD:refs/heads/main", cwd=repo)
    print("GITHUB_MAIN_FAST_FORWARD_PUSH=PASS")
    print("OLD_MAIN=" + old_head)
    print("NEW_MAIN=" + git("rev-parse", "HEAD", cwd=repo))
    print("PRODUCTION_AND_STAGING_SITES_MODIFIED=NONE")


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print("RESTORE_STATUS=BLOCKED\nDETAIL=" + str(e), file=sys.stderr)
        sys.exit(1)
