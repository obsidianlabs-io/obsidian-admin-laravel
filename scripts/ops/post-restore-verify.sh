#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: scripts/ops/post-restore-verify.sh <base-url> [compose-file] [env-file]" >&2
  exit 1
fi

BASE_URL="${1%/}"
COMPOSE_FILE="${2:-docker-compose.production.yml}"
ENV_FILE="${3:-.env}"
APP_SERVICE="${APP_SERVICE:-app}"

echo "==> Probing live health"
curl --fail --silent --show-error "$BASE_URL/api/health/live" | tee /dev/stderr >/dev/null

echo "==> Probing ready health"
curl --fail --silent --show-error "$BASE_URL/api/health/ready" | tee /dev/stderr >/dev/null

echo "==> Verifying Laravel bootstrap"
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T "$APP_SERVICE" php artisan about --only=environment

echo "==> Verifying health routes are registered"
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T "$APP_SERVICE" php artisan route:list --path=health

echo "Post-restore verification completed."
