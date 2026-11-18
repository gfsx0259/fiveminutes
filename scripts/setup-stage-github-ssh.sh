#!/usr/bin/env bash
# Run on the stage host (as root). Makes git fetch use the deploy key.
set -euo pipefail

KEY="$HOME/.ssh/github_fiveminutes"
CONFIG="$HOME/.ssh/config"

if [[ ! -f "$KEY" ]]; then
  ssh-keygen -t ed25519 -f "$KEY" -N "" -C "deploy@$(hostname)-fiveminutes"
fi

touch "$CONFIG"
chmod 600 "$CONFIG"

if ! grep -q 'Host github.com-fiveminutes' "$CONFIG" 2>/dev/null; then
  cat >>"$CONFIG" <<'EOF'

Host github.com-fiveminutes
  HostName github.com
  User git
  IdentityFile ~/.ssh/github_fiveminutes
  IdentitiesOnly yes

EOF
fi

if [[ -d /opt/rider/.git ]]; then
  git config --global --add safe.directory /opt/rider 2>/dev/null || true
  cd /opt/rider
  git remote set-url origin git@github.com-fiveminutes:gfsx0259/fiveminutes.git 2>/dev/null || true
fi

echo "Deploy key public (add to GitHub → Settings → Deploy keys):"
cat "${KEY}.pub"