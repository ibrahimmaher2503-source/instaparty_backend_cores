#!/usr/bin/env bash
# PostToolUse Edit/MultiEdit/Write hook for InstaParty
# Auto-formats PHP files with Laravel Pint after every edit. Non-blocking on failure.
set -euo pipefail

INPUT=$(cat)
FILE=$(echo "$INPUT" | jq -r '.tool_input.file_path // .tool_input.path // ""')

# Only act on PHP files
case "$FILE" in
  *.php) ;;
  *) exit 0 ;;
esac

# Skip if file no longer exists (was deleted)
[ -f "$FILE" ] || exit 0

# Skip if it's in a path we shouldn't touch
case "$FILE" in
  */vendor/*|*/node_modules/*|*/storage/*|*/bootstrap/cache/*) exit 0 ;;
esac

# Skip if Pint isn't installed yet
PINT="${CLAUDE_PROJECT_DIR}/vendor/bin/pint"
[ -x "$PINT" ] || exit 0

# Format quietly. Pint exits 0 on success, non-zero on style fixes applied (still success).
# Redirect stderr so we don't pollute Claude's output unless something is genuinely wrong.
if ! "$PINT" --quiet "$FILE" 2>/tmp/pint-error.log; then
  ERR=$(cat /tmp/pint-error.log)
  echo "Pint reported issues on $FILE:" >&2
  echo "$ERR" >&2
fi

exit 0
