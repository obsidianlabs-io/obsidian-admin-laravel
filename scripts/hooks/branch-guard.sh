#!/usr/bin/env bash
set -euo pipefail

# Protected Branch Push Guard
# Blocks direct pushes to main and release/* branches.
# Reads push refs from stdin (format: local_ref local_sha remote_ref remote_sha).
#
# Bypass (emergency only): LEFTHOOK_EXCLUDE=pre-push git push

while read -r local_ref local_sha remote_ref remote_sha; do
  if [[ "$remote_ref" =~ ^refs/heads/(main|release/.*)$ ]]; then
    echo "❌ Direct push to protected branch '${BASH_REMATCH[1]}' is blocked." >&2
    echo "" >&2
    echo "Please push to a feature branch and open a pull request instead." >&2
    echo "  git push origin HEAD:refs/heads/feature/your-branch-name" >&2
    echo "" >&2
    echo "To bypass (emergency only): LEFTHOOK_EXCLUDE=pre-push git push" >&2
    exit 1
  fi
done

exit 0
