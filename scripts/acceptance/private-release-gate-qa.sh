#!/usr/bin/env bash
# Synthetic acceptance metadata only. No customer data, databases or hardware.
set -Eeuo pipefail
set +x
umask 077
TMP="$(mktemp -d /tmp/barama-private-qa.XXXXXXXX)"
DIR="$TMP/barama-private-rehearsal-evidence"
mkdir -m 700 "$DIR"
trap 'rm -rf -- "$TMP"' EXIT
export APP_ENV=testing
export BARAMA_REHEARSAL_APPROVED=TWO_DISPOSABLE_CLONES_VERIFIED
export BARAMA_EXPECTED_GIT_SHA="$(git rev-parse HEAD)"
python3 - "$DIR" "$BARAMA_EXPECTED_GIT_SHA" <<'PY'
import hashlib
import json
import pathlib
import sys

directory = pathlib.Path(sys.argv[1])
commit = sys.argv[2]
controls = [
    "original_backup_two_restores",
    "legacy_customer_data_reconciliation",
    "private_media_sha256",
    "historical_financial_semantics",
    "historical_permission_and_mfa_review",
    "financial_independent_approval",
    "security_independent_approval",
    "privacy_independent_approval",
    "physical_aapanel_nginx_php",
    "physical_cpanel_apache_php",
    "physical_iphone_safari",
    "physical_android_chrome",
    "physical_tablet_browser",
    "apple_google_calendar_import",
    "stacked_pr_reconciliation",
]
checks = {}
for i, control in enumerate(controls):
    filename = "synthetic-control-%02d.txt" % i
    data = ("Synthetic fixture %s, not actual physical acceptance\n" % control).encode()
    (directory / filename).write_bytes(data)
    checks[control] = {
        "status": "PASS",
        "evidence_file": filename,
        "evidence_sha256": hashlib.sha256(data).hexdigest(),
    }
base = {
    "schema": "barama-private-acceptance-v1",
    "scope": "isolated_qa_only",
    "commit": commit,
    "source_backup_sha256": "a" * 64,
    "operator_id": "synthetic_operator",
    "reviewer_id": "synthetic_reviewer",
    "checks": checks,
}
def write(name, value):
    (directory / name).write_text(json.dumps(value), encoding="utf-8")

write("complete.json", base)
import copy
partial = copy.deepcopy(base)
partial["checks"]["physical_iphone_safari"] = {"status": "NOT_TESTED"}
write("partial.json", partial)
same = copy.deepcopy(base)
same["reviewer_id"] = same["operator_id"]
write("self-reviewed.json", same)
duplicate = copy.deepcopy(base)
duplicate["checks"]["physical_cpanel_apache_php"]["evidence_file"] = \
    duplicate["checks"]["physical_aapanel_nginx_php"]["evidence_file"]
write("duplicate.json", duplicate)
traversal = copy.deepcopy(base)
traversal["checks"]["private_media_sha256"]["evidence_file"] = "../private"
write("traversal.json", traversal)
badsha = copy.deepcopy(base)
badsha["checks"]["historical_financial_semantics"]["evidence_sha256"] = "b" * 64
write("bad-hash.json", badsha)
missing = copy.deepcopy(base)
del missing["checks"]["privacy_independent_approval"]
write("missing.json", missing)
PY

verify() {
    php support/barama_private_release_gate.php --review --manifest="$DIR/$1"
}
verify complete.json > "$TMP/complete.log"
grep -Fx "PRIVATE_ACCEPTANCE_EVIDENCE=STRUCTURALLY_COMPLETE_NOT_INDEPENDENTLY_VERIFIED" "$TMP/complete.log"
grep -Fx "PRODUCTION_RELEASE=BLOCKED" "$TMP/complete.log"

verify partial.json > "$TMP/partial.log" && exit 9 || result=$?
test "$result" -eq 2
grep -Fx "PRIVATE_ACCEPTANCE_EVIDENCE=INCOMPLETE" "$TMP/partial.log"

for case in self-reviewed duplicate traversal bad-hash missing; do
    if verify "$case.json" > "$TMP/$case.log" 2>&1; then
        echo "FAILED=INVALID_EVIDENCE_ACCEPTED"; exit 9
    fi
    grep -Fx "PRIVATE_ACCEPTANCE_EVIDENCE=DENIED" "$TMP/$case.log" >/dev/null
done

if APP_ENV=production verify complete.json > "$TMP/prod.log" 2>&1; then
    echo "FAILED=PRODUCTION_EVIDENCE_GATE_ALLOWED"; exit 9
fi
grep -Fx "PRIVATE_ACCEPTANCE_EVIDENCE=DENIED" "$TMP/prod.log" >/dev/null
echo "PRIVATE_RELEASE_EVIDENCE_NEGATIVE_TESTS=PASS"
echo "REAL_DATA_HOST_AND_DEVICE_TESTS=NOT_PERFORMED"
