#!/usr/bin/env bash
set -euo pipefail

COMMIT_MSG_FILE="$1"
COMMIT_MSG=$(head -1 "$COMMIT_MSG_FILE")

# Allow merge commits
if [[ "$COMMIT_MSG" =~ ^Merge\ (branch|pull\ request) ]]; then
  exit 0
fi

# Conventional Commit pattern: type(scope)?!?: description
PATTERN='^(feat|fix|docs|style|refactor|perf|test|build|ci|chore|revert)(\([a-z0-9_-]+\))?(!)?: .+'

if ! [[ "$COMMIT_MSG" =~ $PATTERN ]]; then
  echo "❌ Commit message does not follow Conventional Commits format." >&2
  echo "" >&2
  echo "Expected: <type>(<scope>)?: <description>" >&2
  echo "Example:  feat(auth): add two-factor authentication" >&2
  echo "" >&2
  echo "Allowed types: feat, fix, docs, style, refactor, perf, test, build, ci, chore, revert" >&2
  exit 1
fi

# Check subject line length (max 72 characters)
if [ ${#COMMIT_MSG} -gt 72 ]; then
  echo "❌ Commit subject exceeds 72 characters (actual: ${#COMMIT_MSG})." >&2
  echo "Please shorten the subject line." >&2
  exit 1
fi

exit 0
