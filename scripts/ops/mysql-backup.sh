#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: scripts/ops/mysql-backup.sh <output.sql> [compose-file] [env-file]" >&2
  exit 1
fi

OUTPUT_FILE="$1"
COMPOSE_FILE="${2:-docker-compose.production.yml}"
ENV_FILE="${3:-.env}"
MYSQL_SERVICE="${MYSQL_SERVICE:-mysql}"

mkdir -p "$(dirname "$OUTPUT_FILE")"

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T "$MYSQL_SERVICE" sh -lc 'mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' > "$OUTPUT_FILE"

echo "Backup written to $OUTPUT_FILE"
