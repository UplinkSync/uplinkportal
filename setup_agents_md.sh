#!/usr/bin/env bash
# setup_agents_md.sh
#
# Consolidates project AI instructions into a single AGENTS.md and creates
# symbolic links so each AI tool finds its expected filename.
#
# Usage:
#   bash setup_agents_md.sh [--windows-fallback]
#
# Options:
#   --windows-fallback   Write "@AGENTS.md" stub files instead of symlinks.
#                        Use this on Windows where mklink requires elevation
#                        or the filesystem does not support POSIX symlinks.
#
# Idempotent: safe to run multiple times.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CANONICAL="AGENTS.md"
WINDOWS_FALLBACK=false

# ── Argument parsing ────────────────────────────────────────────────────────
for arg in "$@"; do
  case "$arg" in
    --windows-fallback) WINDOWS_FALLBACK=true ;;
    *) echo "Unknown option: $arg" >&2; exit 1 ;;
  esac
done

# ── Helper: create a relative symlink ──────────────────────────────────────
# create_symlink <link_path> <target_relative_to_link>
create_symlink() {
  local link="$1"
  local target="$2"
  local link_abs="$REPO_ROOT/$link"
  local link_dir
  link_dir="$(dirname "$link_abs")"

  # Remove existing file/symlink (but NOT a real directory)
  if [ -L "$link_abs" ]; then
    rm "$link_abs"
    echo "  Removed stale symlink: $link"
  elif [ -f "$link_abs" ]; then
    echo "  WARNING: '$link' is a regular file — it will be replaced by a symlink."
    echo "           Back it up first if it contains unique content."
    rm "$link_abs"
  fi

  mkdir -p "$link_dir"
  ln -s "$target" "$link_abs"
  echo "  Linked  : $link  ->  $target"
}

# ── Helper: write a @-directive stub (Windows fallback) ────────────────────
create_stub() {
  local link="$1"
  local link_abs="$REPO_ROOT/$link"
  local link_dir
  link_dir="$(dirname "$link_abs")"

  if [ -L "$link_abs" ]; then
    rm "$link_abs"
    echo "  Removed existing symlink: $link (replacing with stub)"
  fi

  mkdir -p "$link_dir"
  printf '@%s\n' "$CANONICAL" > "$link_abs"
  echo "  Stub    : $link  (@$CANONICAL directive)"
}

# ── Helper: dispatch based on mode ─────────────────────────────────────────
link_or_stub() {
  local link="$1"
  local target="$2"   # relative target (used for symlinks only)
  if [ "$WINDOWS_FALLBACK" = true ]; then
    create_stub "$link"
  else
    create_symlink "$link" "$target"
  fi
}

# ── Consolidation step (runs only when source files are plain files) ────────
consolidate() {
  local src="$1"
  local src_abs="$REPO_ROOT/$src"
  local canonical_abs="$REPO_ROOT/$CANONICAL"

  # Skip if source does not exist or is already a symlink (already consolidated)
  [ -f "$src_abs" ] && [ ! -L "$src_abs" ] || return 0

  echo ""
  echo "  Appending '$src' into '$CANONICAL'..."
  {
    printf '\n\n---\n<!-- Source: %s -->\n\n' "$src"
    cat "$src_abs"
  } >> "$canonical_abs"
  rm "$src_abs"
  echo "  Removed  : $src (content merged)"
}

# ══════════════════════════════════════════════════════════════════════════════
echo ""
echo "=== UplinkPortal: AGENTS.md setup ==="
echo "    Repo root : $REPO_ROOT"
echo "    Mode      : $([ "$WINDOWS_FALLBACK" = true ] && echo 'Windows stub (@directive)' || echo 'POSIX symlinks')"
echo ""

cd "$REPO_ROOT"

# 1. Ensure AGENTS.md exists ─────────────────────────────────────────────────
if [ ! -f "$CANONICAL" ] && [ ! -L "$CANONICAL" ]; then
  echo "  Creating empty $CANONICAL — populate it with your AI instructions."
  touch "$CANONICAL"
fi
echo "  Canonical : $CANONICAL  ($(wc -l < "$CANONICAL") lines)"

# 2. Consolidate legacy source files (if they still exist as plain files) ─────
echo ""
echo "── Consolidating legacy instruction files ──"
consolidate ".github/copilot-instructions.md"
consolidate "docs/development/codex-agent-rules.md"
consolidate "docs/development/codex-working-standard.md"
# Add more source files here as needed, e.g.:
# consolidate "CURSOR_RULES.md"

# 3. Create root-level alias symlinks ─────────────────────────────────────────
echo ""
echo "── Creating root-level aliases ──"
link_or_stub "CLAUDE.md"  "AGENTS.md"
link_or_stub "CODEX.md"   "AGENTS.md"
# Uncomment to add more aliases:
# link_or_stub "CURSOR_RULES.md" "AGENTS.md"
# link_or_stub "GEMINI.md"       "AGENTS.md"

# 4. Create tool-specific directory aliases ────────────────────────────────────
echo ""
echo "── Creating tool-specific directory aliases ──"
link_or_stub ".github/copilot-instructions.md" "../AGENTS.md"
link_or_stub "docs/development/codex-agent-rules.md"     "../../AGENTS.md"
link_or_stub "docs/development/codex-working-standard.md" "../../AGENTS.md"

# 5. Summary ──────────────────────────────────────────────────────────────────
echo ""
echo "── Verification ──"
for f in \
  "AGENTS.md" \
  "CLAUDE.md" \
  "CODEX.md" \
  ".github/copilot-instructions.md" \
  "docs/development/codex-agent-rules.md" \
  "docs/development/codex-working-standard.md"
do
  if [ -L "$f" ]; then
    printf "  %-50s  ->  %s\n" "$f" "$(readlink "$f")"
  elif [ -f "$f" ]; then
    printf "  %-50s  [regular file, %s lines]\n" "$f" "$(wc -l < "$f")"
  else
    printf "  %-50s  [MISSING]\n" "$f"
  fi
done

echo ""
echo "Done. All AI agents will read from $CANONICAL."
echo ""
echo "Windows note: if symlinks are not supported, re-run with --windows-fallback"
echo "to write '@AGENTS.md' stub files instead (supported by many AI tools)."
echo ""
