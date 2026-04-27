#!/usr/bin/env bash
# PreToolUse Edit/MultiEdit/Write protect-paths hook for InstaParty
# Blocks edits to files that should never be modified by the agent.
set -euo pipefail

command -v jq >/dev/null 2>&1 || exit 0

INPUT=$(cat)
TOOL=$(echo "$INPUT" | jq -r '.tool_name // ""')
FILE=$(echo "$INPUT" | jq -r '.tool_input.file_path // .tool_input.path // ""')

if [ -z "$FILE" ]; then
  exit 0
fi

block() {
  echo "BLOCKED by pre-edit-protect-paths: $1" >&2
  echo "Tool: $TOOL — File: $FILE" >&2
  exit 2
}

# Convert to absolute-relative form for matching
REL="${FILE#$CLAUDE_PROJECT_DIR/}"

case "$REL" in
  .env|.env.*)
    block "secrets file — describe required env vars in chat instead"
    ;;
  vendor/*|*/vendor/*)
    block "vendor/ is composer-managed — modify composer.json instead"
    ;;
  node_modules/*|*/node_modules/*)
    block "node_modules/ is npm-managed"
    ;;
  storage/logs/*|*/storage/logs/*)
    block "log files are runtime artifacts — read them but never edit"
    ;;
  storage/framework/*|*/storage/framework/*)
    block "Laravel runtime cache — never edit"
    ;;
  public/build/*|*/public/build/*|public/hot|*/public/hot)
    block "build artifacts from Vite — modify resources/ instead"
    ;;
  .git/*|*/.git/*)
    block ".git internals — use git commands"
    ;;
  bootstrap/cache/*|*/bootstrap/cache/*)
    block "Laravel cache — never edit (use php artisan optimize:clear)"
    ;;
esac

# Block edits to lock files
case "$REL" in
  composer.lock)
    block "composer.lock — run composer commands, do not edit"
    ;;
  package-lock.json|yarn.lock|pnpm-lock.yaml)
    block "package lock — run package manager, do not edit"
    ;;
esac

exit 0
