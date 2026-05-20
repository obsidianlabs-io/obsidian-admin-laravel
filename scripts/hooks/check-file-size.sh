#!/usr/bin/env bash
set -euo pipefail

MAX_SIZE=$((5 * 1024 * 1024))  # 5 MB in bytes
EXIT_CODE=0

for file in "$@"; do
  if [ -f "$file" ]; then
    size=$(wc -c < "$file" | tr -d ' ')
    if [ "$size" -gt "$MAX_SIZE" ]; then
      size_mb=$(echo "scale=2; $size / 1048576" | bc)
      echo "❌ File exceeds 5 MB limit: $file (${size_mb} MB)" >&2
      EXIT_CODE=1
    fi
  fi
done

exit $EXIT_CODE
