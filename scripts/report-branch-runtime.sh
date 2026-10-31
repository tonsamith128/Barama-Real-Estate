#!/usr/bin/env bash
set -Eeuo pipefail
# Read-only report: fetch remote refs before execution.
git for-each-ref --format='%(refname)' refs/remotes/origin | while IFS= read -r ref; do
  case "$ref" in refs/remotes/origin/HEAD) continue ;; esac
  name="$(printf '%s' "$ref" | sed 's@^refs/remotes/origin/@@')"
  files="$(git ls-tree -r --name-only "$ref")"
  if grep -Eq '^(application/app/Core/|application/bootstrap/|assets/|updater/|index\.php$)' <<<"$files"; then
    printf 'RETIRED_RUNTIME\t%s\n' "$name"
  elif grep -Fxq 'artisan' <<<"$files" && grep -Fxq 'bootstrap/app.php' <<<"$files"; then
    printf 'LARAVEL_CANDIDATE\t%s\n' "$name"
  else
    printf 'UNCLASSIFIED_REVIEW\t%s\n' "$name"
  fi
done
