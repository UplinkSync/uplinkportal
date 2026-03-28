#!/usr/bin/env bash
set -euo pipefail

echo "Running repository hygiene scan..."

fail=0

check_pattern() {
  local pattern="$1"
  local label="$2"

  if rg --hidden --glob '!.git/*' --glob '!vendor/*' --glob '!node_modules/*' --glob '!infrastructure/worker/node_modules/*' --glob '!infrastructure/scripts/repo-hygiene-scan.sh' --pcre2 -n "$pattern" . >/tmp/uplinkportal_hygiene_hits.txt 2>/dev/null; then
    echo "FAIL: ${label}"
    cat /tmp/uplinkportal_hygiene_hits.txt
    fail=1
  fi
}

# Secrets and private credentials.
check_pattern '-----BEGIN (RSA|EC|OPENSSH|DSA|PGP) PRIVATE KEY-----' 'Private key material found'
check_pattern 'sk_live_[0-9A-Za-z]{16,}' 'Live payment key pattern found'
check_pattern 'ghp_[0-9A-Za-z]{20,}' 'GitHub personal access token found'
check_pattern 'xox[baprs]-[0-9A-Za-z-]{20,}' 'Slack token found'
check_pattern '(?i)(api|secret|private)[_-]?key\s*[:=]\s*["'"'"'][A-Za-z0-9_./+=-]{20,}["'"'"']' 'Hard-coded API secret-like value found'
check_pattern '(?i)\\bstripe\\b' 'Forbidden Stripe reference found in public core'
check_pattern '(?i)payment\\s+gateway' 'Forbidden payment gateway wording found'
check_pattern '(?i)gateway\\s+module' 'Forbidden gateway module wording found'
check_pattern '(?i)coming\\s+soon\\s+gateway' 'Forbidden gateway teaser wording found'
check_pattern '(?i)upgrade\s+to\s+(pro|premium)' 'Forbidden upsell phrase found'
check_pattern '(?i)unlock\s+(pro|premium)' 'Forbidden premium unlock phrase found'
check_pattern '(?i)(premium|paid)\s+only' 'Forbidden premium-only wording found'
check_pattern '(?i)buy\s+the\s+(pro|premium)\s+version' 'Forbidden purchase upsell phrase found'

# Public-core policy guard: pro module files must stay placeholders.
if rg --files-without-match --glob 'src/Core/Modules/Pro/*.php' 'TODO: Implement in private/pro repository only\.' src/Core/Modules/Pro >/tmp/uplinkportal_hygiene_pro_hits.txt 2>/dev/null; then
  echo "FAIL: Pro placeholder marker missing in one or more files"
  cat /tmp/uplinkportal_hygiene_pro_hits.txt
  fail=1
fi

if [[ "$fail" -ne 0 ]]; then
  echo "Repository hygiene scan failed."
  exit 1
fi

echo "Repository hygiene scan passed."
