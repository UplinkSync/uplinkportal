#!/usr/bin/env bash
set -euo pipefail

REPO="${1:-}"
WORKER_NAME="<PLUGIN_SLUG_PLACEHOLDER>-license-server"

if [[ -z "$REPO" ]]; then
  REPO="$(gh repo view --json nameWithOwner --jq .nameWithOwner 2>/dev/null || true)"
fi

if [[ -z "$REPO" ]]; then
  echo "Unable to determine repository. Pass it explicitly: infrastructure/scripts/bootstrap.sh owner/repo"
  exit 1
fi

if ! command -v gh >/dev/null 2>&1; then
  echo "gh CLI is required: https://cli.github.com"
  exit 1
fi

if ! gh auth status >/dev/null 2>&1; then
  echo "Run 'gh auth login' first."
  exit 1
fi

set_secret() {
  local secret_name="$1"
  local value=""

  read -r -s -p "Enter ${secret_name} (leave empty to skip): " value
  echo

  if [[ -z "$value" ]]; then
    echo "Skipped ${secret_name}"
    return
  fi

  printf '%s' "$value" | gh secret set "$secret_name" --repo "$REPO"
  unset value
  echo "Saved ${secret_name}"
}

echo "Repository: $REPO"
echo ""
echo "Step 1: Set GitHub secrets used by workflows"
set_secret "SUPABASE_DB_URL"
set_secret "CLOUDFLARE_API_TOKEN"
set_secret "CLOUDFLARE_ACCOUNT_ID"
set_secret "WORKER_DATABASE_URL"

echo ""
echo "Step 2: Cloudflare worker secret commands"
echo "Run these commands from repo root after 'npm install' in infrastructure/worker/:"
echo ""
echo "cd infrastructure/worker"
echo "npx wrangler login"
echo "npx wrangler secret put DATABASE_URL --name ${WORKER_NAME}"
echo ""
echo "Step 3: Trigger workflows"
echo "gh workflow run db-migrate.yml --repo ${REPO}"
echo "gh workflow run deploy-worker.yml --repo ${REPO}"
echo ""
echo "Step 4: Verify rollout"
echo "- Open https://<worker-subdomain>.workers.dev/health"
echo "- Configure WordPress plugin settings"
echo "- Run validate-license test flow"
