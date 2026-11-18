#!/usr/bin/env bash
# Register the stage deploy key in GitHub (read-only). Needs GITHUB_TOKEN with repo scope.
set -euo pipefail

STAGE_HOST="${RIDER_STAGE_HOST:-root@2.29.205.3}"
REPO="${RIDER_GITHUB_REPO:-gfsx0259/fiveminutes}"
TITLE="${RIDER_DEPLOY_KEY_TITLE:-stage-2.29.205.3}"

if [[ -z "${GITHUB_TOKEN:-}" ]]; then
  echo "Set GITHUB_TOKEN (classic PAT with repo or admin:repo_hook) and re-run." >&2
  echo "Or add manually: https://github.com/${REPO}/settings/keys" >&2
  echo >&2
  ssh -o BatchMode=yes "$STAGE_HOST" 'cat /root/.ssh/github_fiveminutes.pub' 2>/dev/null \
    || echo "Run scripts/setup-stage-github-ssh.sh on the stage host first." >&2
  exit 1
fi

PUBKEY="$(ssh -o BatchMode=yes "$STAGE_HOST" 'cat /root/.ssh/github_fiveminutes.pub')"
KEY_JSON="$(jq -nc --arg title "$TITLE" --arg key "$PUBKEY" '{title: $title, key: $key, read_only: true}')"

curl -fsSL \
  -X POST \
  -H "Accept: application/vnd.github+json" \
  -H "Authorization: Bearer ${GITHUB_TOKEN}" \
  "https://api.github.com/repos/${REPO}/keys" \
  -d "$KEY_JSON"

echo
echo "Deploy key registered for ${REPO}."