#!/usr/bin/env bash
# Stop hook for InstaParty
# Appends a session summary to .claude/session-log.md when Claude finishes responding.
# Critical: must check stop_hook_active to avoid infinite loops if we ever exit 2.
set -euo pipefail

INPUT=$(cat)
STOP_ACTIVE=$(echo "$INPUT" | jq -r '.stop_hook_active // false')

# If we've already triggered once in this stop cycle, do nothing
if [ "$STOP_ACTIVE" = "true" ]; then
  exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-$(pwd)}" 2>/dev/null || exit 0

LOG="${CLAUDE_PROJECT_DIR}/.claude/session-log.md"
mkdir -p "$(dirname "$LOG")"

TS=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
{
  echo ""
  echo "## $TS"

  if git rev-parse --git-dir >/dev/null 2>&1; then
    BRANCH=$(git branch --show-current 2>/dev/null || echo detached)
    echo "- branch: \`$BRANCH\`"

    DIRTY=$(git status --porcelain 2>/dev/null)
    if [ -n "$DIRTY" ]; then
      echo "- dirty:"
      echo '```'
      echo "$DIRTY" | head -40
      echo '```'
    else
      echo "- clean working tree"
    fi

    # Last commit on this branch
    LAST=$(git log -1 --pretty=format:'%h %s' 2>/dev/null || echo "(no commits)")
    echo "- last commit: $LAST"
  else
    echo "- (not a git repo)"
  fi
} >> "$LOG"

exit 0
