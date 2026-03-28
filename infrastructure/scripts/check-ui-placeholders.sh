#!/usr/bin/env bash
set -euo pipefail

matches="$(rg -n --glob '*.php' 'TODO:' src/ClientPortal/templates src/Admin || true)"

if [[ -n "$matches" ]]; then
  echo "Found leaked UI TODO markers:"
  echo "$matches"
  exit 1
fi

echo "No leaked UI TODO markers found."
