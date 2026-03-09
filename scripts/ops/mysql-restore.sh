#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: scripts/ops/mysql-restore.sh <input.sql> [compose-file] [env-file]" >&2
  exit 1
fi

INPUT_FILE="$1"
COMPOSE_FILE="${2:-docker-compose.production.yml}"
ENV_FILE="${3:-.env}"
MYSQL_SERVICE="${MYSQL_SERVICE:-mysql}"

if [[ ! -f "$INPUT_FILE" ]]; then
  echo "SQL file not found: $INPUT_FILE" >&2
  exit 1
fi

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T "$MYSQL_SERVICE" sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < "$INPUT_FILE"

echo "Restore completed from $INPUT_FILE"
