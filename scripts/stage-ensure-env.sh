#!/usr/bin/env bash
# Patches /opt/rider/api/.env with vars required by the current code (idempotent).
set -euo pipefail

ENV="${RIDER_STAGE_API_ENV:-/opt/rider/api/.env}"
[[ -f "$ENV" ]] || cp /opt/rider/api/.env.example "$ENV"

append_if_missing() {
  local key="$1"
  local line="$2"
  if ! grep -q "^${key}=" "$ENV"; then
    printf '\n%s\n' "$line" >>"$ENV"
  fi
}

append_if_missing RIDER_KAFKA_MATCHER_TOPIC 'RIDER_KAFKA_MATCHER_TOPIC=rider.leads'
append_if_missing RIDER_KAFKA_MATCHER_GROUP 'RIDER_KAFKA_MATCHER_GROUP=rider-matcher'
append_if_missing RIDER_KAFKA_SETTLEMENT_TOPIC 'RIDER_KAFKA_SETTLEMENT_TOPIC=rider.lead.settlement'
append_if_missing RIDER_KAFKA_SETTLEMENT_GROUP 'RIDER_KAFKA_SETTLEMENT_GROUP=rider-balance'
append_if_missing RIDER_REDIS_HOST 'RIDER_REDIS_HOST=redis'
append_if_missing RIDER_REDIS_PORT 'RIDER_REDIS_PORT=6379'
append_if_missing RIDER_IMPERSONATION_SECRET 'RIDER_IMPERSONATION_SECRET=stage-impersonation-secret-change-me'