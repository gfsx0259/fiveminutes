#!/usr/bin/env bash
# Deploy Rider to the closed stage from a dev machine (after git push).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAGE_HOST="${RIDER_STAGE_HOST:-root@2.29.205.3}"
STAGE_DIR="${RIDER_STAGE_DIR:-/opt/rider}"
CERT_SRC="${RIDER_CERT_DIR:-$ROOT/../cert}"
USE_RSYNC="${RIDER_DEPLOY_RSYNC:-}"

sync_tls_certs() {
  if ssh -o BatchMode=yes "$STAGE_HOST" "test -f ${STAGE_DIR}/traefik/cert/_wildcard.chase.io.pem"; then
    return 0
  fi
  echo "Syncing mkcert wildcards to stage (one-time or after cert wipe)…"
  scp "$CERT_SRC"/_wildcard.{rider,chase,marshall}.io.pem \
      "$CERT_SRC"/_wildcard.{rider,chase,marshall}.io-key.pem \
      "$STAGE_HOST:${STAGE_DIR}/traefik/cert/"
}

deploy_git() {
  ssh -o BatchMode=yes "$STAGE_HOST" bash -s <<REMOTE
set -euo pipefail
cd ${STAGE_DIR}
git fetch origin
git reset --hard origin/main
git log -1 --oneline
REMOTE
}

deploy_rsync() {
  echo "Deploying via rsync from ${ROOT}…"
  rsync -az --delete \
    --exclude '.git' \
    --exclude 'api/vendor' \
    --exclude 'api/runtime/logs/*' \
    --exclude 'dashboard/node_modules' \
    --exclude 'tests/vendor' \
    --exclude 'kratos/elements/node_modules' \
    --exclude 'kratos/elements/.next' \
    "$ROOT/" "$STAGE_HOST:${STAGE_DIR}/"
}

ensure_env() {
  ssh -o BatchMode=yes "$STAGE_HOST" bash -s <"$ROOT/scripts/stage-ensure-env.sh"
}

rollout() {
  ssh -o BatchMode=yes "$STAGE_HOST" bash -s <<REMOTE
set -euo pipefail
cd ${STAGE_DIR}
make -C infra up
make -C kratos up
make -C api up
make -C dashboard up
REMOTE
}

main() {
  sync_tls_certs
  ssh -o BatchMode=yes "$STAGE_HOST" "bash -s" <"$ROOT/scripts/setup-stage-github-ssh.sh" >/dev/null 2>&1 || true

  if [[ "$USE_RSYNC" == "1" ]]; then
    deploy_rsync
  elif ssh -o BatchMode=yes "$STAGE_HOST" "cd ${STAGE_DIR} && git fetch origin main 2>/dev/null"; then
    deploy_git
  else
    echo "git fetch on stage failed (deploy key missing?). Falling back to rsync." >&2
    echo "Fix: export GITHUB_TOKEN=… && ${ROOT}/scripts/register-github-deploy-key.sh" >&2
    deploy_rsync
  fi

  ensure_env
  rollout
  echo "Stage deploy finished."
}

main "$@"